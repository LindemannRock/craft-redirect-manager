<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services\analytics;

use Craft;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use lindemannrock\base\helpers\AnalyticsIpHelper;
use lindemannrock\base\helpers\DbHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\helpers\AnalyticsRequestTypeHelper;
use lindemannrock\redirectmanager\records\AnalyticsRecord;
use lindemannrock\redirectmanager\RedirectManager;
use yii\db\Expression;

/**
 * Analytics Tracking Service
 *
 * Records 404 events and handles IP processing.
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since 5.25.0
 */
class AnalyticsTrackingService
{
    use LoggingTrait;

    public function __construct()
    {
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

    /**
     * Record a 404 error
     *
     * @param string $url
     * @param bool $handled Whether the 404 was handled by a redirect
     * @param array $context Optional context data (source plugin, metadata)
     * @return void
     */
    public function record404(string $url, bool $handled, array $context = []): void
    {
        $settings = RedirectManager::$plugin->getSettings();

        // Check if analytics is enabled (master switch)
        if (!$settings->enableAnalytics) {
            return;
        }

        $contextSiteId = $context['siteId'] ?? null;
        $siteId = is_numeric($contextSiteId) && (int)$contextSiteId > 0
            ? (int)$contextSiteId
            : Craft::$app->getSites()->getCurrentSite()->id;

        // Extract source plugin and redirectId from context
        $sourcePlugin = $context['source'] ?? 'redirect-manager';
        $redirectId = $context['redirectId'] ?? null;

        // Strip query string from URL if configured
        $urlParsed = $settings->stripQueryStringFromStats
            ? strtok($url, '?')
            : $url;

        // Clean the URL. Lowercased so case-variant hits merge into one
        // analytics row on PostgreSQL the same way MySQL's ci collation always
        // merged them (query-string values flatten too — status quo on MySQL).
        // The raw `url` column keeps original casing for display.
        $urlParsed = strtolower($this->cleanUrl($urlParsed));

        // Get referrer, IP, and user agent
        $request = Craft::$app->getRequest();
        $referrer = $request->getReferrer();
        $userAgent = $request->getUserAgent();

        // Detect device information using Matomo DeviceDetector
        $deviceInfo = RedirectManager::$plugin->deviceDetection->detectDevice($userAgent);
        $requestType = AnalyticsRequestTypeHelper::detect(
            $url,
            (bool)$deviceInfo['isRobot'],
            is_string($deviceInfo['trafficType'] ?? null) ? $deviceInfo['trafficType'] : null,
        );

        $ipState = AnalyticsIpHelper::prepare(
            $request->getUserIP(),
            $settings->anonymizeIpAddress,
            $settings->enableGeoDetection,
            fn(string $ip): string => $this->_hashIpWithSalt($ip),
        );

        if ($ipState['hashError'] !== null) {
            $this->logError('Failed to hash IP address', ['error' => $ipState['hashError']->getMessage()]);
        }

        $ip = $ipState['hashedIp'];
        $geoData = $ipState['geoLookupIp']
            ? RedirectManager::$plugin->analytics->breakdown->getLocationFromIp($ipState['geoLookupIp'])
            : null;

        $nowUtc = $this->currentTimeUtc();
        $now = Db::prepareDateForDb($nowUtc);
        $analyticsData = [
                'siteId' => $siteId,
                'url' => $url,
                'urlParsed' => $urlParsed,
                'handled' => $handled,
                'redirectId' => $redirectId,
                'sourcePlugin' => $sourcePlugin,
                'count' => 1,
                'referrer' => $referrer,
                'ip' => $ip,
                'userAgent' => $userAgent,
                'language' => $deviceInfo['language'] ?? null,
                // Device detection fields
                'deviceType' => $deviceInfo['deviceType'],
                'deviceBrand' => $deviceInfo['deviceBrand'],
                'deviceModel' => $deviceInfo['deviceModel'],
                'browser' => $deviceInfo['browser'],
                'browserVersion' => $deviceInfo['browserVersion'],
                'browserEngine' => $deviceInfo['browserEngine'],
                'osName' => $deviceInfo['osName'],
                'osVersion' => $deviceInfo['osVersion'],
                'clientType' => $deviceInfo['clientType'],
                'isRobot' => $deviceInfo['isRobot'],
                'isMobileApp' => $deviceInfo['isMobileApp'],
                'botName' => $deviceInfo['botName'],
                'botCategory' => $deviceInfo['botCategory'] ?? null,
                'botUrl' => $deviceInfo['botUrl'] ?? null,
                'botProducerName' => $deviceInfo['botProducerName'] ?? null,
                'botProducerUrl' => $deviceInfo['botProducerUrl'] ?? null,
                'isSystemAgent' => $deviceInfo['isSystemAgent'] ?? false,
                'trafficType' => $deviceInfo['trafficType'] ?? 'human',
                'requestType' => $requestType,
                // Geographic data
                'country' => $geoData['countryCode'] ?? null,
                'city' => $geoData['city'] ?? null,
                'region' => $geoData['region'] ?? null,
                'latitude' => $geoData['lat'] ?? null,
                'longitude' => $geoData['lon'] ?? null,
                'lastHit' => $now,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ];

        $analyticsData = $this->filterAnalyticsColumns($analyticsData);
    
        $dimensionData = $analyticsData;
        unset($dimensionData['count'], $dimensionData['lastHit'], $dimensionData['dateCreated'], $dimensionData['dateUpdated'], $dimensionData['uid']);
        ksort($dimensionData);
        $hitDate = $nowUtc->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()))->format('Y-m-d');
        $dailyData = $analyticsData + [
            'bucketKey' => hash('sha256', Json::encode([$hitDate, $dimensionData])),
            'hitDate' => $hitDate,
        ];

        Craft::$app->getDb()->transaction(function() use ($analyticsData, $dailyData, $url, $handled, $redirectId, $sourcePlugin, $referrer, $ip, $userAgent, $deviceInfo, $requestType, $geoData, $now): void {
            Craft::$app->getDb()->createCommand()
                ->upsert(
                    AnalyticsRecord::tableName(),
                    $analyticsData,
                    $this->filterAnalyticsColumns([
                        // Existing-row reference must be table-qualified — bare [[count]]
                        // is ambiguous in PostgreSQL's ON CONFLICT DO UPDATE (42702).
                        'count' => new Expression(DbHelper::existingColumn('redirectmanager_analytics', 'count') . ' + 1'),
                        'url' => $url, // Update to latest URL (preserves most recent query string)
                        'handled' => $handled,
                        'redirectId' => $redirectId,
                        'sourcePlugin' => $sourcePlugin,
                        'referrer' => $referrer,
                        'ip' => $ip,
                        'userAgent' => $userAgent,
                        'language' => $deviceInfo['language'] ?? null,
                        // Device detection fields
                        'deviceType' => $deviceInfo['deviceType'],
                        'deviceBrand' => $deviceInfo['deviceBrand'],
                        'deviceModel' => $deviceInfo['deviceModel'],
                        'browser' => $deviceInfo['browser'],
                        'browserVersion' => $deviceInfo['browserVersion'],
                        'browserEngine' => $deviceInfo['browserEngine'],
                        'osName' => $deviceInfo['osName'],
                        'osVersion' => $deviceInfo['osVersion'],
                        'clientType' => $deviceInfo['clientType'],
                        'isRobot' => $deviceInfo['isRobot'],
                        'isMobileApp' => $deviceInfo['isMobileApp'],
                        'botName' => $deviceInfo['botName'],
                        'botCategory' => $deviceInfo['botCategory'] ?? null,
                        'botUrl' => $deviceInfo['botUrl'] ?? null,
                        'botProducerName' => $deviceInfo['botProducerName'] ?? null,
                        'botProducerUrl' => $deviceInfo['botProducerUrl'] ?? null,
                        'isSystemAgent' => $deviceInfo['isSystemAgent'] ?? false,
                        'trafficType' => $deviceInfo['trafficType'] ?? 'human',
                        'requestType' => $requestType,
                        // Geographic data
                        'country' => $geoData['countryCode'] ?? null,
                        'city' => $geoData['city'] ?? null,
                        'region' => $geoData['region'] ?? null,
                        'latitude' => $geoData['lat'] ?? null,
                        'longitude' => $geoData['lon'] ?? null,
                        'lastHit' => $now,
                        'dateUpdated' => $now,
                    ]),
                )
                ->execute();

            $this->writeDailyAggregate($dailyData, $now);
        });
    
        $this->logDebug('Recorded 404 analytics hit', ['url' => $url, 'urlParsed' => $urlParsed, 'source' => $sourcePlugin]);
    }

    /** Return the UTC event time; isolated behavioral tests can control it. */
    protected function currentTimeUtc(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone('UTC'));
    }

    /** @param array<string, mixed> $dailyData */
    protected function writeDailyAggregate(array $dailyData, string $now): void
    {
        Craft::$app->getDb()->createCommand()
            ->upsert(
                AnalyticsRecord::dailyTableName(),
                $dailyData,
                [
                    'count' => new Expression(DbHelper::existingColumn('redirectmanager_analytics_daily', 'count') . ' + 1'),
                    'lastHit' => $now,
                    'dateUpdated' => $now,
                ],
            )
            ->execute();
    }

    /**
     * Keep pre-release local databases working until their analytics table is
     * refreshed with the latest optional columns from Install.php.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterAnalyticsColumns(array $data): array
    {
        $schema = Craft::$app->getDb()->getSchema()->getTableSchema(AnalyticsRecord::tableName());
        if ($schema === null) {
            return $data;
        }

        return array_intersect_key($data, $schema->columns);
    }

    /**
     * Clean and normalize URL
     *
     * @param string $url
     * @return string
     */
    private function cleanUrl(string $url): string
    {
        $url = trim($url);
        $url = str_replace(["\r", "\n", "\t"], '', $url);
        $url = preg_replace('#(?<!:)//+#', '/', $url);
        return $url;
    }

    /**
     * Hash IP address with salt for privacy
     *
     * Uses SHA256 with a secret salt to hash IPs. This prevents rainbow table attacks
     * while still allowing unique visitor tracking (same IP = same hash).
     *
     * @param string $ip The IP address to hash
     * @return string Hashed IP address (64 characters)
     * @throws \Exception If salt is not configured
     */
    private function _hashIpWithSalt(string $ip): string
    {
        $settings = RedirectManager::$plugin->getSettings();
        $salt = $settings->ipHashSalt;

        if (!$salt || $salt === '$REDIRECT_MANAGER_IP_SALT' || trim($salt) === '') {
            $this->logWarning('IP hash salt not configured - IP tracking disabled', [
                'ip' => 'hidden',
                'saltConfigured' => false,
            ]);
            throw new \Exception('IP hash salt not configured. Run: php craft redirect-manager/security/generate-salt');
        }

        return hash('sha256', $ip . $salt);
    }
}

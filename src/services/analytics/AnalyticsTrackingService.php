<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services\analytics;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\records\AnalyticsRecord;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Analytics Tracking Service
 *
 * Records 404 events and handles IP processing.
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class AnalyticsTrackingService
{
    use LoggingTrait;

    private AnalyticsExportService $exportService;

    public function __construct(AnalyticsExportService $exportService)
    {
        $this->exportService = $exportService;
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

    /**
     * Record a 404 error
     *
     * @param string $url
     * @param bool $handled Whether the 404 was handled by a redirect
     * @param array $context Optional context data (source plugin, metadata)
     * @return void
     * @since 5.1.0
     */
    public function record404(string $url, bool $handled, array $context = []): void
    {
        $settings = RedirectManager::$plugin->getSettings();

        // Check if analytics is enabled (master switch)
        if (!$settings->enableAnalytics) {
            return;
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        // Extract source plugin and redirectId from context
        $sourcePlugin = $context['source'] ?? 'redirect-manager';
        $redirectId = $context['redirectId'] ?? null;

        // Strip query string from URL if configured
        $urlParsed = $settings->stripQueryStringFromStats
            ? strtok($url, '?')
            : $url;

        // Clean the URL
        $urlParsed = $this->cleanUrl($urlParsed);

        // Get referrer, IP, and user agent
        $request = Craft::$app->getRequest();
        $referrer = $request->getReferrer();
        $userAgent = $request->getUserAgent();

        // Detect device information using Matomo DeviceDetector
        $deviceInfo = RedirectManager::$plugin->deviceDetection->detectDevice($userAgent);

        // Multi-step IP processing for privacy and geo detection
        // IP is always captured when analytics is enabled (like smart-links)
        $ip = null;
        $geoData = null;
        $rawIp = $request->getUserIP();

        // Step 1: Subnet masking (if anonymizeIpAddress enabled)
        if ($settings->anonymizeIpAddress && $rawIp) {
            $rawIp = $this->_anonymizeIp($rawIp);
        }

        // Step 2: Get geo location (BEFORE hashing, using anonymized or full IP)
        if ($settings->enableGeoDetection && $rawIp) {
            $geoData = RedirectManager::$plugin->analytics->breakdown->getLocationFromIp($rawIp);
        }

        // Step 3: Hash with salt for storage
        if ($rawIp) {
            try {
                $ip = $this->_hashIpWithSalt($rawIp);
            } catch (\Exception $e) {
                $this->logError('Failed to hash IP address', ['error' => $e->getMessage()]);
                $ip = null;
            }
        }

        // Check if record exists
        $existing = (new Query())
            ->from(AnalyticsRecord::tableName())
            ->where([
                'urlParsed' => $urlParsed,
                'siteId' => $siteId,
            ])
            ->one();

        if ($existing) {
            // Update existing record
            Craft::$app->getDb()->createCommand()
                ->update(
                    AnalyticsRecord::tableName(),
                    [
                        'count' => new \yii\db\Expression('[[count]] + 1'),
                        'url' => $url, // Update to latest URL (preserves most recent query string)
                        'handled' => $handled,
                        'redirectId' => $redirectId,
                        'sourcePlugin' => $sourcePlugin,
                        'referrer' => $referrer,
                        'ip' => $ip,
                        'userAgent' => $userAgent,
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
                        // Geographic data
                        'country' => $geoData['countryCode'] ?? null,
                        'city' => $geoData['city'] ?? null,
                        'region' => $geoData['region'] ?? null,
                        'latitude' => $geoData['lat'] ?? null,
                        'longitude' => $geoData['lon'] ?? null,
                        'lastHit' => Db::prepareDateForDb(new \DateTime()),
                        'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
                    ],
                    ['id' => $existing['id']]
                )
                ->execute();

            $this->logDebug('Updated 404 analytics record', ['url' => $url, 'urlParsed' => $urlParsed, 'count' => $existing['count'] + 1, 'source' => $sourcePlugin]);
        } else {
            // Create new record
            $record = new AnalyticsRecord();
            $record->siteId = $siteId;
            $record->url = $url;
            $record->urlParsed = $urlParsed;
            $record->handled = $handled;
            $record->redirectId = $redirectId;
            $record->sourcePlugin = $sourcePlugin;
            $record->count = 1;
            $record->referrer = $referrer;
            $record->ip = $ip;
            $record->userAgent = $userAgent;
            // Device detection fields
            $record->deviceType = $deviceInfo['deviceType'];
            $record->deviceBrand = $deviceInfo['deviceBrand'];
            $record->deviceModel = $deviceInfo['deviceModel'];
            $record->browser = $deviceInfo['browser'];
            $record->browserVersion = $deviceInfo['browserVersion'];
            $record->browserEngine = $deviceInfo['browserEngine'];
            $record->osName = $deviceInfo['osName'];
            $record->osVersion = $deviceInfo['osVersion'];
            $record->clientType = $deviceInfo['clientType'];
            $record->isRobot = $deviceInfo['isRobot'];
            $record->isMobileApp = $deviceInfo['isMobileApp'];
            $record->botName = $deviceInfo['botName'];
            // Geographic data
            $record->country = $geoData['countryCode'] ?? null;
            $record->city = $geoData['city'] ?? null;
            $record->region = $geoData['region'] ?? null;
            $record->latitude = $geoData['lat'] ?? null;
            $record->longitude = $geoData['lon'] ?? null;
            $record->lastHit = Db::prepareDateForDb(new \DateTime());

            if ($record->save()) {
                $this->logDebug('Created 404 analytics record', ['url' => $url, 'urlParsed' => $urlParsed, 'source' => $sourcePlugin]);

                // Check if we need to trim analytics
                if ($settings->autoTrimAnalytics) {
                    $this->exportService->trimAnalytics();
                }
            } else {
                $this->logError('Failed to save 404 analytics record', ['errors' => $record->getErrors()]);
            }
        }
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
        $url = preg_replace('#/+#', '/', $url);
        return $url;
    }

    /**
     * Anonymize IP address (keep first 3 octets for IPv4, first 4 segments for IPv6)
     *
     * @param string|null $ip
     * @return string|null
     */
    private function _anonymizeIp(?string $ip): ?string
    {
        if (empty($ip)) {
            return null;
        }

        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            // Keep first 4 segments, anonymize the rest
            $parts = array_slice($parts, 0, 4);
            return implode(':', $parts) . '::';
        }

        return null;
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
                'saltValue' => $salt ?? 'NULL',
            ]);
            throw new \Exception('IP hash salt not configured. Run: php craft redirect-manager/security/generate-salt');
        }

        return hash('sha256', $ip . $salt);
    }
}

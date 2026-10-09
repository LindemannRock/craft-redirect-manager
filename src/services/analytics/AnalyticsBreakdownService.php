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
use lindemannrock\base\helpers\GeoHelper;
use lindemannrock\base\traits\GeoLookupTrait;
use lindemannrock\redirectmanager\records\AnalyticsRecord;
use lindemannrock\redirectmanager\RedirectManager;
use yii\db\Expression;

/**
 * Analytics Breakdown Service
 *
 * Device, browser, OS, geo, and bot breakdowns.
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since 5.25.0
 */
class AnalyticsBreakdownService
{
    use AnalyticsQueryTrait;
    use GeoLookupTrait;

    /**
     * Get device type breakdown
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     */
    public function getDeviceBreakdown(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['deviceType', 'count' => new Expression('SUM([[count]])')])
            ->from(AnalyticsRecord::dailyTableName())
            ->where(['not', ['deviceType' => null]])
            ->groupBy('deviceType')
            ->orderBy(['count' => SORT_DESC]);

        $this->applyDateFilter($query, $days, $startDate, $endDate);
        $this->applySiteFilter($query, $siteId);

        $results = $query->all();

        return [
            'labels' => array_map(function($type) {
                return ucfirst($type);
            }, array_column($results, 'deviceType')),
            'values' => array_map('intval', array_column($results, 'count')),
        ];
    }

    /**
     * Get browser breakdown
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     */
    public function getBrowserBreakdown(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['browser', 'count' => new Expression('SUM([[count]])')])
            ->from(AnalyticsRecord::dailyTableName())
            ->where(['not', ['browser' => null]])
            ->groupBy('browser')
            ->orderBy(['count' => SORT_DESC])
            ->limit(10);

        $this->applyDateFilter($query, $days, $startDate, $endDate);
        $this->applySiteFilter($query, $siteId);

        $results = $query->all();

        return [
            'labels' => array_column($results, 'browser'),
            'values' => array_map('intval', array_column($results, 'count')),
        ];
    }

    /**
     * Get OS breakdown
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     */
    public function getOsBreakdown(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['osName', 'count' => new Expression('SUM([[count]])')])
            ->from(AnalyticsRecord::dailyTableName())
            ->where(['not', ['osName' => null]])
            ->groupBy('osName')
            ->orderBy(['count' => SORT_DESC]);

        $this->applyDateFilter($query, $days, $startDate, $endDate);
        $this->applySiteFilter($query, $siteId);

        $results = $query->all();

        return [
            'labels' => array_column($results, 'osName'),
            'values' => array_map('intval', array_column($results, 'count')),
        ];
    }

    /**
     * Get bot traffic stats
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     */
    public function getBotStats(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->from(AnalyticsRecord::dailyTableName());

        $this->applyDateFilter($query, $days, $startDate, $endDate);
        $this->applySiteFilter($query, $siteId);

        $total = (int) (clone $query)
            ->select(['total' => new Expression('COALESCE(SUM([[count]]), 0)')])
            ->scalar();

        $typeRows = (clone $query)
            ->select(['requestType', 'count' => new Expression('SUM([[count]])')])
            ->andWhere(['requestType' => ['normal', 'system', 'bot', 'probe']])
            ->groupBy(['requestType'])
            ->all();

        $typeCounts = [
            'normal' => 0,
            'system' => 0,
            'bot' => 0,
            'probe' => 0,
        ];
        foreach ($typeRows as $row) {
            $type = (string)($row['requestType'] ?? '');
            if (isset($typeCounts[$type])) {
                $typeCounts[$type] = (int)$row['count'];
            }
        }

        $humanCount = $typeCounts['normal'];
        $botCount = $typeCounts['bot'];
        $systemCount = $typeCounts['system'];
        $probeCount = $typeCounts['probe'];
        $nonHumanCount = $systemCount + $botCount + $probeCount;

        $botPercentage = $total > 0 ? round(($botCount / $total) * 100, 1) : 0;
        $nonHumanPercentage = $total > 0 ? round(($nonHumanCount / $total) * 100, 1) : 0;

        $topAgents = (clone $query)
            ->select([
                'botName',
                'requestType',
                'botCategory',
                'botProducerName',
                'count' => new Expression('SUM([[count]])'),
            ])
            ->andWhere(['not', ['botName' => null]])
            ->andWhere(['<>', 'botName', ''])
            ->groupBy(['botName', 'requestType', 'botCategory', 'botProducerName'])
            ->orderBy(['count' => SORT_DESC])
            ->limit(5)
            ->all();

        return [
            'total' => $total,
            'botCount' => $botCount,
            'humanCount' => $humanCount,
            'systemCount' => $systemCount,
            'probeCount' => $probeCount,
            'nonHumanCount' => $nonHumanCount,
            'botPercentage' => $botPercentage,
            'nonHumanPercentage' => $nonHumanPercentage,
            'topAgents' => $topAgents,
            'topBots' => $topAgents,
            'chart' => [
                'types' => ['normal', 'system', 'bot', 'probe'],
                'values' => [$humanCount, $systemCount, $botCount, $probeCount],
            ],
        ];
    }

    /**
     * Get top countries
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param int $limit
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     */
    public function getTopCountries(int|array|null $siteId = null, int $days = 30, int $limit = 15, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['country', 'count' => new Expression('SUM([[count]])')])
            ->from(AnalyticsRecord::dailyTableName())
            ->where(['not', ['country' => null]])
            ->andWhere(['not', ['country' => '']])
            ->groupBy(['country'])
            ->orderBy(['count' => SORT_DESC])
            ->limit($limit);

        $this->applyDateFilter($query, $days, $startDate, $endDate);
        $this->applySiteFilter($query, $siteId);

        $totalQuery = (new Query())
            ->select(['total' => new Expression('COALESCE(SUM([[count]]), 0)')])
            ->from(AnalyticsRecord::dailyTableName())
            ->where(['not', ['country' => null]])
            ->andWhere(['not', ['country' => '']]);
        $this->applyDateFilter($totalQuery, $days, $startDate, $endDate);
        $this->applySiteFilter($totalQuery, $siteId);

        $results = $query->all();
        $total = (int)$totalQuery->scalar();

        $countries = [];
        foreach ($results as $row) {
            $countries[] = [
                'country' => $row['country'],
                'name' => GeoHelper::getCountryName($row['country'] ?? ''),
                'count' => (int)$row['count'],
                'percentage' => $total > 0 ? round(($row['count'] / $total) * 100, 1) : 0,
            ];
        }

        return $countries;
    }

    /**
     * Get top cities
     *
     * @param int|array<int>|null $siteId
     * @param int $days
     * @param int $limit
     * @param \DateTime|null $startDate Start date for filtering
     * @param \DateTime|null $endDate End date for filtering
     * @return array
     */
    public function getTopCities(int|array|null $siteId = null, int $days = 30, int $limit = 15, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['city', 'country', 'count' => new Expression('SUM([[count]])')])
            ->from(AnalyticsRecord::dailyTableName())
            ->where(['not', ['city' => null]])
            ->andWhere(['not', ['city' => '']])
            ->groupBy(['city', 'country'])
            ->orderBy(['count' => SORT_DESC])
            ->limit($limit);

        $this->applyDateFilter($query, $days, $startDate, $endDate);
        $this->applySiteFilter($query, $siteId);

        $totalQuery = (new Query())
            ->select(['total' => new Expression('COALESCE(SUM([[count]]), 0)')])
            ->from(AnalyticsRecord::dailyTableName())
            ->where(['not', ['city' => null]])
            ->andWhere(['not', ['city' => '']]);
        $this->applyDateFilter($totalQuery, $days, $startDate, $endDate);
        $this->applySiteFilter($totalQuery, $siteId);

        $results = $query->all();
        $total = (int)$totalQuery->scalar();

        $cities = [];
        foreach ($results as $row) {
            $cities[] = [
                'city' => $row['city'],
                'country' => $row['country'],
                'countryName' => GeoHelper::getCountryName($row['country'] ?? ''),
                'count' => (int)$row['count'],
                'percentage' => $total > 0 ? round(($row['count'] / $total) * 100, 1) : 0,
            ];
        }

        return $cities;
    }

    /**
     * Get location data from IP address
     *
     * @param string $ip
     * @return array|null
     */
    public function getLocationFromIp(string $ip): ?array
    {
        $geoData = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ? $this->getDefaultLocation()
            : $this->lookupGeoIp($ip, $this->getGeoConfig());

        if ($geoData === null) {
            return null;
        }

        // Normalize response to match expected format (lat/lon keys)
        return [
            'countryCode' => $geoData['countryCode'] ?? null,
            'country' => $geoData['country'] ?? null,
            'city' => $geoData['city'] ?? null,
            'region' => $geoData['region'] ?? null,
            'lat' => $geoData['latitude'] ?? null,
            'lon' => $geoData['longitude'] ?? null,
        ];
    }

    /**
     * Get geo config from plugin settings
     *
     * @return array<string, mixed>
     */
    public function getGeoConfig(): array
    {
        $settings = RedirectManager::$plugin->getSettings();

        return [
            'provider' => $settings->geoProvider ?? 'ip-api.com',
            'apiKey' => $settings->geoApiKey ?? null,
            'logCategory' => RedirectManager::$plugin->id,
        ];
    }

    /**
     * Get default location for private/local IPs
     *
     * @return array<string, mixed>|null
     */
    private function getDefaultLocation(): ?array
    {
        $settings = RedirectManager::$plugin->getSettings();
        $defaultCountry = $settings->defaultCountry;
        $defaultCity = $settings->defaultCity;

        if (!$defaultCountry || !$defaultCity) {
            return null;
        }

        $location = GeoHelper::getDefaultLocation($defaultCountry, $defaultCity);

        if ($location !== null) {
            return $location;
        }

        Craft::warning('Configured default analytics location was not found; leaving local/private IP geo fields empty. | ' . json_encode([
            'configuredCountry' => $defaultCountry,
            'configuredCity' => $defaultCity,
        ]), RedirectManager::$plugin->id);

        return null;
    }
}

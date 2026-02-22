<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services\analytics;

use craft\db\Query;
use craft\helpers\App;
use lindemannrock\base\helpers\GeoHelper;
use lindemannrock\base\traits\GeoLookupTrait;
use lindemannrock\redirectmanager\records\AnalyticsRecord;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Analytics Breakdown Service
 *
 * Device, browser, OS, geo, and bot breakdowns.
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.7.0
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
     * @since 5.7.0
     */
    public function getDeviceBreakdown(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['deviceType', 'COUNT(*) as count'])
            ->from(AnalyticsRecord::tableName())
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
     * @since 5.7.0
     */
    public function getBrowserBreakdown(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['browser', 'COUNT(*) as count'])
            ->from(AnalyticsRecord::tableName())
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
     * @since 5.7.0
     */
    public function getOsBreakdown(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['osName', 'COUNT(*) as count'])
            ->from(AnalyticsRecord::tableName())
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
     * @since 5.7.0
     */
    public function getBotStats(int|array|null $siteId = null, int $days = 30, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->from(AnalyticsRecord::tableName());

        $this->applyDateFilter($query, $days, $startDate, $endDate);
        $this->applySiteFilter($query, $siteId);

        $total = (int) $query->count();
        $botCount = (int) (clone $query)->andWhere(['isRobot' => true])->count();
        $humanCount = $total - $botCount;

        $botPercentage = $total > 0 ? round(($botCount / $total) * 100, 1) : 0;

        // Get top bots
        $topBots = (clone $query)
            ->select(['botName', 'COUNT(*) as count'])
            ->where(['isRobot' => true])
            ->andWhere(['not', ['botName' => null]])
            ->groupBy('botName')
            ->orderBy(['count' => SORT_DESC])
            ->limit(5)
            ->all();

        return [
            'total' => $total,
            'botCount' => $botCount,
            'humanCount' => $humanCount,
            'botPercentage' => $botPercentage,
            'topBots' => $topBots,
            'chart' => [
                'labels' => ['Human Traffic', 'Bot Traffic'],
                'values' => [$humanCount, $botCount],
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
     * @since 5.9.0
     */
    public function getTopCountries(int|array|null $siteId = null, int $days = 30, int $limit = 15, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['country', 'COUNT(*) as count'])
            ->from(AnalyticsRecord::tableName())
            ->where(['not', ['country' => null]])
            ->andWhere(['not', ['country' => '']])
            ->groupBy(['country'])
            ->orderBy(['count' => SORT_DESC])
            ->limit($limit);

        $this->applyDateFilter($query, $days, $startDate, $endDate);
        $this->applySiteFilter($query, $siteId);

        $results = $query->all();
        $total = array_sum(array_column($results, 'count'));

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
     * @since 5.9.0
     */
    public function getTopCities(int|array|null $siteId = null, int $days = 30, int $limit = 15, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = (new Query())
            ->select(['city', 'country', 'COUNT(*) as count'])
            ->from(AnalyticsRecord::tableName())
            ->where(['not', ['city' => null]])
            ->andWhere(['not', ['city' => '']])
            ->groupBy(['city', 'country'])
            ->orderBy(['count' => SORT_DESC])
            ->limit($limit);

        $this->applyDateFilter($query, $days, $startDate, $endDate);
        $this->applySiteFilter($query, $siteId);

        $results = $query->all();
        $total = array_sum(array_column($results, 'count'));

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
     * @since 5.9.0
     */
    public function getLocationFromIp(string $ip): ?array
    {
        // Handle private/local IPs with default location for development
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $this->getDefaultLocation();
        }

        // Use centralized geo lookup from base plugin
        $geoData = $this->lookupGeoIp($ip, $this->getGeoConfig());

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
     * @since 5.9.0
     */
    public function getGeoConfig(): array
    {
        $settings = RedirectManager::$plugin->getSettings();

        return [
            'provider' => $settings->geoProvider ?? 'ip-api.com',
            'apiKey' => $settings->geoApiKey ?? null,
        ];
    }

    /**
     * Get default location for private/local IPs
     *
     * @return array<string, mixed>
     */
    private function getDefaultLocation(): array
    {
        $settings = RedirectManager::$plugin->getSettings();
        $defaultCountry = $settings->defaultCountry ?: (App::env('REDIRECT_MANAGER_DEFAULT_COUNTRY') ?: 'AE');
        $defaultCity = $settings->defaultCity ?: (App::env('REDIRECT_MANAGER_DEFAULT_CITY') ?: 'Dubai');

        // Predefined locations for common cities worldwide
        $locations = [
            'US' => [
                'New York' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'New York', 'region' => 'New York', 'lat' => 40.7128, 'lon' => -74.0060],
                'Los Angeles' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'Los Angeles', 'region' => 'California', 'lat' => 34.0522, 'lon' => -118.2437],
                'Chicago' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'Chicago', 'region' => 'Illinois', 'lat' => 41.8781, 'lon' => -87.6298],
                'San Francisco' => ['countryCode' => 'US', 'country' => 'United States', 'city' => 'San Francisco', 'region' => 'California', 'lat' => 37.7749, 'lon' => -122.4194],
            ],
            'GB' => [
                'London' => ['countryCode' => 'GB', 'country' => 'United Kingdom', 'city' => 'London', 'region' => 'England', 'lat' => 51.5074, 'lon' => -0.1278],
                'Manchester' => ['countryCode' => 'GB', 'country' => 'United Kingdom', 'city' => 'Manchester', 'region' => 'England', 'lat' => 53.4808, 'lon' => -2.2426],
            ],
            'AE' => [
                'Dubai' => ['countryCode' => 'AE', 'country' => 'United Arab Emirates', 'city' => 'Dubai', 'region' => 'Dubai', 'lat' => 25.2048, 'lon' => 55.2708],
                'Abu Dhabi' => ['countryCode' => 'AE', 'country' => 'United Arab Emirates', 'city' => 'Abu Dhabi', 'region' => 'Abu Dhabi', 'lat' => 24.4539, 'lon' => 54.3773],
            ],
            'SA' => [
                'Riyadh' => ['countryCode' => 'SA', 'country' => 'Saudi Arabia', 'city' => 'Riyadh', 'region' => 'Riyadh Province', 'lat' => 24.7136, 'lon' => 46.6753],
                'Jeddah' => ['countryCode' => 'SA', 'country' => 'Saudi Arabia', 'city' => 'Jeddah', 'region' => 'Makkah Province', 'lat' => 21.5433, 'lon' => 39.1728],
            ],
            'DE' => [
                'Berlin' => ['countryCode' => 'DE', 'country' => 'Germany', 'city' => 'Berlin', 'region' => 'Berlin', 'lat' => 52.5200, 'lon' => 13.4050],
                'Munich' => ['countryCode' => 'DE', 'country' => 'Germany', 'city' => 'Munich', 'region' => 'Bavaria', 'lat' => 48.1351, 'lon' => 11.5820],
            ],
            'FR' => [
                'Paris' => ['countryCode' => 'FR', 'country' => 'France', 'city' => 'Paris', 'region' => 'Île-de-France', 'lat' => 48.8566, 'lon' => 2.3522],
            ],
            'CA' => [
                'Toronto' => ['countryCode' => 'CA', 'country' => 'Canada', 'city' => 'Toronto', 'region' => 'Ontario', 'lat' => 43.6532, 'lon' => -79.3832],
                'Vancouver' => ['countryCode' => 'CA', 'country' => 'Canada', 'city' => 'Vancouver', 'region' => 'British Columbia', 'lat' => 49.2827, 'lon' => -123.1207],
            ],
            'AU' => [
                'Sydney' => ['countryCode' => 'AU', 'country' => 'Australia', 'city' => 'Sydney', 'region' => 'New South Wales', 'lat' => -33.8688, 'lon' => 151.2093],
                'Melbourne' => ['countryCode' => 'AU', 'country' => 'Australia', 'city' => 'Melbourne', 'region' => 'Victoria', 'lat' => -37.8136, 'lon' => 144.9631],
            ],
            'JP' => [
                'Tokyo' => ['countryCode' => 'JP', 'country' => 'Japan', 'city' => 'Tokyo', 'region' => 'Tokyo', 'lat' => 35.6762, 'lon' => 139.6503],
            ],
            'SG' => [
                'Singapore' => ['countryCode' => 'SG', 'country' => 'Singapore', 'city' => 'Singapore', 'region' => 'Singapore', 'lat' => 1.3521, 'lon' => 103.8198],
            ],
            'IN' => [
                'Mumbai' => ['countryCode' => 'IN', 'country' => 'India', 'city' => 'Mumbai', 'region' => 'Maharashtra', 'lat' => 19.0760, 'lon' => 72.8777],
                'Delhi' => ['countryCode' => 'IN', 'country' => 'India', 'city' => 'Delhi', 'region' => 'Delhi', 'lat' => 28.7041, 'lon' => 77.1025],
            ],
        ];

        // Return the configured location if it exists
        if (isset($locations[$defaultCountry][$defaultCity])) {
            return $locations[$defaultCountry][$defaultCity];
        }

        // Fallback to Dubai if configuration not found
        return $locations['AE']['Dubai'];
    }
}

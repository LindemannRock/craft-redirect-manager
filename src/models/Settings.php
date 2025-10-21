<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\models;

use Craft;
use craft\base\Model;
use craft\db\Query;
use craft\helpers\Db;
use craft\validators\ArrayValidator;

/**
 * Settings Model
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
class Settings extends Model
{
    /**
     * @var string The public-facing name of the plugin
     */
    public string $pluginName = 'Redirect Manager';

    /**
     * @var bool Controls whether redirects automatically created when entry URIs change
     */
    public bool $autoCreateRedirects = true;

    /**
     * @var string Should the legacy URL be matched by path or full URL
     */
    public string $redirectSrcMatch = 'pathonly';

    /**
     * @var bool Should the query string be stripped from all 404 URLs before evaluation
     */
    public bool $stripQueryString = false;

    /**
     * @var bool Should the query string be preserved and passed to the destination
     */
    public bool $preserveQueryString = false;

    /**
     * @var bool Should no-cache headers be set on redirect responses
     */
    public bool $setNoCacheHeaders = true;

    /**
     * @var bool Should anonymous IP addresses be recorded for 404s
     */
    public bool $recordRemoteIp = true;

    /**
     * @var bool Should query strings be stripped from statistics URLs
     */
    public bool $stripQueryStringFromStats = true;

    /**
     * @var int Maximum number of unique 404 records to retain
     */
    public int $statisticsLimit = 1000;

    /**
     * @var int Number of days to retain statistics (0 = keep forever)
     */
    public int $statisticsRetention = 30;

    /**
     * @var bool Whether statistics should be automatically trimmed
     */
    public bool $autoTrimStatistics = true;

    /**
     * @var int Dashboard refresh interval in seconds
     */
    public int $refreshIntervalSecs = 5;

    /**
     * @var int How many redirects to display in the CP
     */
    public int $redirectsDisplayLimit = 100;

    /**
     * @var int How many statistics to display in the CP
     */
    public int $statisticsDisplayLimit = 100;

    /**
     * @var int Items per page in list views
     */
    public int $itemsPerPage = 100;

    /**
     * @var bool Whether to enable GraphQL endpoint
     */
    public bool $enableApiEndpoint = false;

    /**
     * @var array Regular expressions to exclude URLs from redirect handling
     */
    public array $excludePatterns = [];

    /**
     * @var array Additional HTTP headers to add to redirect responses
     */
    public array $additionalHeaders = [];

    /**
     * @var string Log level for the logging library
     */
    public string $logLevel = 'error';

    /**
     * @var bool Enable redirect caching
     */
    public bool $enableRedirectCache = true;

    /**
     * @var int Redirect cache duration in seconds
     */
    public int $redirectCacheDuration = 3600;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['pluginName', 'string'],
            ['pluginName', 'default', 'value' => 'Redirect Manager'],
            [
                [
                    'autoCreateRedirects',
                    'stripQueryString',
                    'preserveQueryString',
                    'setNoCacheHeaders',
                    'recordRemoteIp',
                    'stripQueryStringFromStats',
                    'autoTrimStatistics',
                    'enableApiEndpoint',
                ],
                'boolean',
            ],
            ['redirectSrcMatch', 'default', 'value' => 'pathonly'],
            ['redirectSrcMatch', 'string'],
            ['redirectSrcMatch', 'in', 'range' => ['pathonly', 'fullurl']],
            ['statisticsLimit', 'integer', 'min' => 1],
            ['statisticsLimit', 'default', 'value' => 1000],
            ['statisticsRetention', 'integer', 'min' => 0],
            ['statisticsRetention', 'default', 'value' => 30],
            ['refreshIntervalSecs', 'integer', 'min' => 0],
            ['refreshIntervalSecs', 'default', 'value' => 5],
            ['redirectsDisplayLimit', 'integer', 'min' => 1],
            ['redirectsDisplayLimit', 'default', 'value' => 100],
            ['statisticsDisplayLimit', 'integer', 'min' => 1],
            ['statisticsDisplayLimit', 'default', 'value' => 100],
            ['itemsPerPage', 'integer', 'min' => 1],
            ['itemsPerPage', 'default', 'value' => 100],
            [
                ['excludePatterns', 'additionalHeaders'],
                ArrayValidator::class,
            ],
            ['logLevel', 'string'],
            ['logLevel', 'in', 'range' => ['debug', 'info', 'warning', 'error']],
            ['logLevel', 'default', 'value' => 'error'],
            ['enableRedirectCache', 'boolean'],
            ['enableRedirectCache', 'default', 'value' => true],
            ['redirectCacheDuration', 'integer', 'min' => 0],
            ['redirectCacheDuration', 'default', 'value' => 3600],
        ];
    }

    /**
     * Load settings from database
     *
     * @param Settings|null $settings Optional existing settings instance
     * @return self
     */
    public static function loadFromDatabase(?Settings $settings = null): self
    {
        if ($settings === null) {
            $settings = new self();
        }

        // Load from database
        try {
            $row = (new Query())
                ->from('{{%redirectmanager_settings}}')
                ->where(['id' => 1])
                ->one();
        } catch (\Exception $e) {
            Craft::error('Failed to load settings from database', 'redirect-manager', ['error' => $e->getMessage()]);
            return $settings;
        }

        if ($row) {

            // Remove system fields
            unset($row['id'], $row['dateCreated'], $row['dateUpdated'], $row['uid']);

            // Convert boolean fields
            $booleanFields = [
                'autoCreateRedirects',
                'stripQueryString',
                'preserveQueryString',
                'setNoCacheHeaders',
                'recordRemoteIp',
                'stripQueryStringFromStats',
                'autoTrimStatistics',
                'enableApiEndpoint',
                'enableRedirectCache',
            ];

            foreach ($booleanFields as $field) {
                if (isset($row[$field])) {
                    $row[$field] = (bool) $row[$field];
                }
            }

            // Convert integer fields
            $integerFields = [
                'statisticsLimit',
                'statisticsRetention',
                'refreshIntervalSecs',
                'redirectsDisplayLimit',
                'statisticsDisplayLimit',
                'itemsPerPage',
                'redirectCacheDuration',
            ];

            foreach ($integerFields as $field) {
                if (isset($row[$field])) {
                    $row[$field] = (int) $row[$field];
                }
            }

            // Handle JSON array fields
            if (isset($row['excludePatterns'])) {
                $row['excludePatterns'] = !empty($row['excludePatterns']) ? json_decode($row['excludePatterns'], true) : [];
            }
            if (isset($row['additionalHeaders'])) {
                $row['additionalHeaders'] = !empty($row['additionalHeaders']) ? json_decode($row['additionalHeaders'], true) : [];
            }

            // Set attributes from database
            $settings->setAttributes($row, false);
        }

        return $settings;
    }

    /**
     * Save settings to database
     *
     * @return bool
     */
    public function saveToDatabase(): bool
    {
        if (!$this->validate()) {
            Craft::error('Settings validation failed', 'redirect-manager', ['errors' => $this->getErrors()]);
            return false;
        }

        $db = Craft::$app->getDb();
        $attributes = $this->getAttributes();

        // Handle JSON array serialization
        if (isset($attributes['excludePatterns'])) {
            $attributes['excludePatterns'] = json_encode($attributes['excludePatterns']);
        }
        if (isset($attributes['additionalHeaders'])) {
            $attributes['additionalHeaders'] = json_encode($attributes['additionalHeaders']);
        }

        // Update timestamp
        $attributes['dateUpdated'] = Db::prepareDateForDb(new \DateTime());

        // Update existing settings (always row id=1)
        try {
            $result = $db->createCommand()
                ->update('{{%redirectmanager_settings}}', $attributes, ['id' => 1])
                ->execute();

            if ($result !== false) {
                Craft::info('Settings saved successfully to database', 'redirect-manager');
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Craft::error('Failed to save settings', 'redirect-manager', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if a setting is overridden by config file
     *
     * @param string $attribute
     * @return bool
     */
    public function isOverriddenByConfig(string $attribute): bool
    {
        $configPath = Craft::$app->getPath()->getConfigPath() . '/redirect-manager.php';

        if (!file_exists($configPath)) {
            return false;
        }

        // Load the raw config file
        $rawConfig = require $configPath;

        // Check for the attribute in the config
        if (array_key_exists($attribute, $rawConfig)) {
            return true;
        }

        // Check environment-specific configs
        $env = Craft::$app->getConfig()->env;
        if ($env && is_array($rawConfig[$env] ?? null) && array_key_exists($attribute, $rawConfig[$env])) {
            return true;
        }

        // Check wildcard config
        if (is_array($rawConfig['*'] ?? null) && array_key_exists($attribute, $rawConfig['*'])) {
            return true;
        }

        return false;
    }
}

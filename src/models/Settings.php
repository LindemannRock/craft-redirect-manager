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
use craft\behaviors\EnvAttributeParserBehavior;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\Db;
use craft\validators\ArrayValidator;
use lindemannrock\logginglibrary\traits\LoggingTrait;

/**
 * Settings Model
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class Settings extends Model
{
    use LoggingTrait;

    /**
     * @var string The public-facing name of the plugin
     */
    public string $pluginName = 'Redirect Manager';

    /**
     * @var bool Controls whether redirects automatically created when entry URIs change
     */
    public bool $autoCreateRedirects = true;

    /**
     * @var int Time window in minutes for detecting immediate undo (30, 60, 120, 240)
     */
    public int $undoWindowMinutes = 60;

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
     * @var bool Enable analytics tracking (master switch - controls IP tracking, device detection, geo detection)
     */
    public bool $enableAnalytics = true;

    /**
     * @var bool Should IP addresses be anonymized before hashing
     */
    public bool $anonymizeIpAddress = false;

    /**
     * @var bool Enable geographic detection from IP addresses
     */
    public bool $enableGeoDetection = false;

    /**
     * @var string|null Default country for local development (when IP is private)
     */
    public ?string $defaultCountry = null;

    /**
     * @var string|null Default city for local development (when IP is private)
     */
    public ?string $defaultCity = null;

    /**
     * @var bool Cache device detection results
     */
    public bool $cacheDeviceDetection = true;

    /**
     * @var int Device detection cache duration in seconds (1 hour)
     */
    public int $deviceDetectionCacheDuration = 3600;

    /**
     * @var string|null IP hash salt from .env
     */
    public ?string $ipHashSalt = null;

    /**
     * @var bool Should query strings be stripped from analytics URLs
     */
    public bool $stripQueryStringFromStats = true;

    /**
     * @var int Maximum number of unique 404 records to retain
     */
    public int $analyticsLimit = 1000;

    /**
     * @var int Number of days to retain analytics (0 = keep forever)
     */
    public int $analyticsRetention = 30;

    /**
     * @var bool Whether analytics should be automatically trimmed
     */
    public bool $autoTrimAnalytics = true;

    /**
     * @var int Dashboard refresh interval in seconds
     */
    public int $refreshIntervalSecs = 5;

    /**
     * @var int How many redirects to display in the CP
     */
    public int $redirectsDisplayLimit = 100;

    /**
     * @var int How many analytics to display in the CP
     */
    public int $analyticsDisplayLimit = 100;

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
     * @var string Local filesystem path for storing import backups
     */
    public string $backupPath = '@storage/redirect-manager/backups/imports';

    /**
     * @var string|null Optional asset volume UID for storing backups
     */
    public ?string $backupVolumeUid = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('redirect-manager');

        // Fallback to .env if ipHashSalt not set by config file
        if ($this->ipHashSalt === null) {
            $this->ipHashSalt = App::env('REDIRECT_MANAGER_IP_SALT');
        }

        // Load default location from .env if not set by config file
        if ($this->defaultCountry === null) {
            $this->defaultCountry = App::env('REDIRECT_MANAGER_DEFAULT_COUNTRY');
        }
        if ($this->defaultCity === null) {
            $this->defaultCity = App::env('REDIRECT_MANAGER_DEFAULT_CITY');
        }
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['backupPath'],
            ],
        ];
    }

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
                    'enableAnalytics',
                    'anonymizeIpAddress',
                    'enableGeoDetection',
                    'stripQueryStringFromStats',
                    'autoTrimAnalytics',
                    'enableApiEndpoint',
                ],
                'boolean',
            ],
            ['redirectSrcMatch', 'default', 'value' => 'pathonly'],
            ['redirectSrcMatch', 'string'],
            ['redirectSrcMatch', 'in', 'range' => ['pathonly', 'fullurl']],
            ['analyticsLimit', 'integer', 'min' => 1],
            ['analyticsLimit', 'default', 'value' => 1000],
            ['analyticsRetention', 'integer', 'min' => 0],
            ['analyticsRetention', 'default', 'value' => 30],
            ['refreshIntervalSecs', 'integer', 'min' => 0],
            ['refreshIntervalSecs', 'default', 'value' => 5],
            ['redirectsDisplayLimit', 'integer', 'min' => 1],
            ['redirectsDisplayLimit', 'default', 'value' => 100],
            ['analyticsDisplayLimit', 'integer', 'min' => 1],
            ['analyticsDisplayLimit', 'default', 'value' => 100],
            ['itemsPerPage', 'integer', 'min' => 10, 'max' => 500],
            ['itemsPerPage', 'default', 'value' => 100],
            ['undoWindowMinutes', 'integer'],
            ['undoWindowMinutes', 'in', 'range' => [0, 30, 60, 120, 240]], // 0 = disabled (no undo detection)
            ['undoWindowMinutes', 'default', 'value' => 60],
            [
                ['excludePatterns', 'additionalHeaders'],
                ArrayValidator::class,
            ],
            [['logLevel'], 'in', 'range' => ['debug', 'info', 'warning', 'error']],
            [['logLevel'], 'validateLogLevel'],
            ['enableRedirectCache', 'boolean'],
            ['enableRedirectCache', 'default', 'value' => true],
            ['redirectCacheDuration', 'integer', 'min' => 0],
            ['redirectCacheDuration', 'default', 'value' => 3600],
            ['backupPath', 'required'],
            ['backupPath', 'string'],
            ['backupPath', 'validateBackupPath'],
            ['backupVolumeUid', 'string'],
        ];
    }

    /**
     * Validate log level - debug requires devMode
     */
    public function validateLogLevel($attribute, $params, $validator)
    {
        $logLevel = $this->$attribute;

        // Reset session warning when devMode is true - allows warning to show again if devMode changes
        if (Craft::$app->getConfig()->getGeneral()->devMode && !Craft::$app->getRequest()->getIsConsoleRequest()) {
            Craft::$app->getSession()->remove('rm_debug_config_warning');
        }

        // Debug level is only allowed when devMode is enabled
        if ($logLevel === 'debug' && !Craft::$app->getConfig()->getGeneral()->devMode) {
            $this->$attribute = 'info';

            if ($this->isOverriddenByConfig('logLevel')) {
                if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
                    if (Craft::$app->getSession()->get('rm_debug_config_warning') === null) {
                        $this->logWarning('Log level "debug" from config file changed to "info" because devMode is disabled', [
                            'configFile' => 'config/redirect-manager.php',
                        ]);
                        Craft::$app->getSession()->set('rm_debug_config_warning', true);
                    }
                } else {
                    $this->logWarning('Log level "debug" from config file changed to "info" because devMode is disabled', [
                        'configFile' => 'config/redirect-manager.php',
                    ]);
                }
            } else {
                $this->logWarning('Log level automatically changed from "debug" to "info" because devMode is disabled');
                $this->saveToDatabase();
            }
        }
    }

    /**
     * Validate backup path - only allow secure aliases
     */
    public function validateBackupPath($attribute, $params, $validator)
    {
        $path = $this->$attribute;

        // Check for directory traversal attempts
        if (strpos($path, '..') !== false) {
            $this->addError($attribute, Craft::t('redirect-manager', 'Backup path cannot contain directory traversal sequences (..)'));
            return;
        }

        // If path starts with @, validate against allowed aliases (unresolved)
        if (str_starts_with($path, '@')) {
            $allowedAliases = ['@root', '@storage'];
            $hasValidAlias = false;

            foreach ($allowedAliases as $alias) {
                if (str_starts_with($path, $alias)) {
                    $hasValidAlias = true;
                    break;
                }
            }

            if (!$hasValidAlias) {
                $this->addError(
                    $attribute,
                    Craft::t('redirect-manager', 'Backup path must start with @root or @storage (secure locations only, never web-accessible)')
                );
                return;
            }
        }

        // Resolve the alias to check actual path
        try {
            $resolvedPath = Craft::getAlias($path);
            $webroot = Craft::getAlias('@webroot');

            // Prevent backups in web-accessible directory
            if (str_starts_with($resolvedPath, $webroot)) {
                $this->addError(
                    $attribute,
                    Craft::t('redirect-manager', 'Backup path cannot be in a web-accessible directory (@webroot)')
                );
                return;
            }
        } catch (\Exception $e) {
            $this->addError($attribute, Craft::t('redirect-manager', 'Invalid backup path: {error}', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Get the resolved backup path
     *
     * @return string
     */
    public function getBackupPath(): string
    {
        // If a volume is selected, use its path
        if ($this->backupVolumeUid) {
            $volume = Craft::$app->getVolumes()->getVolumeByUid($this->backupVolumeUid);
            if ($volume) {
                $fs = $volume->getFs();
                if (property_exists($fs, 'path')) {
                    $path = App::env($fs->path);
                    return rtrim($path, '/') . '/redirect-manager/backups/imports';
                }
                return "Volume: {$volume->name} / redirect-manager/backups/imports";
            }
        }

        // Fall back to local storage with safety checks
        $path = Craft::getAlias($this->backupPath);

        // Additional safety check: prevent exact root directory match
        $rootPath = Craft::getAlias('@root');
        if ($path === $rootPath || $path === '/' || $path === '') {
            // Force a safe default path
            $path = Craft::getAlias('@storage/redirect-manager/backups/imports');
            $this->logWarning('Backup path was pointing to root directory. Using safe default');
        }

        return $path;
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
            $settings->logError('Failed to load settings from database', ['error' => $e->getMessage()]);
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
                'enableAnalytics',
                'anonymizeIpAddress',
                'enableGeoDetection',
                'stripQueryStringFromStats',
                'autoTrimAnalytics',
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
                'analyticsLimit',
                'analyticsRetention',
                'refreshIntervalSecs',
                'redirectsDisplayLimit',
                'analyticsDisplayLimit',
                'itemsPerPage',
                'redirectCacheDuration',
                'undoWindowMinutes',
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
            $this->logError('Settings validation failed', ['errors' => $this->getErrors()]);
            return false;
        }

        $db = Craft::$app->getDb();
        $attributes = $this->getAttributes();

        // Exclude config-only attributes that shouldn't be saved to database
        unset($attributes['ipHashSalt'], $attributes['defaultCountry'], $attributes['defaultCity']); // These come from .env/config, not database

        // Remove attributes that are overridden by config file
        foreach (array_keys($attributes) as $attribute) {
            if ($this->isOverriddenByConfig($attribute)) {
                unset($attributes[$attribute]);
            }
        }

        // Handle JSON array serialization
        if (isset($attributes['excludePatterns'])) {
            $attributes['excludePatterns'] = json_encode($attributes['excludePatterns']);
        }
        if (isset($attributes['additionalHeaders'])) {
            $attributes['additionalHeaders'] = json_encode($attributes['additionalHeaders']);
        }

        // Update timestamp
        $attributes['dateUpdated'] = Db::prepareDateForDb(new \DateTime());

        // Log what we're trying to save
        $this->logDebug('Attempting to save settings', ['attributes' => array_keys($attributes)]);

        // Update existing settings (always row id=1)
        try {
            $db->createCommand()
                ->update('{{%redirectmanager_settings}}', $attributes, ['id' => 1])
                ->execute();

            $this->logInfo('Settings saved successfully to database');
            return true;
        } catch (\Exception $e) {
            $this->logError('Failed to save ' . $this->getFullName() . ' settings', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Also check if column exists
            $columnsQuery = $db->createCommand("SHOW COLUMNS FROM {{%redirectmanager_settings}} LIKE 'undoWindowMinutes'")->queryAll();
            $this->logError('Column check', ['columnExists' => !empty($columnsQuery)]);

            return false;
        }
    }

    /**
     * Check if a setting is overridden by config file
     * Supports dot notation for nested settings like: excludePatterns.0
     *
     * @param string $attribute The setting attribute name or dot-notation path
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

        // Handle dot notation for nested config
        if (str_contains($attribute, '.')) {
            $parts = explode('.', $attribute);
            $current = $rawConfig;

            foreach ($parts as $part) {
                if (!is_array($current) || !array_key_exists($part, $current)) {
                    return false;
                }
                $current = $current[$part];
            }

            return true;
        }

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

    /**
     * Get display name (singular, without "Manager")
     *
     * Strips "Manager" and singularizes the plugin name for use in UI labels.
     * E.g., "Redirect Manager" → "Redirect", "Redirects" → "Redirect"
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        // Strip "Manager" or "manager" from the name
        $name = str_replace([' Manager', ' manager'], '', $this->pluginName);

        // Singularize by removing trailing 's' if present
        $singular = preg_replace('/s$/', '', $name) ?: $name;

        return $singular;
    }

    /**
     * Get full plugin name (as configured, with "Manager" if present)
     *
     * Returns the plugin name exactly as configured in settings.
     * E.g., "Redirect Manager", "Redirects", etc.
     *
     * @return string
     */
    public function getFullName(): string
    {
        return $this->pluginName;
    }

    /**
     * Get plural display name (without "Manager")
     *
     * Strips "Manager" from the plugin name but keeps plural form.
     * E.g., "Redirect Manager" → "Redirects", "Redirects" → "Redirects"
     *
     * @return string
     */
    public function getPluralDisplayName(): string
    {
        // Strip "Manager" or "manager" from the name
        return str_replace([' Manager', ' manager'], '', $this->pluginName);
    }

    /**
     * Get lowercase display name (singular, without "Manager")
     *
     * Lowercase version of getDisplayName() for use in messages, handles, etc.
     * E.g., "Redirect Manager" → "redirect", "Redirects" → "redirect"
     *
     * @return string
     */
    public function getLowerDisplayName(): string
    {
        return strtolower($this->getDisplayName());
    }

    /**
     * Get lowercase plural display name (without "Manager")
     *
     * Lowercase version of getPluralDisplayName() for use in messages, handles, etc.
     * E.g., "Redirect Manager" → "redirects", "Redirects" → "redirects"
     *
     * @return string
     */
    public function getPluralLowerDisplayName(): string
    {
        return strtolower($this->getPluralDisplayName());
    }
}

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
use craft\helpers\App;
use craft\validators\ArrayValidator;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\base\helpers\StoragePathHelper;
use lindemannrock\base\traits\DateFormatSettingsTrait;
use lindemannrock\base\traits\DateRangeSettingsTrait;
use lindemannrock\base\traits\ExportFormatSettingsTrait;
use lindemannrock\base\traits\GeoSettingsTrait;
use lindemannrock\base\traits\ItemsPerPageSettingsTrait;
use lindemannrock\base\traits\LogLevelSettingsTrait;
use lindemannrock\base\traits\PluginNameSettingsTrait;
use lindemannrock\base\traits\SettingsConfigTrait;
use lindemannrock\base\traits\SettingsDisplayNameTrait;
use lindemannrock\base\traits\SettingsPersistenceTrait;
use lindemannrock\base\validators\StoragePathValidator;
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
    use DateFormatSettingsTrait;
    use DateRangeSettingsTrait;
    use ExportFormatSettingsTrait;
    use GeoSettingsTrait;
    use ItemsPerPageSettingsTrait;
    use LogLevelSettingsTrait;
    use LoggingTrait;
    use PluginNameSettingsTrait;
    use SettingsConfigTrait;
    use SettingsDisplayNameTrait;
    use SettingsPersistenceTrait;

    /**
     * Backup schedule options exposed by Redirect Manager.
     */
    private const BACKUP_SCHEDULE_OPTIONS = [
        'disabled',
        'daily',
        'weekly',
        'monthly',
    ];

    /**
     * @var string The name of the plugin as it appears in the Control Panel menu
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
     * @var string Geo IP lookup provider (ip-api.com, ipapi.co, ipinfo.io)
     */
    public string $geoProvider = 'ip-api.com';

    /**
     * @var string|null API key for paid provider tiers (enables HTTPS for ip-api.com)
     */
    public ?string $geoApiKey = null;

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
     * @var int|null Dashboard refresh interval in seconds (null = disabled)
     */
    public ?int $refreshIntervalSecs = null;

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
     * @var bool Enable redirect caching
     */
    public bool $enableRedirectCache = true;

    /**
     * @var int Redirect cache duration in seconds
     */
    public int $redirectCacheDuration = 3600;

    /**
     * @var string Cache storage method (file or redis)
     */
    public string $cacheStorageMethod = 'file';

    /**
     * @var string Local filesystem path for storing import backups
     */
    public string $backupPath = '@storage/redirect-manager/backups';

    /**
     * @var string|null Optional asset volume UID for storing backups
     */
    public ?string $backupVolumeUid = null;

    /**
     * @var bool Whether to enable automatic backups
     * @since 5.23.0
     */
    public bool $backupEnabled = true;

    /**
     * @var bool Whether to create a backup before importing
     * @since 5.23.0
     */
    public bool $backupOnImport = true;

    /**
     * @var string Backup schedule (disabled, daily, weekly, monthly)
     * @since 5.23.0
     */
    public string $backupSchedule = 'disabled';

    /**
     * @var int Number of days to keep automatic backups (0 = keep forever)
     * @since 5.23.0
     */
    public int $backupRetentionDays = 30;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(static::pluginHandle());

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
     * Return the effective backup schedule, normalizing old pre-release
     * `manual` values to the canonical disabled schedule.
     *
     * @since 5.32.0
     */
    public function getEffectiveBackupSchedule(): string
    {
        if ($this->backupSchedule === 'manual') {
            return 'disabled';
        }

        if (!in_array($this->backupSchedule, self::BACKUP_SCHEDULE_OPTIONS, true)) {
            return 'disabled';
        }

        return $this->backupSchedule;
    }

    /**
     * Get backup schedule options for settings dropdowns.
     *
     * @return array<array{value: string, label: string}>
     * @since 5.32.0
     */
    public function getBackupScheduleOptions(): array
    {
        return ScheduleHelper::getOptions(self::BACKUP_SCHEDULE_OPTIONS);
    }

    // =========================================================================
    // TRAIT CONFIGURATION (Required by base traits)
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected static function tableName(): string
    {
        return 'redirectmanager_settings';
    }

    /**
     * @inheritdoc
     */
    protected static function pluginHandle(): string
    {
        return 'redirect-manager';
    }

    /**
     * @inheritdoc
     */
    protected static function booleanFields(): array
    {
        return [
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
            'cacheDeviceDetection',
            'backupEnabled',
            'backupOnImport',
            'showSeconds',
            'exportsCsv',
            'exportsJson',
            'exportsExcel',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function integerFields(): array
    {
        return [
            'analyticsLimit',
            'analyticsRetention',
            'refreshIntervalSecs',
            'itemsPerPage',
            'redirectCacheDuration',
            'undoWindowMinutes',
            'deviceDetectionCacheDuration',
            'backupRetentionDays',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function jsonFields(): array
    {
        return [
            'excludePatterns',
            'additionalHeaders',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function excludeFromSave(): array
    {
        return [
            'ipHashSalt',
            'defaultCountry',
            'defaultCity',
        ];
    }

    // =========================================================================
    // BEHAVIORS & VALIDATION
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return array_merge([
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
                    'backupEnabled',
                    'backupOnImport',
                ],
                'boolean',
            ],
            ['redirectSrcMatch', 'default', 'value' => 'pathonly'],
            ['redirectSrcMatch', 'string'],
            ['redirectSrcMatch', 'in', 'range' => ['pathonly', 'fullurl']],
            ['analyticsLimit', 'required'],
            ['analyticsLimit', 'integer', 'min' => 1, 'max' => 100000],
            ['analyticsLimit', 'default', 'value' => 1000],
            ['analyticsRetention', 'required'],
            ['analyticsRetention', 'integer', 'min' => 0, 'max' => 3650],
            ['analyticsRetention', 'default', 'value' => 30],
            ['refreshIntervalSecs', 'integer', 'min' => 0, 'skipOnEmpty' => true],
            ['refreshIntervalSecs', 'default', 'value' => null],
            ['undoWindowMinutes', 'integer'],
            ['undoWindowMinutes', 'in', 'range' => [0, 30, 60, 120, 240]], // 0 = unlimited window (always undo, no time limit)
            ['undoWindowMinutes', 'default', 'value' => 60],
            ['deviceDetectionCacheDuration', 'integer', 'min' => 60, 'max' => 604800],
            ['deviceDetectionCacheDuration', 'default', 'value' => 3600],
            [
                ['excludePatterns', 'additionalHeaders'],
                ArrayValidator::class,
            ],
            ['enableRedirectCache', 'boolean'],
            ['enableRedirectCache', 'default', 'value' => true],
            ['redirectCacheDuration', 'integer', 'min' => 60, 'max' => 86400],
            ['redirectCacheDuration', 'default', 'value' => 3600],
            [['cacheStorageMethod'], 'in', 'range' => ['file', 'redis']],
            ['backupPath', 'required'],
            ['backupPath', 'string'],
            [
                'backupPath',
                StoragePathValidator::class,
                'translationCategory' => 'redirect-manager',
                'allowedAliases' => ['@storage', '@root'],
                'preventWebroot' => true,
                'requireAlias' => true,
            ],
            ['backupPath', 'validateBackupPathRootSubfolder'],
            ['backupVolumeUid', 'string'],
            ['backupRetentionDays', 'integer', 'min' => 0, 'max' => 365],
            ['backupSchedule', 'in', 'range' => array_merge(ScheduleHelper::getValidValues(self::BACKUP_SCHEDULE_OPTIONS), ['manual'])],
        ], $this->pluginNameSettingsRules(), $this->logLevelSettingsRules(), $this->dateFormatSettingsRules(), $this->dateRangeSettingsRules(), $this->exportFormatSettingsRules(), $this->geoSettingsRules(), $this->itemsPerPageSettingsRules());
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge([
            // Redirect behavior
            'autoCreateRedirects' => Craft::t('redirect-manager', 'Auto Create Redirects'),
            'undoWindowMinutes' => Craft::t('redirect-manager', 'Undo Window'),
            'redirectSrcMatch' => Craft::t('redirect-manager', 'Default Source Match Mode'),
            'stripQueryString' => Craft::t('redirect-manager', 'Strip Query String'),
            'preserveQueryString' => Craft::t('redirect-manager', 'Preserve Query String'),
            'setNoCacheHeaders' => Craft::t('redirect-manager', 'Set No-Cache Headers'),
            // Analytics + Geo (geoProvider/geoApiKey live on GeoSettingsTrait)
            'enableAnalytics' => Craft::t('redirect-manager', 'Enable Analytics'),
            'anonymizeIpAddress' => Craft::t('redirect-manager', 'Anonymize IP Addresses'),
            'enableGeoDetection' => Craft::t('redirect-manager', 'Enable Geographic Detection'),
            'cacheDeviceDetection' => Craft::t('redirect-manager', 'Cache Device Detection'),
            'deviceDetectionCacheDuration' => Craft::t('redirect-manager', 'Device Detection Cache Duration'),
            'stripQueryStringFromStats' => Craft::t('redirect-manager', 'Strip Query String From Stats'),
            'analyticsLimit' => Craft::t('redirect-manager', 'Analytics Limit'),
            'analyticsRetention' => Craft::t('redirect-manager', 'Analytics Retention (Days)'),
            'autoTrimAnalytics' => Craft::t('redirect-manager', 'Auto Trim Analytics'),
            'refreshIntervalSecs' => Craft::t('redirect-manager', 'Dashboard Refresh Interval'),
            // API endpoint
            'enableApiEndpoint' => Craft::t('redirect-manager', 'Enable API Endpoint'),
            'excludePatterns' => Craft::t('redirect-manager', 'Exclude Patterns'),
            'additionalHeaders' => Craft::t('redirect-manager', 'Additional Headers'),
            // Redirect cache
            'enableRedirectCache' => Craft::t('redirect-manager', 'Enable Redirect Cache'),
            'redirectCacheDuration' => Craft::t('redirect-manager', 'Redirect Cache Duration'),
            'cacheStorageMethod' => Craft::t('redirect-manager', 'Cache Storage Method'),
            // Backups
            'backupPath' => Craft::t('redirect-manager', 'Custom Backup Path'),
            'backupVolumeUid' => Craft::t('redirect-manager', 'Backup Storage Volume'),
            'backupEnabled' => Craft::t('redirect-manager', 'Enable Backups'),
            'backupOnImport' => Craft::t('redirect-manager', 'Backup Before Import'),
            'backupSchedule' => Craft::t('redirect-manager', 'Backup Schedule'),
            'backupRetentionDays' => Craft::t('redirect-manager', 'Retention Period'),
        ],
            $this->pluginNameSettingsLabel(),
            $this->logLevelSettingsLabel(),
            $this->dateFormatSettingsLabels(),
            $this->dateRangeSettingsLabel(),
            $this->exportFormatSettingsLabels(),
            $this->geoSettingsLabel(),
            $this->itemsPerPageSettingsLabel(),
        );
    }

    /**
     * Get the resolved backup path (base path without subdirectories)
     *
     * @return string
     * @since 5.23.0
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
                    return rtrim($path, '/') . '/redirect-manager/backups';
                }
                return "Volume: {$volume->name} / redirect-manager/backups";
            }
        }

        // Fall back to local storage with safety checks
        try {
            $path = StoragePathHelper::resolve($this->backupPath);
        } catch (\Throwable $e) {
            $this->logWarning('Backup path could not be resolved. Using safe default.', [
                'path' => $this->backupPath,
                'error' => $e->getMessage(),
            ]);

            return Craft::getAlias('@storage/redirect-manager/backups');
        }

        // Additional safety check: prevent exact root directory match
        $rootPath = Craft::getAlias('@root');
        if ($path === $rootPath || $path === '/' || $path === '') {
            // Force a safe default path
            $path = Craft::getAlias('@storage/redirect-manager/backups');
            $this->logWarning('Backup path was pointing to root directory. Using safe default');
        }

        return $path;
    }

    /**
     * Require a subfolder when using @root for backupPath.
     */
    public function validateBackupPathRootSubfolder(string $attribute): void
    {
        $value = trim((string)$this->$attribute);
        if ($value === '') {
            return;
        }

        $normalized = rtrim($value, '/\\');
        if (strcasecmp($normalized, '@root') === 0) {
            $this->addError(
                $attribute,
                Craft::t('redirect-manager', 'When using @root, include a subfolder (for example: @root/backups/redirect-manager).')
            );
        }
    }
}

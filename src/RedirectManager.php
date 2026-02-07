<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * Intelligent redirect management and 404 handling
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\ElementEvent;
use craft\events\ExceptionEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\ErrorHandler;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use lindemannrock\base\helpers\ColorHelper;
use lindemannrock\base\helpers\CpNavHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\LoggingLibrary;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\redirectmanager\jobs\CreateBackupJob;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\services\AnalyticsService;
use lindemannrock\redirectmanager\services\BackupService;
use lindemannrock\redirectmanager\services\DeviceDetectionService;
use lindemannrock\redirectmanager\services\MatchingService;
use lindemannrock\redirectmanager\services\RedirectsService;
use lindemannrock\redirectmanager\utilities\RedirectManagerUtility;
use lindemannrock\redirectmanager\variables\RedirectManagerVariable;
use lindemannrock\redirectmanager\widgets\AnalyticsSummaryWidget;
use lindemannrock\redirectmanager\widgets\Unhandled404sWidget;
use yii\base\Event;

/**
 * Redirect Manager Plugin
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 *
 * @property-read RedirectsService $redirects
 * @property-read AnalyticsService $analytics
 * @property-read MatchingService $matching
 * @property-read DeviceDetectionService $deviceDetection
 * @property-read BackupService $backup
 * @property-read Settings $settings
 * @method Settings getSettings()
 */
class RedirectManager extends Plugin
{
    use LoggingTrait;

    /**
     * @var RedirectManager|null Singleton plugin instance
     */
    public static ?RedirectManager $plugin = null;

    /**
     * @var string Plugin schema version for migrations
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool Whether the plugin exposes a control panel settings page
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool Whether the plugin registers a control panel section
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Bootstrap base module (logging + Twig extension + colors)
        PluginHelper::bootstrap(
            $this,
            'redirectHelper',
            ['redirectManager:viewSystemLogs'],
            ['redirectManager:downloadSystemLogs'],
            [
                'colorSets' => [
                    'requestType' => [
                        'normal' => ColorHelper::getPaletteColor('blue'),
                        'bot' => ColorHelper::getPaletteColor('amber'),
                        'probe' => ColorHelper::getPaletteColor('red'),
                    ],
                    'matchType' => [
                        'exact' => ColorHelper::getPaletteColor('indigo'),
                        'regex' => ColorHelper::getPaletteColor('pink'),
                        'wildcard' => ColorHelper::getPaletteColor('teal'),
                        'prefix' => ColorHelper::getPaletteColor('amber'),
                    ],
                    'creationType' => [
                        'manual' => ColorHelper::getPaletteColor('orange'),
                        'entry-change' => ColorHelper::getPaletteColor('blue'),
                    ],
                ],
            ]
        );
        PluginHelper::applyPluginNameFromConfig($this);

        // Register services
        $this->setComponents([
            'redirects' => RedirectsService::class,
            'analytics' => AnalyticsService::class,
            'matching' => MatchingService::class,
            'deviceDetection' => DeviceDetectionService::class,
            'backup' => BackupService::class,
        ]);

        // Schedule analytics cleanup if retention is enabled
        $this->scheduleAnalyticsCleanup();
        $this->scheduleBackupJob();

        // Register variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('redirectManager', RedirectManagerVariable::class);
            }
        );

        // Register CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, $this->getCpUrlRules());
            }
        );

        // Register permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $settings = $this->getSettings();
                $event->permissions[] = [
                    'heading' => $settings->getFullName(),
                    'permissions' => $this->getPluginPermissions($settings),
                ];
            }
        );

        // Register dashboard widgets
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = AnalyticsSummaryWidget::class;
                $event->types[] = Unhandled404sWidget::class;
            }
        );

        // Register utilities
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = RedirectManagerUtility::class;
            }
        );

        // Register cache clearing options
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function(RegisterCacheOptionsEvent $event) {
                // Only show cache option if user has permission to clear cache
                if (!Craft::$app->getUser()->checkPermission('redirectManager:clearCache')) {
                    return;
                }

                $settings = $this->getSettings();
                $displayName = $settings->getDisplayName();

                $event->options[] = [
                    'key' => 'redirect-manager-cache',
                    'label' => Craft::t('redirect-manager', '{displayName} caches', ['displayName' => $displayName]),
                    'action' => [$this->redirects, 'invalidateCaches'],
                ];
            }
        );

        // Install event listeners for 404 handling and entry changes
        $this->installEventListeners();

        // DO NOT log in init() - it's called on every request
    }

    /**
     * @inheritdoc
     */
    public function setSettings(array|Model $settings): void
    {
        $oldSettings = $this->getSettings();
        parent::setSettings($settings);

        if ($settings instanceof Settings) {
            $settings->saveToDatabase();

            if ($oldSettings->backupEnabled !== $settings->backupEnabled ||
                $oldSettings->backupSchedule !== $settings->backupSchedule
            ) {
                $this->handleBackupScheduleChange($settings);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $user = Craft::$app->getUser();

        if ($item) {
            $settings = $this->getSettings();

            $item['label'] = $settings->getFullName();
            $item['icon'] = '@appicons/arrows-turn-right.svg';

            $sections = $this->getCpSections($settings);
            $item['subnav'] = CpNavHelper::buildSubnav($user, $settings, $sections);

            // Add logs section using the logging library
            if (PluginHelper::isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'redirectManager:viewSystemLogs',
                ]);
            }

            // Hide from nav if no accessible subnav items
            if (empty($item['subnav'])) {
                return null;
            }
        }

        return $item;
    }

    /**
     * Get CP sections for nav + default route resolution
     *
     * @param Settings $settings
     * @param bool $includeDashboard
     * @param bool $includeLogs
     * @return array
     * @since 5.24.0
     */
    public function getCpSections(Settings $settings, bool $includeDashboard = true, bool $includeLogs = false): array
    {
        $sections = [];

        if ($includeDashboard) {
            $sections[] = [
                'key' => 'dashboard',
                'label' => Craft::t('redirect-manager', 'Dashboard'),
                'url' => 'redirect-manager',
                'permissionsAll' => ['redirectManager:viewAnalytics'],
            ];
        }

        $sections[] = [
            'key' => 'redirects',
            'label' => Craft::t('redirect-manager', 'Redirects'),
            'url' => 'redirect-manager/redirects',
            'permissionsAny' => [
                'redirectManager:viewRedirects',
                'redirectManager:createRedirects',
                'redirectManager:editRedirects',
                'redirectManager:deleteRedirects',
            ],
        ];

        $sections[] = [
            'key' => 'import-export',
            'label' => Craft::t('redirect-manager', 'Import/Export'),
            'url' => 'redirect-manager/import-export',
            'permissionsAny' => [
                'redirectManager:manageImportExport',
                'redirectManager:importRedirects',
                'redirectManager:exportRedirects',
                'redirectManager:viewImportHistory',
            ],
        ];

        $sections[] = [
            'key' => 'backups',
            'label' => Craft::t('redirect-manager', 'Backups'),
            'url' => 'redirect-manager/backups',
            'permissionsAll' => ['redirectManager:manageBackups'],
            'settingsFlag' => 'backupEnabled',
        ];

        $sections[] = [
            'key' => 'analytics',
            'label' => Craft::t('redirect-manager', 'Analytics'),
            'url' => 'redirect-manager/analytics',
            'permissionsAll' => ['redirectManager:viewAnalytics'],
            'settingsFlag' => 'enableAnalytics',
        ];

        if ($includeLogs) {
            $sections[] = [
                'key' => 'logs',
                'label' => Craft::t('redirect-manager', 'Logs'),
                'url' => 'redirect-manager/logs',
                'permissionsAll' => ['redirectManager:viewSystemLogs'],
                'when' => fn() => PluginHelper::isPluginEnabled('logging-library'),
            ];
        }

        $sections[] = [
            'key' => 'settings',
            'label' => Craft::t('redirect-manager', 'Settings'),
            'url' => 'redirect-manager/settings',
            'permissionsAll' => ['redirectManager:manageSettings'],
        ];

        return $sections;
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        // Load settings from database
        try {
            return Settings::loadFromDatabase();
        } catch (\Exception $e) {
            $this->logInfo('Could not load settings from database', ['error' => $e->getMessage()]);
            return new Settings();
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettings(): ?Model
    {
        $settings = parent::getSettings();

        if ($settings) {
            // Override with config file values using Craft's native multi-environment handling
            // This properly merges '*' with environment-specific configs (e.g., 'production')
            $config = Craft::$app->getConfig()->getConfigFromFile('redirect-manager');
            if (!empty($config) && is_array($config)) {
                foreach ($config as $key => $value) {
                    if (property_exists($settings, $key)) {
                        $settings->$key = $value;
                    }
                }
            }
        }

        return $settings;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->controller->redirect('redirect-manager/settings');
    }

    /**
     * Get CP URL rules
     */
    private function getCpUrlRules(): array
    {
        return [
            // Dashboard routes (404 list)
            'redirect-manager' => 'redirect-manager/analytics/dashboard',
            'redirect-manager/dashboard' => 'redirect-manager/analytics/dashboard',
            'redirect-manager/dashboard/export-csv' => 'redirect-manager/analytics/export-csv',

            // Redirects routes
            'redirect-manager/redirects' => 'redirect-manager/redirects/index',
            'redirect-manager/redirects/new' => 'redirect-manager/redirects/edit',
            'redirect-manager/redirects/<redirectId:\d+>' => 'redirect-manager/redirects/edit',

            // Analytics routes (charts/analytics)
            'redirect-manager/analytics' => 'redirect-manager/analytics/index',
            'redirect-manager/analytics/export' => 'redirect-manager/analytics/export',

            // Import/Export routes
            'redirect-manager/import-export' => 'redirect-manager/import-export/index',
            'redirect-manager/import-export/map' => 'redirect-manager/import-export/map',
            'redirect-manager/import-export/preview' => 'redirect-manager/import-export/preview',
            'redirect-manager/import-export/export' => 'redirect-manager/import-export/export',
            'redirect-manager/import-export/clear-logs' => 'redirect-manager/import-export/clear-logs',
            'redirect-manager/backups' => 'redirect-manager/import-export/backups',

            // Settings routes
            'redirect-manager/settings' => 'redirect-manager/settings/index',
            'redirect-manager/settings/<section:\w+>' => 'redirect-manager/settings/<section>',
        ];
    }

    /**
     * Get plugin permissions
     */
    private function getPluginPermissions(Settings $settings): array
    {
        $plural = $settings->getPluralLowerDisplayName();

        return [
            // Redirects - grouped
            'redirectManager:manageRedirects' => [
                'label' => Craft::t('redirect-manager', 'Manage {plural}', ['plural' => $plural]),
                'nested' => [
                    'redirectManager:viewRedirects' => [
                        'label' => Craft::t('redirect-manager', 'View {plural}', ['plural' => $plural]),
                    ],
                    'redirectManager:createRedirects' => [
                        'label' => Craft::t('redirect-manager', 'Create {plural}', ['plural' => $plural]),
                    ],
                    'redirectManager:editRedirects' => [
                        'label' => Craft::t('redirect-manager', 'Edit {plural}', ['plural' => $plural]),
                    ],
                    'redirectManager:deleteRedirects' => [
                        'label' => Craft::t('redirect-manager', 'Delete {plural}', ['plural' => $plural]),
                    ],
                ],
            ],
            'redirectManager:manageImportExport' => [
                'label' => Craft::t('redirect-manager', 'Manage import/export'),
                'nested' => [
                    'redirectManager:importRedirects' => [
                        'label' => Craft::t('redirect-manager', 'Import {plural}', ['plural' => $plural]),
                    ],
                    'redirectManager:exportRedirects' => [
                        'label' => Craft::t('redirect-manager', 'Export {plural}', ['plural' => $plural]),
                    ],
                    'redirectManager:viewImportHistory' => [
                        'label' => Craft::t('redirect-manager', 'View import history'),
                    ],
                    'redirectManager:clearImportHistory' => [
                        'label' => Craft::t('redirect-manager', 'Clear import history'),
                    ],
                ],
            ],
            'redirectManager:manageBackups' => [
                'label' => Craft::t('redirect-manager', 'Manage backups'),
                'nested' => [
                    'redirectManager:createBackups' => [
                        'label' => Craft::t('redirect-manager', 'Create backups'),
                    ],
                    'redirectManager:downloadBackups' => [
                        'label' => Craft::t('redirect-manager', 'Download backups'),
                    ],
                    'redirectManager:restoreBackups' => [
                        'label' => Craft::t('redirect-manager', 'Restore backups'),
                    ],
                    'redirectManager:deleteBackups' => [
                        'label' => Craft::t('redirect-manager', 'Delete backups'),
                    ],
                ],
            ],
            'redirectManager:viewAnalytics' => [
                'label' => Craft::t('redirect-manager', 'View analytics'),
                'nested' => [
                    'redirectManager:exportAnalytics' => [
                        'label' => Craft::t('redirect-manager', 'Export analytics'),
                    ],
                    'redirectManager:clearAnalytics' => [
                        'label' => Craft::t('redirect-manager', 'Clear analytics'),
                    ],
                ],
            ],
            'redirectManager:clearCache' => [
                'label' => Craft::t('redirect-manager', 'Clear cache'),
            ],
            'redirectManager:viewLogs' => [
                'label' => Craft::t('redirect-manager', 'View logs'),
                'nested' => [
                    'redirectManager:viewSystemLogs' => [
                        'label' => Craft::t('redirect-manager', 'View system logs'),
                        'nested' => [
                            'redirectManager:downloadSystemLogs' => [
                                'label' => Craft::t('redirect-manager', 'Download system logs'),
                            ],
                        ],
                    ],
                ],
            ],
            'redirectManager:manageSettings' => [
                'label' => Craft::t('redirect-manager', 'Manage settings'),
            ],
        ];
    }

    /**
     * Schedule analytics cleanup job
     */
    private function scheduleAnalyticsCleanup(): void
    {
        $settings = $this->getSettings();

        // Only schedule cleanup if analytics is enabled and retention is set
        if ($settings->enableAnalytics && $settings->analyticsRetention > 0) {
            // Check if a cleanup job is already scheduled
            $existingJob = (new \craft\db\Query())
                ->from('{{%queue}}')
                ->where(['like', 'job', 'redirectmanager'])
                ->andWhere(['like', 'job', 'CleanupAnalyticsJob'])
                ->exists();

            if (!$existingJob) {
                $job = new CleanupAnalyticsJob([
                    'reschedule' => true,
                ]);

                // Add to queue with a small initial delay
                // The job will re-queue itself to run every 24 hours
                Craft::$app->queue->delay(5 * 60)->push($job);

                $this->logInfo('Scheduled initial analytics cleanup job', ['interval' => '24 hours']);
            }
        }
    }

    /**
     * Schedule backup job if enabled
     * Called on every plugin init to ensure job is always in queue
     *
     * @since 5.23.0
     */
    private function scheduleBackupJob(): void
    {
        $settings = $this->getSettings();

        if (!$settings->backupEnabled || $settings->backupSchedule === 'manual') {
            return;
        }

        $existingJob = (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'redirectmanager'])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->exists();

        if (!$existingJob) {
            $delay = match ($settings->backupSchedule) {
                'daily' => 86400,
                'weekly' => 604800,
                'monthly' => 2592000,
                default => 86400,
            };

            $job = new CreateBackupJob([
                'reason' => 'scheduled',
                'reschedule' => true,
            ]);

            Craft::$app->getQueue()->delay($delay)->push($job);

            $this->logInfo('Scheduled initial backup job', [
                'delay_seconds' => $delay,
                'schedule' => $settings->backupSchedule,
            ]);
        }
    }

    /**
     * Handle backup schedule changes when settings are saved
     *
     * @since 5.23.0
     */
    private function handleBackupScheduleChange(Settings $settings): void
    {
        if (!$settings->backupEnabled || $settings->backupSchedule === 'manual') {
            $this->cancelScheduledBackupJobs();
            $this->logInfo('Backup scheduling disabled');
            return;
        }

        $existingJob = (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'redirectmanager'])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->andWhere(['fail' => false])
            ->andWhere(['timePushed' => null])
            ->exists();

        if ($existingJob) {
            $this->logInfo('Scheduled backup job already exists, not creating a new one');
            return;
        }

        $delay = match ($settings->backupSchedule) {
            'daily' => 86400,
            'weekly' => 604800,
            'monthly' => 2592000,
            default => 86400,
        };

        $job = new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]);

        Craft::$app->getQueue()->delay($delay)->push($job);

        $this->logInfo('Scheduled backup job queued', ['schedule' => $settings->backupSchedule]);
    }

    /**
     * Cancel any existing scheduled backup jobs
     *
     * @since 5.23.0
     */
    private function cancelScheduledBackupJobs(): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', [
                'and',
                ['like', 'job', 'redirectmanager'],
                ['like', 'job', 'CreateBackupJob'],
            ])
            ->execute();
    }

    /**
     * Install event listeners for 404 handling and entry changes
     */
    private function installEventListeners(): void
    {
        $request = Craft::$app->getRequest();

        // Only install site listeners for site requests
        if ($request->getIsSiteRequest() && !$request->getIsConsoleRequest()) {
            // Handle 404 exceptions
            Event::on(
                ErrorHandler::class,
                ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION,
                function(ExceptionEvent $event) {
                    if ($event->exception instanceof \yii\web\NotFoundHttpException) {
                        $this->redirects->handle404($event->exception);
                    }
                }
            );
        }

        // Listen for entry URI changes (all request types)
        if ($this->getSettings()->autoCreateRedirects) {
            Event::on(
                Elements::class,
                Elements::EVENT_BEFORE_SAVE_ELEMENT,
                function(ElementEvent $event) {
                    $this->redirects->stashElementUri($event->element);
                }
            );

            Event::on(
                Elements::class,
                Elements::EVENT_AFTER_SAVE_ELEMENT,
                function(ElementEvent $event) {
                    $this->redirects->handleElementUriChange($event->element);
                }
            );
        }
    }
}

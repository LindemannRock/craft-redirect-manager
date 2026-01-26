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
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\LoggingLibrary;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\services\AnalyticsService;
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

        // Bootstrap base module (logging + Twig extension)
        PluginHelper::bootstrap(
            $this,
            'redirectHelper',
            ['redirectManager:viewLogs'],
            ['redirectManager:downloadLogs']
        );
        PluginHelper::applyPluginNameFromConfig($this);

        // Register services
        $this->setComponents([
            'redirects' => RedirectsService::class,
            'analytics' => AnalyticsService::class,
            'matching' => MatchingService::class,
            'deviceDetection' => DeviceDetectionService::class,
        ]);

        // Schedule analytics cleanup if retention is enabled
        $this->scheduleAnalyticsCleanup();

        // Register translations
        Craft::$app->i18n->translations['redirect-manager'] = [
            'class' => \craft\i18n\PhpMessageSource::class,
            'sourceLanguage' => 'en',
            'basePath' => __DIR__ . '/translations',
            'forceTranslation' => true,
            'allowOverrides' => true,
        ];

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
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $user = Craft::$app->getUser();

        // Check if user has view access to each section
        $hasRedirectsAccess = $user->checkPermission('redirectManager:viewRedirects');
        $hasAnalyticsAccess = $user->checkPermission('redirectManager:viewAnalytics');
        $hasImportExportAccess = $user->checkPermission('redirectManager:manageImportExport');
        $hasLogsAccess = $user->checkPermission('redirectManager:viewLogs');
        $hasSettingsAccess = $user->checkPermission('redirectManager:manageSettings');

        // If no access at all, hide the plugin from nav
        if (!$hasRedirectsAccess && !$hasAnalyticsAccess && !$hasImportExportAccess && !$hasLogsAccess && !$hasSettingsAccess) {
            return null;
        }

        if ($item) {
            $item['label'] = $this->getSettings()->getFullName();
            $item['icon'] = '@appicons/arrows-turn-right.svg';

            $item['subnav'] = [];

            // Dashboard - requires analytics access (shows 404 list)
            if ($hasAnalyticsAccess) {
                $item['subnav']['dashboard'] = [
                    'label' => Craft::t('redirect-manager', 'Dashboard'),
                    'url' => 'redirect-manager',
                ];
            }

            // Redirects - requires any redirect permission
            if ($hasRedirectsAccess) {
                $item['subnav']['redirects'] = [
                    'label' => Craft::t('redirect-manager', 'Redirects'),
                    'url' => 'redirect-manager/redirects',
                ];
            }

            // Import/Export - requires import/export permission
            if ($hasImportExportAccess) {
                $item['subnav']['import-export'] = [
                    'label' => Craft::t('redirect-manager', 'Import/Export'),
                    'url' => 'redirect-manager/import-export',
                ];
            }

            // Add analytics if enabled and user has permission
            if ($this->getSettings()->enableAnalytics && $hasAnalyticsAccess) {
                $item['subnav']['analytics'] = [
                    'label' => Craft::t('redirect-manager', 'Analytics'),
                    'url' => 'redirect-manager/analytics',
                ];
            }

            // Add logs section using the logging library
            if (Craft::$app->getPlugins()->isPluginInstalled('logging-library') &&
                Craft::$app->getPlugins()->isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'redirectManager:viewLogs',
                ]);
            }

            if ($hasSettingsAccess) {
                $item['subnav']['settings'] = [
                    'label' => Craft::t('redirect-manager', 'Settings'),
                    'url' => 'redirect-manager/settings',
                ];
            }
        }

        return $item;
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

            // Import/Export routes
            'redirect-manager/import-export' => 'redirect-manager/import-export/index',
            'redirect-manager/import-export/map' => 'redirect-manager/import-export/map',
            'redirect-manager/import-export/preview' => 'redirect-manager/import-export/preview',

            // Settings routes
            'redirect-manager/settings' => 'redirect-manager/settings/index',
            'redirect-manager/settings/<section:\w+>' => 'redirect-manager/settings/<section>',

            // Logging routes
            'redirect-manager/logs' => 'logging-library/logs/index',
            'redirect-manager/logs/download' => 'logging-library/logs/download',
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
                    'redirectManager:downloadLogs' => [
                        'label' => Craft::t('redirect-manager', 'Download logs'),
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

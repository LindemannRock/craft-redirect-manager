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
use craft\base\Plugin;
use craft\base\Model;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\ElementEvent;
use craft\events\ExceptionEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\ErrorHandler;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\services\RedirectsService;
use lindemannrock\redirectmanager\services\AnalyticsService;
use lindemannrock\redirectmanager\services\MatchingService;
use lindemannrock\redirectmanager\services\DeviceDetectionService;
use lindemannrock\redirectmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\redirectmanager\variables\RedirectManagerVariable;
use lindemannrock\redirectmanager\utilities\RedirectManagerUtility;
use lindemannrock\redirectmanager\widgets\AnalyticsSummaryWidget;
use lindemannrock\redirectmanager\widgets\Unhandled404sWidget;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\logginglibrary\LoggingLibrary;
use yii\base\Event;

/**
 * Redirect Manager Plugin
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
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
     * @var RedirectManager|null
     */
    public static ?RedirectManager $plugin = null;

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Configure logging
        $settings = $this->getSettings();
        LoggingLibrary::configure([
            'pluginHandle' => $this->handle,
            'pluginName' => $settings->pluginName ?? $this->name,
            'logLevel' => $settings->logLevel ?? 'error',
            'itemsPerPage' => $settings->itemsPerPage ?? 50,
            'permissions' => ['redirectManager:viewLogs'],
        ]);

        // Set plugin name from config if available
        $configPath = Craft::$app->getPath()->getConfigPath() . '/redirect-manager.php';
        if (file_exists($configPath)) {
            $rawConfig = require $configPath;
            if (isset($rawConfig['pluginName'])) {
                $this->name = $rawConfig['pluginName'];
            }
        }

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
                $event->permissions[] = [
                    'heading' => Craft::t('redirect-manager', 'Redirect Manager'),
                    'permissions' => $this->getPluginPermissions(),
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
                $settings = $this->getSettings();
                $pluginName = $settings->pluginName ?? 'Redirect Manager';

                $event->options[] = [
                    'key' => 'redirect-manager-cache',
                    'label' => Craft::t('redirect-manager', '{pluginName} Cache', ['pluginName' => $pluginName]),
                    'action' => [$this->redirects, 'invalidateCaches'],
                ];
            }
        );

        // Install event listeners for 404 handling and entry changes
        $this->installEventListeners();

        Craft::info(
            Craft::t('redirect-manager', '{name} plugin loaded', ['name' => $this->name]),
            __METHOD__
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($item) {
            $item['label'] = $this->getSettings()->pluginName;
            $item['icon'] = '@appicons/arrows-righ-left.svg';

            $item['subnav'] = [
                'dashboard' => [
                    'label' => Craft::t('redirect-manager', 'Dashboard'),
                    'url' => 'redirect-manager',
                ],
                'redirects' => [
                    'label' => Craft::t('redirect-manager', 'Redirects'),
                    'url' => 'redirect-manager/redirects',
                ],
                'import-export' => [
                    'label' => Craft::t('redirect-manager', 'Import/Export'),
                    'url' => 'redirect-manager/import-export',
                ],
            ];

            // Add analytics if enabled
            if ($this->getSettings()->enableAnalytics) {
                $item['subnav']['analytics'] = [
                    'label' => Craft::t('redirect-manager', 'Analytics'),
                    'url' => 'redirect-manager/analytics',
                ];
            }

            // Add logs section using the logging library
            if (Craft::$app->getPlugins()->isPluginInstalled('logging-library') &&
                Craft::$app->getPlugins()->isPluginEnabled('logging-library')) {
                $item = LoggingLibrary::addLogsNav($item, $this->handle, [
                    'redirectManager:viewLogs'
                ]);
            }

            if (Craft::$app->getUser()->checkPermission('redirectManager:manageSettings')) {
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
            Craft::info('Could not load settings from database', __METHOD__, ['error' => $e->getMessage()]);
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
            // Load config file settings and merge with database values
            $configPath = Craft::$app->getPath()->getConfigPath() . '/redirect-manager.php';
            if (file_exists($configPath)) {
                $config = require $configPath;

                // Apply environment-specific overrides
                $env = Craft::$app->getConfig()->env;
                if ($env && isset($config[$env])) {
                    $config = array_merge($config, $config[$env]);
                }

                // Apply wildcard overrides
                if (isset($config['*'])) {
                    $config = array_merge($config, $config['*']);
                }

                // Remove environment-specific keys
                unset($config['*'], $config['dev'], $config['staging'], $config['production']);

                // Set config values (these override database values)
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
    private function getPluginPermissions(): array
    {
        return [
            'redirectManager:viewRedirects' => [
                'label' => Craft::t('redirect-manager', 'View redirects'),
            ],
            'redirectManager:createRedirects' => [
                'label' => Craft::t('redirect-manager', 'Create redirects'),
            ],
            'redirectManager:editRedirects' => [
                'label' => Craft::t('redirect-manager', 'Edit redirects'),
            ],
            'redirectManager:deleteRedirects' => [
                'label' => Craft::t('redirect-manager', 'Delete redirects'),
            ],
            'redirectManager:viewAnalytics' => [
                'label' => Craft::t('redirect-manager', 'View analytics'),
            ],
            'redirectManager:viewLogs' => [
                'label' => Craft::t('redirect-manager', 'View logs'),
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
            // Check if a cleanup job is already scheduled (within next 24 hours)
            $existingJob = (new \craft\db\Query())
                ->from('{{%queue}}')
                ->where(['like', 'job', 'redirectmanager'])
                ->andWhere(['like', 'job', 'CleanupAnalyticsJob'])
                ->andWhere(['<=', 'timePushed', time() + 86400]) // Within next 24 hours
                ->exists();

            if (!$existingJob) {
                $job = new CleanupAnalyticsJob([
                    'reschedule' => true,
                ]);

                // Add to queue with a small initial delay
                // The job will re-queue itself to run every 24 hours
                Craft::$app->queue->delay(5 * 60)->push($job);

                Craft::info(
                    Craft::t('redirect-manager', 'Scheduled initial analytics cleanup job (will run every 24 hours)'),
                    __METHOD__
                );
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
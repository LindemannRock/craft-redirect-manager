<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\redirectmanager\controllers\SettingsController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.32.0
 */
#[CoversClass(SettingsController::class)]
final class SettingsControllerSectionScopeTest extends TestCase
{
    public function testSettingsSectionsMatchRenderedFormScopes(): void
    {
        $controller = new SettingsController('settings', RedirectManager::$plugin);
        $method = new \ReflectionMethod($controller, '_validationAttributesForSection');

        $expected = [
            'general' => [
                'pluginName',
                'autoCreateRedirects',
                'undoWindowMinutes',
                'redirectSrcMatch',
                'stripQueryString',
                'preserveQueryString',
                'setNoCacheHeaders',
                'logLevel',
            ],
            'analytics' => [
                'enableAnalytics',
                'enableGeoDetection',
                'geoProvider',
                'geoApiKey',
                'anonymizeIpAddress',
                'stripQueryStringFromStats',
                'analyticsRetention',
                'analyticsLimit',
                'autoTrimAnalytics',
            ],
            'interface' => [
                'itemsPerPage',
                'refreshIntervalSecs',
                'timeFormat',
                'monthFormat',
                'dateOrder',
                'dateSeparator',
                'showSeconds',
                'defaultDateRange',
                'exportsCsv',
                'exportsJson',
                'exportsExcel',
            ],
            'cache' => [
                'cacheStorageMethod',
                'cacheDeviceDetection',
                'deviceDetectionCacheDuration',
                'enableRedirectCache',
                'redirectCacheDuration',
            ],
            'advanced' => [
                'apiEndpointEnabled',
                'apiEndpointRateLimit',
                'excludePatterns',
                'additionalHeaders',
            ],
            'backup' => [
                'backupEnabled',
                'backupOnImport',
                'backupSchedule',
                'backupRetentionDays',
                'backupVolumeUid',
                'backupPath',
            ],
        ];

        foreach ($expected as $section => $attributes) {
            self::assertSame($attributes, $method->invoke($controller, $section), "Unexpected {$section} settings scope.");
        }
    }
}

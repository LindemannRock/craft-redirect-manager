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

    public function testInstructionPlaceholdersEscapeConfiguredPluginName(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $source = file_get_contents($pluginRoot . '/src/templates/settings/advanced.twig');
        self::assertIsString($source);

        self::assertStringContainsString('{% set redirectFullNameHtml = redirectHelper.fullName|e %}', $source);
        self::assertStringContainsString('pluginName: redirectFullNameHtml', $source);
        self::assertDoesNotMatchRegularExpression('/instructions:.*redirectHelper\\.(?:lowerDisplayName|pluralLowerDisplayName|fullName|displayName)/', $source);
    }

    public function testSetupCompleteInfoBoxUsesConfiguredPluginName(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/templates/setup.twig');
        self::assertIsString($source);

        self::assertStringContainsString("{% set title = 'Set up {pluginName}'|t('redirect-manager', {", $source);
        self::assertStringContainsString('pluginName: redirectHelper.fullName', $source);
        self::assertStringNotContainsString('Set up Redirect Manager', $source);
        self::assertStringContainsString('{% set redirectFullNameHtml = redirectHelper.fullName|e %}', $source);
        self::assertStringContainsString('redirectFullNameHtml: redirectFullNameHtml,', $source);
        self::assertStringContainsString("'{pluginName} is ready to track redirect analytics.'|t('redirect-manager', {", $source);
        self::assertStringContainsString('pluginName: redirectFullNameHtml', $source);
        self::assertStringNotContainsString("'Redirect Manager is ready to track redirect analytics.'|t('redirect-manager')", $source);
    }
}

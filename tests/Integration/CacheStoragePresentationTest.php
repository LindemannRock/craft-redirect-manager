<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use Craft;
use craft\web\View;
use lindemannrock\base\cache\DisposableCacheStoragePresenter;
use lindemannrock\redirectmanager\controllers\SettingsController;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\Support\InMemoryRedisConnection;
use lindemannrock\redirectmanager\tests\TestCase;
use yii\redis\Cache as RedisCache;

final class CacheStoragePresentationTest extends TestCase
{
    private bool $hadEphemeralSetting;
    private mixed $originalEphemeralSetting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hadEphemeralSetting = array_key_exists('CRAFT_EPHEMERAL', $_SERVER);
        $this->originalEphemeralSetting = $_SERVER['CRAFT_EPHEMERAL'] ?? null;
        $_SERVER['CRAFT_EPHEMERAL'] = false;
    }

    protected function tearDown(): void
    {
        if ($this->hadEphemeralSetting) {
            $_SERVER['CRAFT_EPHEMERAL'] = $this->originalEphemeralSetting;
        } else {
            unset($_SERVER['CRAFT_EPHEMERAL']);
        }
        parent::tearDown();
    }

    public function testEffectiveStoragePresentationUsesSemanticApplicationStatusWithoutCounts(): void
    {
        $this->useMemoryApplicationCache();
        $storage = RedirectManager::$plugin->localCache;
        $presenter = new DisposableCacheStoragePresenter();
        $application = $presenter->present($storage->getStorageDecision('craft'));

        self::assertSame('Using Redis cache', $application->headingKey);
        self::assertSame('Redis cache', $application->utilityDescriptionKey);
        self::assertFalse($application->filePathEligible);

        $template = $this->readPluginFile('src/templates/utilities/index.twig');
        self::assertStringContainsString("cacheStorage.utilityValueKey|t('lindemannrock-base')", $template);
        self::assertStringContainsString("cacheStorage.utilityDescriptionKey|t('lindemannrock-base')", $template);
        self::assertStringContainsString('showCacheCounts ? redirectCacheFiles : null', $template);
        self::assertStringNotContainsString('storageMethod', $template);
        self::assertStringNotContainsString('Total cached entries', $template);
    }

    public function testEphemeralFileSelectionUsesApplicationCacheAndSuppressesFilePath(): void
    {
        $this->useMemoryApplicationCache();
        $_SERVER['CRAFT_EPHEMERAL'] = true;
        $storage = RedirectManager::$plugin->localCache;
        $decision = $storage->getStorageDecision('file');
        $presentation = (new DisposableCacheStoragePresenter())->present($decision);

        self::assertTrue($decision->usesApplicationCache());
        self::assertTrue($decision->fileStorageBypassed);
        self::assertNull($storage->getDisplayFilePath($decision));
        self::assertContains(
            'This host has an ephemeral filesystem, so the application cache is used automatically.',
            $presentation->explanationKeys,
        );
    }

    public function testSettingsUseSharedFieldAndPreserveApplicationToken(): void
    {
        $this->useMemoryApplicationCache();
        $controller = new SettingsController('settings', RedirectManager::$plugin);
        $method = new \ReflectionMethod($controller, 'cacheStorageTemplateVariables');

        foreach (['redis', 'craft'] as $token) {
            $variables = $method->invoke($controller, new Settings(['cacheStorageMethod' => $token]));
            self::assertIsArray($variables);
            self::assertSame($token, $variables['applicationToken']);
            self::assertStringContainsString('/redirect-manager/cache/', $variables['filePath']);
            self::assertFalse($variables['applicationPresentation']->filePathEligible);
        }

        $unknown = $method->invoke($controller, new Settings(['cacheStorageMethod' => 'unsupported']));
        self::assertIsArray($unknown);
        self::assertSame('Caching disabled', $unknown['applicationPresentation']->headingKey);

        $template = $this->readPluginFile('src/templates/settings/cache.twig');
        self::assertStringContainsString("'lindemannrock-base/_partials/field-cache-storage'", $template);
        self::assertStringNotContainsString('yii\\redis\\Cache', $template);
        self::assertStringNotContainsString('Redis Not Configured', $template);
    }

    public function testSharedStorageFieldRendersForRedirectManager(): void
    {
        $this->useMemoryApplicationCache();
        $settings = new Settings(['cacheStorageMethod' => 'redis']);
        $settings->addError('cacheStorageMethod', 'Injected cache storage error.');
        $controller = new SettingsController('settings', RedirectManager::$plugin);
        $variables = (new \ReflectionMethod($controller, 'cacheStorageTemplateVariables'))->invoke($controller, $settings);
        self::assertIsArray($variables);

        $assetManager = Craft::$app->getAssetManager();
        $originalBasePath = $assetManager->basePath;
        $originalBaseUrl = $assetManager->baseUrl;
        $assetManager->basePath = $this->createTrackedTempDirectory('redirect-manager-assets-');
        $assetManager->baseUrl = '/assets';
        try {
            $html = Craft::$app->getView()->renderTemplate(
                'lindemannrock-base/_partials/field-cache-storage',
                [
                    'settings' => $settings,
                    'pluginHandle' => RedirectManager::$plugin->id,
                    'configuredStorageToken' => $settings->cacheStorageMethod,
                    'applicationOptionToken' => $variables['applicationToken'],
                    'filePresentation' => $variables['filePresentation'],
                    'applicationPresentation' => $variables['applicationPresentation'],
                    'filePath' => $variables['filePath'],
                ],
                View::TEMPLATE_MODE_CP,
            );
        } finally {
            $assetManager->basePath = $originalBasePath;
            $assetManager->baseUrl = $originalBaseUrl;
        }

        self::assertStringContainsString('Cache Storage Method', $html);
        self::assertStringContainsString('value="file"', $html);
        self::assertStringContainsString('value="redis" selected', $html);
        self::assertStringContainsString('Using Redis cache', $html);
        self::assertStringContainsString('Injected cache storage error.', $html);
    }

    public function testSettingsAcceptEverySupportedStorageToken(): void
    {
        foreach (['file', 'redis', 'craft'] as $token) {
            $settings = new Settings(['cacheStorageMethod' => $token]);
            self::assertTrue($settings->validate(['cacheStorageMethod']));
            self::assertSame([], $settings->getErrors('cacheStorageMethod'));
        }
    }

    public function testEverySharedStorageMessageExistsInAllBaseCatalogues(): void
    {
        $keys = [
            'Cache Storage Method',
            'Choose where disposable cache data is stored. File caching automatically uses the application cache on ephemeral hosts.',
            'File cache',
            'Application cache',
            'Using managed cache',
            'This host has an ephemeral filesystem, so the application cache is used automatically.',
            'Using Redis cache',
            'Using database cache',
            'Using file cache',
            'Using filesystem cache',
            'Using application cache',
            'Cross-request persistence could not be confirmed.',
            'Caching disabled',
            'No suitable cross-request cache is available. Cache data is recomputed as needed.',
            'Active',
            'Managed cache',
            'Redis cache',
            'Database cache',
            'Filesystem cache',
            'Best effort',
            'Recomputed as needed',
            'Inactive',
            'No cache families enabled',
        ];

        foreach (['en', 'de', 'fr', 'nl', 'es', 'ar', 'it', 'pt', 'ja', 'sv', 'da', 'no'] as $locale) {
            $catalogue = require dirname(__DIR__, 3) . "/base/src/translations/{$locale}/lindemannrock-base.php";
            self::assertIsArray($catalogue);
            foreach ($keys as $key) {
                self::assertArrayHasKey($key, $catalogue, "Missing {$locale} Base key: {$key}");
            }
        }
    }

    private function useMemoryApplicationCache(): void
    {
        Craft::$app->set('cache', new RedisCache([
            'redis' => new InMemoryRedisConnection(),
            'keyPrefix' => 'redirect-manager-presentation-test:',
            'forceClusterMode' => false,
        ]));
        RedirectManager::$plugin->getSettings()->cacheStorageMethod = 'redis';
    }

    private function readPluginFile(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source);

        return $source;
    }
}

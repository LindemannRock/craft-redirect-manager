<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.32.0
 */
#[CoversClass(RedirectManager::class)]
#[CoversClass(PluginHelper::class)]
class RedisCacheSafeguardTest extends TestCase
{
    public function testRuntimeSourceUsesRedisSafeguardHelper(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $sourceFiles = [
            $pluginRoot . '/src/controllers/SettingsController.php',
            $pluginRoot . '/src/services/RedirectsService.php',
        ];

        foreach ($sourceFiles as $sourceFile) {
            $source = file_get_contents($sourceFile);
            $this->assertIsString($source);
            $this->assertStringNotContainsString('instanceof \yii\redis\Cache', $source);
            $this->assertStringContainsString('PluginHelper::getRedisCacheOrLog', $source);
        }
    }
}

<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use Craft;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\tests\TestCase;

/**
 * Pins Redirect Manager's backup path validation policy.
 *
 * @since 5.32.0
 */
final class SettingsStoragePathTest extends TestCase
{
    private const ENV_NAME = 'LR_REDIRECT_BACKUP_PATH_TEST';

    protected function tearDown(): void
    {
        putenv(self::ENV_NAME);
        unset($_ENV[self::ENV_NAME], $_SERVER[self::ENV_NAME]);

        parent::tearDown();
    }

    public function testStorageAliasBackupPathPasses(): void
    {
        $settings = $this->settingsForBackupPath('@storage/redirect-manager/backups');

        self::assertTrue($settings->validate(['backupPath']), json_encode($settings->getErrors()));
    }

    public function testEnvBackupPathResolvingToStorageAliasPasses(): void
    {
        $this->setEnvValue('@storage/redirect-manager/backups');
        $settings = $this->settingsForBackupPath('$' . self::ENV_NAME);

        self::assertTrue($settings->validate(['backupPath']), json_encode($settings->getErrors()));
        self::assertSame(Craft::getAlias('@storage/redirect-manager/backups'), $settings->getBackupPath());
    }

    public function testEnvBackupPathResolvingInsideStoragePasses(): void
    {
        $path = Craft::getAlias('@storage/redirect-manager/backups');
        $this->setEnvValue($path);
        $settings = $this->settingsForBackupPath('$' . self::ENV_NAME);

        self::assertTrue($settings->validate(['backupPath']), json_encode($settings->getErrors()));
        self::assertSame($path, $settings->getBackupPath());
    }

    public function testRootSubfolderBackupPathPasses(): void
    {
        $settings = $this->settingsForBackupPath('@root/backups/redirect-manager');

        self::assertTrue($settings->validate(['backupPath']), json_encode($settings->getErrors()));
    }

    public function testRootOnlyBackupPathFails(): void
    {
        $settings = $this->settingsForBackupPath('@root');

        self::assertFalse($settings->validate(['backupPath']));
        self::assertArrayHasKey('backupPath', $settings->getErrors());
    }

    public function testInvalidAliasFailsValidationWithoutBreakingResolvedPathFallback(): void
    {
        $settings = $this->settingsForBackupPath('@storages/redirect-manager/backups');

        self::assertFalse($settings->validate(['backupPath']));
        self::assertArrayHasKey('backupPath', $settings->getErrors());
        self::assertSame(Craft::getAlias('@storage/redirect-manager/backups'), $settings->getBackupPath());
    }

    private function settingsForBackupPath(string $path): Settings
    {
        $settings = new Settings();
        $settings->backupPath = $path;

        return $settings;
    }

    private function setEnvValue(string $value): void
    {
        putenv(self::ENV_NAME . '=' . $value);
        $_ENV[self::ENV_NAME] = $value;
        $_SERVER[self::ENV_NAME] = $value;
    }
}

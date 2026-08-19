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
use craft\base\FsInterface;
use craft\base\LocalFsInterface;
use craft\fs\MissingFs;
use craft\models\Volume;
use craft\services\Config;
use craft\services\Volumes;
use craft\web\View;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\presenters\StorageWarningPresentation;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

/**
 * @since 5.41.0
 */
final class StorageWarningPresentationTest extends TestCase
{
    private const WARNING = 'This host has an ephemeral filesystem. Files in the effective local storage path may be lost during deployments, restarts, or environment replacement. Select a Craft volume backed by durable remote storage. On Craft Cloud, use a Cloud filesystem.';

    private bool $hadEphemeralSetting;
    private mixed $originalEphemeralSetting;
    private Volumes $originalVolumes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hadEphemeralSetting = array_key_exists('CRAFT_EPHEMERAL', $_SERVER);
        $this->originalEphemeralSetting = $_SERVER['CRAFT_EPHEMERAL'] ?? null;
        $this->originalVolumes = Craft::$app->getVolumes();
        $_SERVER['CRAFT_EPHEMERAL'] = true;
    }

    protected function tearDown(): void
    {
        Craft::$app->set('volumes', $this->originalVolumes);
        if ($this->hadEphemeralSetting) {
            $_SERVER['CRAFT_EPHEMERAL'] = $this->originalEphemeralSetting;
        } else {
            unset($_SERVER['CRAFT_EPHEMERAL']);
        }
        parent::tearDown();
    }

    public function testDurableCustomPathDoesNotShowWarning(): void
    {
        $_SERVER['CRAFT_EPHEMERAL'] = false;
        $volumes = $this->createMock(Volumes::class);
        $volumes->expects(self::never())->method('getVolumeByUid');
        Craft::$app->set('volumes', $volumes);

        $presentation = StorageWarningPresentation::forSettings($this->localSettings());

        self::assertSame(StorageWarningPresentation::STATE_DURABLE_HOST, $presentation->state);
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testEphemeralCustomPathShowsWarning(): void
    {
        $presentation = StorageWarningPresentation::forSettings($this->localSettings());

        self::assertSame(StorageWarningPresentation::STATE_LOCAL, $presentation->state);
        self::assertTrue($presentation->shouldShowWarning());
    }

    public function testEphemeralLocalVolumeShowsWarning(): void
    {
        $this->installVolumes($this->volume($this->localFilesystem()));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_LOCAL, $presentation->state);
        self::assertTrue($presentation->shouldShowWarning());
    }

    public function testEphemeralNonLocalVolumeSuppressesWarning(): void
    {
        $this->installVolumes($this->volume($this->nonLocalFilesystem()));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_NON_LOCAL, $presentation->state);
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testConfigNonLocalVolumeOverrideSuppressesWarningDespiteStoredLocalPath(): void
    {
        $this->installVolumes($this->volume($this->nonLocalFilesystem()));
        $effective = $this->applyConfigOverrides(
            $this->localSettings(),
            ['backupVolumeUid' => 'warning-volume'],
        );

        $presentation = StorageWarningPresentation::forSettings($effective);

        self::assertSame('warning-volume', $effective->backupVolumeUid);
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testConfigLocalPathOverrideShowsWarningDespiteStoredNonLocalVolume(): void
    {
        $effective = $this->applyConfigOverrides(
            $this->volumeSettings(),
            [
                'backupVolumeUid' => '',
                'backupPath' => '@storage/redirect-manager/configured-backups',
            ],
        );

        $presentation = StorageWarningPresentation::forSettings($effective);

        self::assertSame('', $effective->backupVolumeUid);
        self::assertSame('@storage/redirect-manager/configured-backups', $effective->backupPath);
        self::assertTrue($presentation->shouldShowWarning());
    }

    public function testEphemeralMissingVolumeFallsBackLocallyAndShowsWarning(): void
    {
        $this->installVolumes(null);

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_LOCAL, $presentation->state);
        self::assertTrue($presentation->shouldShowWarning());
    }

    public function testEphemeralInvalidLocalVolumeFallsBackLocallyAndShowsWarning(): void
    {
        $webroot = Craft::getAlias('@webroot');
        self::assertIsString($webroot);
        /** @var FsInterface&LocalFsInterface&MockObject $fs */
        $fs = $this->createMockForIntersectionOfInterfaces([FsInterface::class, LocalFsInterface::class]);
        $fs->method('getRootPath')->willReturn($webroot . '/warning-test');
        $this->installVolumes($this->volume($fs));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_LOCAL, $presentation->state);
        self::assertTrue($presentation->shouldShowWarning());
    }

    public function testEphemeralMissingFilesystemIsUnavailableAndNotClassifiedAsDurable(): void
    {
        $this->installVolumes($this->volume(new MissingFs(['handle' => 'warning-missing'])));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_UNAVAILABLE, $presentation->state);
        self::assertTrue($presentation->isUnavailable());
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testEphemeralThrowingFilesystemIsUnavailableAndNotClassifiedAsDurable(): void
    {
        $volume = $this->createMock(Volume::class);
        $volume->method('getFs')->willThrowException(new RuntimeException('warning test failure'));
        $this->installVolumes($volume);

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_UNAVAILABLE, $presentation->state);
        self::assertTrue($presentation->isUnavailable());
        self::assertFalse($presentation->shouldShowWarning());
    }

    public function testClassificationPerformsNoStorageIo(): void
    {
        $fs = $this->nonLocalFilesystem();
        $this->expectNoStorageIo($fs);
        $this->installVolumes($this->volume($fs));

        $presentation = StorageWarningPresentation::forSettings($this->volumeSettings());

        self::assertSame(StorageWarningPresentation::STATE_NON_LOCAL, $presentation->state);
    }

    public function testClassificationDoesNotMutateEffectiveSettings(): void
    {
        $settings = $this->volumeSettings();
        $before = $settings->getAttributes();
        $this->installVolumes($this->volume($this->nonLocalFilesystem()));

        StorageWarningPresentation::forSettings($settings);

        self::assertSame($before, $settings->getAttributes());
    }

    public function testWarningRendersExactPluginOwnedMessageImmediatelyAfterLocation(): void
    {
        $template = (string)file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/backup.twig');
        $location = strpos($template, 'Backup Location:');
        $warning = strpos($template, self::WARNING);
        $credit = strpos($template, "lindemannrock-base/_components/plugin-credit");

        self::assertIsInt($location);
        self::assertIsInt($warning);
        self::assertIsInt($credit);
        self::assertTrue($location < $warning && $warning < $credit);
        self::assertStringContainsString("|t('redirect-manager')", substr($template, $warning, strlen(self::WARNING) + 40));

        $html = Craft::$app->getView()->renderTemplate(
            'lindemannrock-base/_components/info-box',
            [
                'message' => Craft::t('redirect-manager', self::WARNING),
                'type' => 'warning',
                'variant' => 'colored',
                'allowHtml' => false,
            ],
            View::TEMPLATE_MODE_CP,
        );
        self::assertStringContainsString(self::WARNING, $html);
        self::assertStringContainsString('lr-info-box--colored', $html);

        foreach (['en', 'de', 'fr', 'nl', 'es', 'ar', 'it', 'pt', 'ja', 'sv', 'da', 'no'] as $locale) {
            $catalogue = require dirname(__DIR__, 2) . "/src/translations/{$locale}/redirect-manager.php";
            self::assertArrayHasKey(self::WARNING, $catalogue);
        }
    }

    private function localSettings(): Settings
    {
        return new Settings([
            'backupVolumeUid' => '',
            'backupPath' => '@storage/redirect-manager/backups',
        ]);
    }

    private function volumeSettings(): Settings
    {
        return new Settings([
            'backupVolumeUid' => 'warning-volume',
            'backupPath' => '@storage/redirect-manager/stored-local-backups',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function applyConfigOverrides(Settings $settings, array $overrides): Settings
    {
        $config = $this->createMock(Config::class);
        $config->method('getConfigFromFile')->willReturnCallback(
            static fn(string $handle): array => $handle === 'redirect-manager' ? $overrides : [],
        );
        Craft::$app->set('config', $config);
        PluginHelper::applyConfigOverridesToSettings($settings, 'redirect-manager');

        return $settings;
    }

    private function localFilesystem(): FsInterface & LocalFsInterface
    {
        /** @var FsInterface&LocalFsInterface&MockObject $fs */
        $fs = $this->createMockForIntersectionOfInterfaces([FsInterface::class, LocalFsInterface::class]);
        $fs->method('getRootPath')->willReturn($this->createTrackedTempDirectory('redirect-storage-warning-'));
        return $fs;
    }

    private function nonLocalFilesystem(): FsInterface & MockObject
    {
        return $this->createMock(FsInterface::class);
    }

    private function volume(FsInterface $fs): Volume
    {
        $volume = $this->createMock(Volume::class);
        $volume->method('getFs')->willReturn($fs);
        return $volume;
    }

    private function installVolumes(?Volume ...$sequence): void
    {
        $volumes = $this->createMock(Volumes::class);
        $index = 0;
        $volumes->method('getVolumeByUid')->willReturnCallback(
            static function(string $uid) use (&$index, $sequence): ?Volume {
                $position = min($index, count($sequence) - 1);
                $index++;
                return $sequence[$position];
            },
        );
        Craft::$app->set('volumes', $volumes);
    }

    private function expectNoStorageIo(FsInterface & MockObject $fs): void
    {
        foreach ([
            'getFileList',
            'getFileSize',
            'getDateModified',
            'write',
            'read',
            'writeFileFromStream',
            'fileExists',
            'deleteFile',
            'renameFile',
            'copyFile',
            'getFileStream',
            'directoryExists',
            'createDirectory',
            'deleteDirectory',
            'renameDirectory',
        ] as $method) {
            $fs->expects(self::never())->method($method);
        }
    }
}

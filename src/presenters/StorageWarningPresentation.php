<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\presenters;

use Craft;
use craft\base\LocalFsInterface;
use craft\base\MissingComponentInterface;
use craft\helpers\App;
use craft\models\Volume;
use lindemannrock\base\helpers\StorageVolumeHelper;
use lindemannrock\redirectmanager\models\Settings;
use Throwable;

/**
 * Classifies effective backup storage for the Craft Cloud settings warning.
 *
 * @since 5.41.0
 */
final class StorageWarningPresentation
{
    public const STATE_DURABLE_HOST = 'durable-host';
    public const STATE_LOCAL = 'local';
    public const STATE_NON_LOCAL = 'non-local';
    public const STATE_UNAVAILABLE = 'unavailable';

    private function __construct(public readonly string $state)
    {
    }

    public static function forSettings(Settings $settings): self
    {
        if (!App::isEphemeral()) {
            return new self(self::STATE_DURABLE_HOST);
        }

        $volumeUid = trim((string)$settings->backupVolumeUid);
        if ($volumeUid === '') {
            return new self(self::STATE_LOCAL);
        }

        try {
            $volumeErrors = StorageVolumeHelper::validateVolume($volumeUid);
        } catch (Throwable) {
            return new self(self::STATE_UNAVAILABLE);
        }

        if ($volumeErrors !== []) {
            return new self(self::STATE_LOCAL);
        }

        try {
            $volume = Craft::$app->getVolumes()->getVolumeByUid($volumeUid);
        } catch (Throwable) {
            return new self(self::STATE_UNAVAILABLE);
        }

        if (!$volume instanceof Volume) {
            return new self(self::STATE_LOCAL);
        }

        try {
            $fs = $volume->getFs();
        } catch (Throwable) {
            return new self(self::STATE_UNAVAILABLE);
        }

        if ($fs instanceof MissingComponentInterface) {
            return new self(self::STATE_UNAVAILABLE);
        }

        return new self(
            $fs instanceof LocalFsInterface
                ? self::STATE_LOCAL
                : self::STATE_NON_LOCAL,
        );
    }

    public function shouldShowWarning(): bool
    {
        return $this->state === self::STATE_LOCAL;
    }

    public function isUnavailable(): bool
    {
        return $this->state === self::STATE_UNAVAILABLE;
    }
}

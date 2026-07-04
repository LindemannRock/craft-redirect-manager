<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use craft\base\Component;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Computes setup readiness for Redirect Manager.
 *
 * @since 5.38.0
 */
class SetupService extends Component
{
    /**
     * @return array{complete: bool, missing: list<string>, setupUrl: string, ipSaltConfigured: bool}
     */
    public function getStatus(?Settings $settings = null): array
    {
        $settings ??= RedirectManager::$plugin->getSettings();
        $ipSaltConfigured = $this->isIpSaltConfigured($settings);
        $missing = $ipSaltConfigured ? [] : ['ipSalt'];

        return [
            'complete' => $missing === [],
            'missing' => $missing,
            'setupUrl' => 'redirect-manager/setup',
            'ipSaltConfigured' => $ipSaltConfigured,
        ];
    }

    public function isIpSaltConfigured(Settings $settings): bool
    {
        $salt = trim((string) ($settings->ipHashSalt ?? ''));

        return $salt !== '' && $salt !== '$REDIRECT_MANAGER_IP_SALT';
    }
}

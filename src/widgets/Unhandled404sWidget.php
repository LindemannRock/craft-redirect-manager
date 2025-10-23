<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\widgets;

use Craft;
use craft\base\Widget;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Redirect Manager Unhandled 404s Widget
 */
class Unhandled404sWidget extends Widget
{
    /**
     * @var int Number of 404s to show
     */
    public int $limit = 10;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['limit'], 'integer', 'min' => 5, 'max' => 50];
        $rules[] = [['limit'], 'default', 'value' => 10];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $pluginName = RedirectManager::$plugin->getSettings()->pluginName ?? 'Redirect Manager';
        return Craft::t('redirect-manager', '{pluginName} - Unhandled 404s', ['pluginName' => $pluginName]);
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return '@app/icons/exclamation-triangle.svg';
    }

    /**
     * @inheritdoc
     */
    public static function maxColspan(): ?int
    {
        return 2;
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        $pluginName = RedirectManager::$plugin->getSettings()->pluginName ?? 'Redirect Manager';
        return Craft::t('redirect-manager', '{pluginName} - Unhandled 404s', ['pluginName' => $pluginName]);
    }

    /**
     * @inheritdoc
     */
    public function getSubtitle(): ?string
    {
        return Craft::t('redirect-manager', 'Showing {limit} most common', ['limit' => $this->limit]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('redirect-manager/widgets/unhandled-404s/settings', [
            'widget' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        // Check if analytics are enabled
        if (!RedirectManager::$plugin->getSettings()->enableAnalytics) {
            return '<p class="light">' . Craft::t('redirect-manager', 'Analytics are disabled in plugin settings.') . '</p>';
        }

        // Get unhandled 404s
        $statistics = RedirectManager::$plugin->statistics->getAllStatistics(null, $this->limit);

        // Filter only unhandled ones
        $unhandled404s = array_filter($statistics, function($stat) {
            return !$stat['handled'];
        });

        return Craft::$app->getView()->renderTemplate('redirect-manager/widgets/unhandled-404s/body', [
            'widget' => $this,
            'stats' => array_slice($unhandled404s, 0, $this->limit),
        ]);
    }
}

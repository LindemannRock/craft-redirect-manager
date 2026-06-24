<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\widgets;

use Craft;
use craft\base\Widget;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * Redirect Manager Unhandled 404s Widget
 *
 * @since 5.33.0
 */
class Unhandled404sWidget extends Widget
{
    use SiteFilterTrait;

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
        $rules[] = [['siteId'], 'in', 'range' => array_column($this->siteOptions(), 'value')];
        $rules[] = [['limit'], 'default', 'value' => 10];
        $rules[] = [['siteId'], 'default', 'value' => 'all'];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return parent::isSelectable() &&
            Craft::$app->getUser()->checkPermission('redirectManager:viewAnalytics');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $pluginName = RedirectManager::$plugin->getSettings()->getFullName();
        return Craft::t('redirect-manager', '{pluginName} - Unhandled 404s', ['pluginName' => $pluginName]);
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return '@lindemannrock/redirectmanager/icon-mask.svg';
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
        $pluginName = RedirectManager::$plugin->getSettings()->getFullName();
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
            'siteOptions' => $this->siteOptions(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        if (!Craft::$app->getUser()->checkPermission('redirectManager:viewAnalytics')) {
            return '<p class="light">' . Craft::t('redirect-manager', 'You don\'t have permission to view analytics.') . '</p>';
        }

        if (!RedirectManager::$plugin->getSettings()->enableAnalytics) {
            return '<p class="light">' . Craft::t('redirect-manager', 'Analytics are disabled in plugin settings.') . '</p>';
        }

        $unhandled404s = RedirectManager::$plugin->analytics->getUnhandled404s($this->effectiveSiteId(), $this->limit);

        return Craft::$app->getView()->renderTemplate('redirect-manager/widgets/unhandled-404s/body', [
            'widget' => $this,
            'stats' => $unhandled404s,
        ]);
    }
}

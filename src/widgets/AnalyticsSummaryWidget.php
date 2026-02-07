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
 * Redirect Manager Analytics Summary Widget
 *
 * @since 5.1.0
 */
class AnalyticsSummaryWidget extends Widget
{
    /**
     * @var int Number of days to show stats for
     */
    public int $days = 7;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['days'], 'integer', 'min' => 1, 'max' => 365];
        $rules[] = [['days'], 'default', 'value' => 7];
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
        return Craft::t('redirect-manager', '{pluginName} - 404 Stats', ['pluginName' => $pluginName]);
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return '@app/icons/chart-bar.svg';
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
        return Craft::t('redirect-manager', '{pluginName} - 404 Stats', ['pluginName' => $pluginName]);
    }

    /**
     * @inheritdoc
     */
    public function getSubtitle(): ?string
    {
        return Craft::t('redirect-manager', 'Last {days} days', ['days' => $this->days]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('redirect-manager/widgets/stats-summary/settings', [
            'widget' => $this,
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

        // Get analytics data scoped to user's editable sites
        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
        $chartData = RedirectManager::$plugin->analytics->getChartData($editableSiteIds, $this->days);

        // Calculate totals
        $totalHandled = array_sum(array_column($chartData, 'handled'));
        $totalUnhandled = array_sum(array_column($chartData, 'unhandled'));
        $total = $totalHandled + $totalUnhandled;
        $handledPercentage = $total > 0 ? round(($totalHandled / $total) * 100) : 0;

        return Craft::$app->getView()->renderTemplate('redirect-manager/widgets/stats-summary/body', [
            'widget' => $this,
            'totalHandled' => $totalHandled,
            'totalUnhandled' => $totalUnhandled,
            'total' => $total,
            'handledPercentage' => $handledPercentage,
        ]);
    }
}

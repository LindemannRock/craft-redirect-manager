<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\widgets;

use Craft;

/**
 * Shared site filter behavior for Redirect Manager dashboard widgets.
 *
 * @since 5.33.0
 */
trait SiteFilterTrait
{
    /**
     * @var string Selected site ID, or "all" for all editable sites
     */
    public string $siteId = 'all';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function siteOptions(): array
    {
        $options = [
            ['value' => 'all', 'label' => Craft::t('redirect-manager', 'All Sites')],
        ];

        foreach (Craft::$app->getSites()->getEditableSites() as $site) {
            $options[] = [
                'value' => (string) $site->id,
                'label' => $site->name,
            ];
        }

        return $options;
    }

    /**
     * @return int|array<int>|null
     */
    protected function effectiveSiteId(): int|array|null
    {
        if ($this->siteId !== 'all') {
            $siteId = (int) $this->siteId;

            return in_array($siteId, Craft::$app->getSites()->getEditableSiteIds(), true)
                ? $siteId
                : null;
        }

        return Craft::$app->getSites()->getEditableSiteIds();
    }
}

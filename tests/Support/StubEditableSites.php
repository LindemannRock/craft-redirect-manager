<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Support;

use craft\models\Site;
use craft\services\Sites;

/**
 * Delegates normal site resolution while isolating editable-site authority.
 */
final class StubEditableSites extends Sites
{
    /** @param list<int> $editableSiteIds */
    public function __construct(
        private readonly Sites $delegate,
        private readonly array $editableSiteIds,
    ) {
        parent::__construct();
    }

    /** @return list<int> */
    public function getEditableSiteIds(): array
    {
        return $this->editableSiteIds;
    }

    /** @return list<Site> */
    public function getEditableSites(): array
    {
        return array_values(array_filter(
            $this->delegate->getAllSites(),
            fn(Site $site): bool => in_array((int)$site->id, $this->editableSiteIds, true),
        ));
    }

    public function getCurrentSite(): Site
    {
        return $this->delegate->getCurrentSite();
    }

    /** @return list<Site> */
    public function getAllSites(?bool $withDisabled = null): array
    {
        return $this->delegate->getAllSites($withDisabled);
    }

    public function getSiteById(int $siteId, ?bool $withDisabled = null): ?Site
    {
        return $this->delegate->getSiteById($siteId, $withDisabled);
    }

    public function getSiteByHandle(string $siteHandle, ?bool $withDisabled = null): ?Site
    {
        return $this->delegate->getSiteByHandle($siteHandle, $withDisabled);
    }
}

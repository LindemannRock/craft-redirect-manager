<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Support;

use craft\console\User;
use craft\elements\User as UserIdentity;

/**
 * Isolated CP user authority for permission-boundary tests.
 */
final class StubCpUser extends User
{
    private StubCpUserIdentity $stubIdentity;

    /** @param list<string> $permissions */
    public function __construct(array $permissions = [], bool $admin = false)
    {
        $this->stubIdentity = new StubCpUserIdentity($permissions, $admin);
        parent::__construct();
        $this->setIdentity($this->stubIdentity);
    }

    public function getIdentity(bool $autoRenew = true): ?UserIdentity
    {
        return $this->stubIdentity;
    }

    public function checkPermission(string $permissionName): bool
    {
        return $this->stubIdentity->can($permissionName);
    }

    public function getIsAdmin(): bool
    {
        return $this->stubIdentity->admin;
    }
}

/**
 * Isolated identity returned to Twig and Craft authorization consumers.
 */
final class StubCpUserIdentity extends UserIdentity
{
    /** @var array<string, true> */
    private array $permissions;

    /** @param list<string> $permissions */
    public function __construct(array $permissions, bool $admin)
    {
        parent::__construct();
        $this->permissions = array_fill_keys($permissions, true);
        $this->admin = $admin;
    }

    public function can(string $permission): bool
    {
        return $this->admin || isset($this->permissions[$permission]);
    }
}

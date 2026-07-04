<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\SetupService;
use lindemannrock\redirectmanager\tests\TestCase;

/**
 * Pins setup-readiness detection.
 *
 * The IP salt readiness gate must mirror the runtime hash gate in
 * AnalyticsTrackingService (empty / unresolved-placeholder / trimmed-empty all
 * count as "not configured").
 *
 * @since 5.38.0
 */
final class SetupServiceTest extends TestCase
{
    private SetupService $setup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setup = RedirectManager::$plugin->setup;
    }

    public function testIpSaltConfiguredWhenSaltPresent(): void
    {
        $settings = new Settings();
        $settings->ipHashSalt = str_repeat('a', 40);

        self::assertTrue(
            $this->setup->isIpSaltConfigured($settings),
            'A real salt value must count as configured.',
        );
    }

    public function testIpSaltNotConfiguredWhenEmpty(): void
    {
        $settings = new Settings();
        $settings->ipHashSalt = '';

        self::assertFalse(
            $this->setup->isIpSaltConfigured($settings),
            'An empty salt must not count as configured.',
        );
    }

    public function testIpSaltNotConfiguredForUnresolvedPlaceholder(): void
    {
        $settings = new Settings();
        $settings->ipHashSalt = '$REDIRECT_MANAGER_IP_SALT';

        self::assertFalse(
            $this->setup->isIpSaltConfigured($settings),
            'The unresolved default env placeholder must not count as configured.',
        );
    }

    public function testGetStatusCompleteWhenSaltConfigured(): void
    {
        $settings = new Settings();
        $settings->ipHashSalt = str_repeat('a', 40);

        $status = $this->setup->getStatus($settings);

        self::assertTrue($status['complete']);
        self::assertTrue($status['ipSaltConfigured']);
        self::assertSame([], $status['missing']);
    }

    public function testGetStatusIncompleteWhenSaltMissing(): void
    {
        $settings = new Settings();
        $settings->ipHashSalt = '';

        $status = $this->setup->getStatus($settings);

        self::assertFalse($status['complete']);
        self::assertFalse($status['ipSaltConfigured']);
        self::assertSame(['ipSalt'], $status['missing']);
    }
}

<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use craft\helpers\App;
use lindemannrock\redirectmanager\tests\TestCase;

/**
 * Pins local/private IP geo fallback behavior.
 *
 * @since 5.35.0
 */
final class AnalyticsGeoDefaultsTest extends TestCase
{
    private ?string $savedDefaultCountry = null;

    private ?string $savedDefaultCity = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedDefaultCountry = $this->settings()->defaultCountry;
        $this->savedDefaultCity = $this->settings()->defaultCity;
    }

    protected function tearDown(): void
    {
        $this->settings()->defaultCountry = $this->savedDefaultCountry;
        $this->settings()->defaultCity = $this->savedDefaultCity;

        parent::tearDown();
    }

    public function testPrivateIpHasNoGeoLocationWithoutExplicitDefaults(): void
    {
        $this->withoutDefaultLocationEnv(function(): void {
            $this->settings()->defaultCountry = null;
            $this->settings()->defaultCity = null;

            self::assertNull(
                $this->analytics->getLocationFromIp('127.0.0.1'),
                'Private/local IPs must not synthesize a default geo location unless both defaults are configured.',
            );
        });
    }

    public function testPrivateIpUsesExplicitSupportedDefaults(): void
    {
        $this->settings()->defaultCountry = 'US';
        $this->settings()->defaultCity = 'New York';

        $location = $this->analytics->getLocationFromIp('192.168.1.42');

        self::assertIsArray($location);
        self::assertSame('US', $location['countryCode']);
        self::assertSame('New York', $location['city']);
    }

    public function testPrivateIpHasNoGeoLocationForUnsupportedDefaults(): void
    {
        $this->settings()->defaultCountry = 'ZZ';
        $this->settings()->defaultCity = 'Missing City';

        self::assertNull(
            $this->analytics->getLocationFromIp('10.0.0.10'),
            'Unsupported local/private IP geo defaults should leave geo fields empty instead of falling back to Dubai.',
        );
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withoutDefaultLocationEnv(callable $callback): mixed
    {
        $countryServer = $_SERVER['REDIRECT_MANAGER_DEFAULT_COUNTRY'] ?? null;
        $cityServer = $_SERVER['REDIRECT_MANAGER_DEFAULT_CITY'] ?? null;
        $countryEnv = $_ENV['REDIRECT_MANAGER_DEFAULT_COUNTRY'] ?? null;
        $cityEnv = $_ENV['REDIRECT_MANAGER_DEFAULT_CITY'] ?? null;
        $countryEffective = App::env('REDIRECT_MANAGER_DEFAULT_COUNTRY');
        $cityEffective = App::env('REDIRECT_MANAGER_DEFAULT_CITY');

        unset(
            $_SERVER['REDIRECT_MANAGER_DEFAULT_COUNTRY'],
            $_SERVER['REDIRECT_MANAGER_DEFAULT_CITY'],
            $_ENV['REDIRECT_MANAGER_DEFAULT_COUNTRY'],
            $_ENV['REDIRECT_MANAGER_DEFAULT_CITY'],
        );
        putenv('REDIRECT_MANAGER_DEFAULT_COUNTRY');
        putenv('REDIRECT_MANAGER_DEFAULT_CITY');

        try {
            return $callback();
        } finally {
            $this->restoreEnvValue('REDIRECT_MANAGER_DEFAULT_COUNTRY', $countryServer, $countryEnv, $countryEffective);
            $this->restoreEnvValue('REDIRECT_MANAGER_DEFAULT_CITY', $cityServer, $cityEnv, $cityEffective);
        }
    }

    private function restoreEnvValue(string $name, ?string $serverValue, ?string $envValue, mixed $effectiveValue): void
    {
        if ($serverValue !== null) {
            $_SERVER[$name] = $serverValue;
        }

        if ($envValue !== null) {
            $_ENV[$name] = $envValue;
        }

        if (is_string($effectiveValue)) {
            putenv($name . '=' . $effectiveValue);
        } else {
            putenv($name);
        }
    }
}

<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use Craft;
use craft\console\Request as ConsoleRequest;
use craft\services\Sites;
use craft\web\Response;
use lindemannrock\redirectmanager\controllers\SettingsController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;

/**
 * Covers query-string settings in the Test URL Redirects tool.
 *
 * @since 5.41.0
 */
final class SettingsTestUrlQueryStringTest extends TestCase
{
    private int $siteId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        Craft::$app->set('response', new Response());
        Craft::$app->set('sites', new class($this->siteId) extends Sites {
            public function __construct(private readonly int $editableSiteId)
            {
                parent::__construct();
            }

            public function getEditableSiteIds(): array
            {
                return [$this->editableSiteId];
            }
        });
    }

    public function testUrlTesterAppliesStripQueryStringSetting(): void
    {
        $redirect = $this->seedRedirect(['siteId' => $this->siteId]);
        $testUrl = $redirect->sourceUrlParsed . '?utm_source=social';

        $this->settings()->stripQueryString = false;
        self::assertFalse($this->testUrl($testUrl)['matched']);

        $this->settings()->stripQueryString = true;
        $result = $this->testUrl($testUrl);

        self::assertTrue($result['matched']);
        self::assertSame($redirect->id, (int)$result['redirect']['id']);
    }

    public function testUrlTesterAppliesPreserveQueryStringSetting(): void
    {
        $redirect = $this->seedRedirect([
            'destinationUrl' => '/destination?existing=value',
            'siteId' => $this->siteId,
        ]);
        $testUrl = $redirect->sourceUrlParsed . '?utm_source=social';
        $this->settings()->stripQueryString = true;

        $this->settings()->preserveQueryString = false;
        $withoutQuery = $this->testUrl($testUrl);
        self::assertSame('/destination?existing=value', $withoutQuery['redirect']['resolvedDestinationUrl']);

        $this->settings()->preserveQueryString = true;
        $withQuery = $this->testUrl($testUrl);
        self::assertSame(
            '/destination?existing=value&utm_source=social',
            $withQuery['redirect']['resolvedDestinationUrl'],
        );
    }

    /** @return array<string, mixed> */
    private function testUrl(string $testUrl): array
    {
        Craft::$app->set('request', new class(['testUrl' => $testUrl]) extends ConsoleRequest {
            /** @param array<string, mixed> $bodyParams */
            public function __construct(private readonly array $bodyParams)
            {
                parent::__construct();
            }

            public function getBodyParam($name, $defaultValue = null): mixed
            {
                return $this->bodyParams[$name] ?? $defaultValue;
            }

            public function getIsPost(): bool
            {
                return true;
            }

            public function getAcceptsJson(): bool
            {
                return true;
            }

            public function getIsOptions(): bool
            {
                return false;
            }

            public function hasValidSiteToken(): bool
            {
                return false;
            }
        });

        $response = (new class('settings', RedirectManager::$plugin) extends SettingsController {
            public function requirePermission(string $permissionName): void
            {
            }
        })->actionTestUrl();

        self::assertIsArray($response->data);
        return $response->data;
    }
}

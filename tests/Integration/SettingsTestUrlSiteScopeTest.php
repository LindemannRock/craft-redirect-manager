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
 * Pins the Test URL Redirects editable-site scope.
 *
 * @since 5.34.0
 */
final class SettingsTestUrlSiteScopeTest extends TestCase
{
    private ?object $savedRequest = null;

    private ?object $savedResponse = null;

    private ?object $savedSites = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedRequest = Craft::$app->getRequest();
        $this->savedResponse = Craft::$app->getResponse();
        $this->savedSites = Craft::$app->getSites();

        Craft::$app->set('response', new Response());
    }

    protected function tearDown(): void
    {
        if ($this->savedRequest !== null) {
            Craft::$app->set('request', $this->savedRequest);
        }
        if ($this->savedResponse !== null) {
            Craft::$app->set('response', $this->savedResponse);
        }
        if ($this->savedSites !== null) {
            Craft::$app->set('sites', $this->savedSites);
        }

        parent::tearDown();
    }

    public function testUrlTestExcludesRedirectsOutsideEditableSites(): void
    {
        $siteRedirect = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'site-scope/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'site-scope/*',
            'matchType' => 'wildcard',
            'destinationUrl' => '/site-specific',
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
            'priority' => 1,
        ]);
        $globalRedirect = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'site-scope-global/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'site-scope-global/*',
            'matchType' => 'wildcard',
            'destinationUrl' => '/global',
            'priority' => 2,
        ]);
        $globalRedirect->siteId = null;
        $globalRedirect->sourceUrl = '/' . self::MARKER . 'site-scope/*';
        $globalRedirect->sourceUrlParsed = '/' . self::MARKER . 'site-scope/*';
        self::assertTrue($globalRedirect->save(false));

        Craft::$app->set('sites', new class() extends Sites {
            public function getEditableSiteIds(): array
            {
                return [];
            }
        });
        Craft::$app->set('request', new class([
            'testUrl' => '/' . TestCase::MARKER . 'site-scope/page',
        ]) extends ConsoleRequest {
            /**
             * @param array<string, mixed> $bodyParams
             */
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
        self::assertTrue($response->data['matched']);
        self::assertSame((int)$globalRedirect->id, (int)$response->data['redirect']['id']);
        self::assertNotSame((int)$siteRedirect->id, (int)$response->data['redirect']['id']);
        self::assertSame([], $response->data['alsoMatches']);
    }
}

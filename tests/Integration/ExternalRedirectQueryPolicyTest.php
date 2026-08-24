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
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers effective query settings for external redirect consumers.
 *
 * @since 5.41.0
 */
final class ExternalRedirectQueryPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->settings()->enableAnalytics = false;
    }

    #[DataProvider('consumerProvider')]
    public function testExternalConsumerRetainsQueryForMatchingWhenConfigured(string $consumer): void
    {
        $token = bin2hex(random_bytes(4));
        $path = '/' . self::MARKER . 'external_query_' . $token;
        $queryUrl = $path . '?campaign=summer';
        $redirect = $this->seedRedirect([
            'sourceUrl' => $queryUrl,
            'sourceUrlParsed' => $queryUrl,
        ]);
        $this->settings()->stripQueryString = false;

        $result = $this->redirects->handleExternal404($queryUrl, ['source' => $consumer]);

        self::assertNotNull($result);
        self::assertSame($redirect->id, (int)$result['id']);
    }

    public function testExternalConsumerStripsQueryForMatchingWhenConfigured(): void
    {
        $redirect = $this->seedRedirect();
        $queryUrl = (string)$redirect->sourceUrlParsed . '?campaign=summer';
        $this->settings()->stripQueryString = true;

        $result = $this->redirects->handleExternal404($queryUrl, ['source' => 'smartlink-manager']);

        self::assertNotNull($result);
        self::assertSame($redirect->id, (int)$result['id']);
    }

    public function testExternalConsumerPreservesQueryBeforeDestinationFragment(): void
    {
        $redirect = $this->seedRedirect([
            'destinationUrl' => '/destination?existing=value#section',
        ]);
        $queryUrl = (string)$redirect->sourceUrlParsed . '?campaign=summer';
        $this->settings()->stripQueryString = true;
        $this->settings()->preserveQueryString = true;

        $result = $this->redirects->handleExternal404($queryUrl, ['source' => 'shortlink-manager']);

        self::assertNotNull($result);
        self::assertSame('/destination?existing=value&campaign=summer#section', $result['destinationUrl']);
    }

    public function testExternalFullUrlRuleRetainsQueryForMatching(): void
    {
        $token = bin2hex(random_bytes(4));
        $url = 'https://example.test/' . self::MARKER . 'external_full_' . $token . '?campaign=summer';
        $redirect = $this->seedRedirect([
            'sourceUrl' => $url,
            'sourceUrlParsed' => $url,
            'redirectSrcMatch' => 'fullurl',
        ]);
        $this->settings()->stripQueryString = false;

        $result = $this->redirects->handleExternal404($url, ['source' => 'smartlink-manager']);

        self::assertNotNull($result);
        self::assertSame($redirect->id, (int)$result['id']);
    }

    public function testExternalConsumerUsesSiteSpecificPrecedence(): void
    {
        $siteId = (int)Craft::$app->getSites()->getCurrentSite()->id;
        $token = bin2hex(random_bytes(4));
        $path = '/' . self::MARKER . 'external_site_' . $token;
        $global = $this->seedRedirect([
            'sourceUrl' => $path,
            'sourceUrlParsed' => $path,
            'destinationUrl' => '/global',
            'priority' => 0,
        ]);
        $global->siteId = null;
        self::assertTrue($global->save(false));
        $site = $this->seedRedirect([
            'sourceUrl' => $path,
            'sourceUrlParsed' => $path,
            'destinationUrl' => '/site',
            'priority' => 9,
            'siteId' => $siteId,
        ]);

        $result = $this->redirects->handleExternal404($path, ['source' => 'shortlink-manager']);

        self::assertNotNull($result);
        self::assertSame($site->id, (int)$result['id']);
        self::assertSame(0, $this->fetchHitCountFromDb((int)$global->id));
        self::assertSame(1, $this->fetchHitCountFromDb((int)$site->id));
    }

    public function testCachedWinnerUsesTheCurrentRequestsPreservedQuery(): void
    {
        $redirect = $this->seedRedirect(['destinationUrl' => '/destination#details']);
        $settings = $this->settings();
        $settings->stripQueryString = true;
        $settings->preserveQueryString = true;
        $settings->enableRedirectCache = true;
        $settings->cacheStorageMethod = 'file';

        $first = $this->redirects->findRedirect(
            'https://example.test' . $redirect->sourceUrlParsed . '?campaign=first',
            $redirect->sourceUrlParsed . '?campaign=first',
        );
        $second = $this->redirects->findRedirect(
            'https://example.test' . $redirect->sourceUrlParsed . '?campaign=second',
            $redirect->sourceUrlParsed . '?campaign=second',
        );

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame('/destination?campaign=first#details', $first['destinationUrl']);
        self::assertSame('/destination?campaign=second#details', $second['destinationUrl']);
        self::assertSame(1, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
        self::assertSame(2, $this->fetchHitCountFromDb((int)$redirect->id));
    }

    /** @return iterable<string, array{string}> */
    public static function consumerProvider(): iterable
    {
        yield 'Shortlink Manager' => ['shortlink-manager'];
        yield 'Smartlink Manager' => ['smartlink-manager'];
    }
}

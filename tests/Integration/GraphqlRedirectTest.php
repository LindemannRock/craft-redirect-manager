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
use GraphQL\Type\Definition\ResolveInfo;
use lindemannrock\base\testing\StubConsoleRequest;
use lindemannrock\redirectmanager\gql\queries\RedirectQuery;
use lindemannrock\redirectmanager\gql\resolvers\RedirectResolver;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\tests\TestCase;
use yii\base\Request as YiiRequest;

/**
 * Covers Redirect Manager's GraphQL resolver contract.
 *
 * @since 5.33.0
 */
final class GraphqlRedirectTest extends TestCase
{
    private const TEST_SALT = '0123456789abcdef0123456789abcdef';

    private ?YiiRequest $savedRequest = null;

    private bool $savedCacheEnabled = false;

    private bool $savedEnableAnalytics = true;

    private bool $savedAutoTrimAnalytics = true;

    private bool $savedEnableGeo = false;

    private bool $savedAnonymize = false;

    private ?string $savedSalt = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedRequest = Craft::$app->getRequest();
        Craft::$app->set('request', new StubConsoleRequest(userIp: '203.0.113.42'));

        $settings = $this->settings();
        $this->savedCacheEnabled = $settings->enableRedirectCache;
        $this->savedEnableAnalytics = $settings->enableAnalytics;
        $this->savedAutoTrimAnalytics = $settings->autoTrimAnalytics;
        $this->savedEnableGeo = $settings->enableGeoDetection;
        $this->savedAnonymize = $settings->anonymizeIpAddress;
        $this->savedSalt = $settings->ipHashSalt;

        $settings->enableRedirectCache = false;
        $settings->enableAnalytics = true;
        $settings->autoTrimAnalytics = false;
        $settings->enableGeoDetection = false;
        $settings->anonymizeIpAddress = false;
        $settings->ipHashSalt = self::TEST_SALT;
    }

    protected function tearDown(): void
    {
        if ($this->savedRequest !== null) {
            Craft::$app->set('request', $this->savedRequest);
        }

        $settings = $this->settings();
        $settings->enableRedirectCache = $this->savedCacheEnabled;
        $settings->enableAnalytics = $this->savedEnableAnalytics;
        $settings->autoTrimAnalytics = $this->savedAutoTrimAnalytics;
        $settings->enableGeoDetection = $this->savedEnableGeo;
        $settings->anonymizeIpAddress = $this->savedAnonymize;
        $settings->ipHashSalt = $this->savedSalt;

        parent::tearDown();
    }

    public function testQueryDefinitionsExposeResolveAndListQueriesWithoutTokenCheck(): void
    {
        $queries = RedirectQuery::getQueries(false);

        self::assertArrayHasKey('redirectManagerResolveRedirect', $queries);
        self::assertArrayHasKey('redirectManagerRedirects', $queries);
        self::assertArrayHasKey('uri', $queries['redirectManagerResolveRedirect']['args']);
        self::assertArrayHasKey('site', $queries['redirectManagerResolveRedirect']['args']);
        self::assertArrayHasKey('siteId', $queries['redirectManagerResolveRedirect']['args']);
    }

    public function testQueryDefinitionsAreSchemaPermissionGated(): void
    {
        self::assertSame([], RedirectQuery::getQueries());
    }

    public function testResolveQueryMatchesRedirectAndRecordsHandledAnalytics(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $redirect = $this->seedRedirect(['siteId' => $site->id]);

        $result = RedirectResolver::resolve(
            null,
            [
                'uri' => $redirect->sourceUrlParsed,
                'site' => $site->handle,
            ],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertIsArray($result);
        self::assertSame($redirect->id, $result['id']);
        self::assertSame(1, (int)$result['hitCount']);
        self::assertNotEmpty($result['lastHit']);
        self::assertSame(1, $this->fetchHitCountFromDb($redirect->id));

        $analytics = $this->fetchRow('{{%redirectmanager_analytics}}', [
            'urlParsed' => $redirect->sourceUrlParsed,
            'siteId' => $site->id,
        ]);
        self::assertNotNull($analytics, 'GraphQL resolution must record handled analytics.');
        self::assertSame(1, (int)$analytics['handled']);
        self::assertSame($redirect->id, (int)$analytics['redirectId']);
        self::assertSame('graphql', $analytics['sourcePlugin']);
    }

    public function testResolveQueryRecordsUnhandledAnalytics(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $uri = '/' . self::MARKER . 'graphql_missing_' . substr(uniqid('', true), -8);

        $result = RedirectResolver::resolve(
            null,
            [
                'uri' => $uri,
                'siteId' => $site->id,
            ],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertNull($result);

        $analytics = $this->fetchRow('{{%redirectmanager_analytics}}', [
            'urlParsed' => $uri,
            'siteId' => $site->id,
        ]);
        self::assertNotNull($analytics, 'GraphQL misses must record unhandled analytics.');
        self::assertSame(0, (int)$analytics['handled']);
        self::assertNull($analytics['redirectId']);
        self::assertSame('graphql', $analytics['sourcePlugin']);
    }

    public function testRedirectListQueryIsReadOnly(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $redirect = $this->seedRedirect(['siteId' => $site->id]);
        $globalRedirect = $this->seedRedirect(['siteId' => null]);

        $results = RedirectResolver::resolveAll(
            null,
            ['siteId' => $site->id],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertIsArray($results);
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $results);
        self::assertContains($redirect->id, $ids);
        self::assertContains($globalRedirect->id, $ids);
        self::assertSame(0, $this->fetchHitCountFromDb($redirect->id));
        self::assertSame(0, $this->fetchHitCountFromDb($globalRedirect->id));
    }

    public function testInvalidExplicitSiteDoesNotFallBack(): void
    {
        $this->seedRedirect();

        $result = RedirectResolver::resolveAll(
            null,
            ['site' => '__missing_site__'],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertSame([], $result);
    }
}

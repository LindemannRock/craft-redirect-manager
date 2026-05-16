<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests;

use Craft;
use lindemannrock\base\testing\IntegrationTestCase;
use lindemannrock\redirectmanager\models\Settings;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\AnalyticsService;
use lindemannrock\redirectmanager\services\MatchingService;
use lindemannrock\redirectmanager\services\RedirectsService;

/**
 * Base test case for redirect-manager integration tests.
 *
 * Extends the shared {@see IntegrationTestCase} for component snapshot/restore
 * and generic Query helpers, and layers plugin-specific shorthand on top:
 *  - direct accessors for `matching` / `redirects` / `analytics` services
 *  - per-test marker prefix + DB purge helpers covering both the redirects
 *    table and the analytics table (no FK linkage — both are purged directly
 *    by LIKE on their parsed-URL columns)
 *  - {@see seedRedirect()} convenience for inserting a marker-tagged row
 *    via the raw `RedirectRecord` (the service's `createRedirect()` runs full
 *    validation + duplicate + loop checks, which several tests want to drive
 *    explicitly rather than implicitly)
 *
 * Subclasses can override `setUp()` for additional fixture work but should
 * call `parent::setUp()` to keep marker-based isolation working.
 *
 * @since 5.30.0
 */
abstract class TestCase extends IntegrationTestCase
{
    /**
     * Marker prefix used for every test-seeded redirect source URL.
     *
     * The redirect's `sourceUrlParsed` column is the natural cleanup hook —
     * every match path (cache key, dedup index, `findRedirect()` query) goes
     * through it, and the marker can ride along as a path segment so the row
     * still satisfies `RedirectRecord::validateSourceUrl()` for `pathonly`
     * mode (which requires a leading `/`). The same marker travels onto the
     * analytics table's `urlParsed` column whenever a 404 is recorded against
     * a marker URL, so a single LIKE prefix drains both tables.
     */
    protected const MARKER = '__rdr_test_';

    protected MatchingService $matching;

    protected RedirectsService $redirects;

    protected AnalyticsService $analytics;

    private int $seedCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matching = RedirectManager::$plugin->matching;
        $this->redirects = RedirectManager::$plugin->redirects;
        $this->analytics = RedirectManager::$plugin->analytics;
        $this->seedCounter = 0;
        $this->purgeTestRows();
    }

    protected function tearDown(): void
    {
        $this->purgeTestRows();
        parent::tearDown();
    }

    /**
     * Override hook called from `IntegrationTestCase::tearDown()` BEFORE
     * component restoration. Invalidate the file/Redis redirect cache so
     * stale matches from one test never bleed into the next.
     */
    protected function cleanupExternalState(): void
    {
        $this->redirects->invalidateCaches();
    }

    /**
     * Seed a saved {@see RedirectRecord} tagged with a marker so cleanup can
     * find it. Built directly (not via `RedirectsService::createRedirect()`)
     * because the service runs duplicate + loop guards that several tests
     * want to drive explicitly. Defaults match the `pathonly` + `exact`
     * shape that covers the vast majority of redirects in the wild.
     *
     * @param array<string, mixed> $overrides
     */
    protected function seedRedirect(array $overrides = []): RedirectRecord
    {
        $this->seedCounter++;
        $marker = '/' . self::MARKER . $this->seedCounter . '_' . substr(uniqid('', true), -8);

        $record = new RedirectRecord();
        $record->sourceUrl = $overrides['sourceUrl'] ?? $marker;
        $record->sourceUrlParsed = $overrides['sourceUrlParsed'] ?? $record->sourceUrl;
        $record->destinationUrl = $overrides['destinationUrl'] ?? '/destination';
        $record->matchType = $overrides['matchType'] ?? 'exact';
        $record->redirectSrcMatch = $overrides['redirectSrcMatch'] ?? 'pathonly';
        $record->statusCode = $overrides['statusCode'] ?? 301;
        $record->siteId = $overrides['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id;
        $record->enabled = $overrides['enabled'] ?? true;
        $record->priority = $overrides['priority'] ?? 0;
        $record->creationType = $overrides['creationType'] ?? 'manual';
        $record->sourcePlugin = $overrides['sourcePlugin'] ?? 'redirect-manager';
        $record->elementId = $overrides['elementId'] ?? null;
        $record->hitCount = $overrides['hitCount'] ?? 0;

        $this->assertTrue(
            $record->save(false),
            'Seeded redirect must save — errors: ' . json_encode($record->getErrors()),
        );

        return $record;
    }

    /**
     * Reload a redirect from the DB and return the persisted `hitCount`.
     * Bypasses any in-memory model state the service might hold.
     */
    protected function fetchHitCountFromDb(int $id): int
    {
        $row = $this->fetchRow(RedirectRecord::tableName(), ['id' => $id]);
        $this->assertNotNull($row, "Redirect row {$id} not found.");

        return (int) $row['hitCount'];
    }

    /**
     * Plugin settings shorthand for tests that need to flip a setting on
     * the in-memory model (no DB write required — `RedirectManager::$plugin`
     * holds a single Settings instance and the services read from it live).
     */
    protected function settings(): Settings
    {
        /** @var Settings $settings */
        $settings = RedirectManager::$plugin->getSettings();
        return $settings;
    }

    /**
     * Wipe every redirect + analytics row that still carries the marker.
     * The redirect table has no FK CASCADE into analytics, so we purge both
     * directly. Analytics rows for marker URLs are matched on `urlParsed`,
     * which is what `AnalyticsTrackingService::record404()` writes when given
     * one of our test URLs.
     */
    protected function purgeTestRows(): void
    {
        $this->purgeRowsByMarker(
            RedirectRecord::tableName(),
            'sourceUrlParsed',
            '/' . self::MARKER,
        );
        $this->purgeRowsByMarker(
            '{{%redirectmanager_analytics}}',
            'urlParsed',
            '/' . self::MARKER,
        );
    }
}

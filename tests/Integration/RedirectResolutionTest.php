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
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\tests\TestCase;

/**
 * End-to-end resolution: a stored redirect — every match type (`exact`,
 * `prefix`, `wildcard`, `regex`) × every source match mode (`pathonly`,
 * `fullurl`) — resolved through {@see \lindemannrock\redirectmanager\services\RedirectsService::findRedirect()}
 * and capture substitution, exactly as a live 404 flows (minus the HTTP send).
 *
 * Each case seeds a real DB row and drives the real service, so it pins the
 * whole match pipeline: candidate query → per-redirect mode selection
 * (`pathonly` vs `fullurl`) → `matchWithCaptures()` → `$0`/`$1`/`$N`
 * substitution into the destination. Both matching and non-matching inputs
 * are asserted so a too-greedy or too-strict matcher fails here.
 *
 * The redirect match cache is disabled so every call walks the DB-match
 * branch deterministically (the cache-hit path is covered separately).
 *
 * @since 5.33.0
 */
final class RedirectResolutionTest extends TestCase
{
    private bool $savedCacheEnabled = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedCacheEnabled = $this->settings()->enableRedirectCache;
        $this->settings()->enableRedirectCache = false;
    }

    protected function tearDown(): void
    {
        $this->settings()->enableRedirectCache = $this->savedCacheEnabled;
        // fullurl + regex rows store a full URL / `^…`-anchored pattern in
        // sourceUrlParsed, which the prefix-anchored marker purge misses.
        // Drain anything carrying the marker anywhere in the column.
        Craft::$app->getDb()->createCommand()
            ->delete(RedirectRecord::tableName(), ['like', 'sourceUrlParsed', '%' . self::MARKER . '%', false])
            ->execute();
        parent::tearDown();
    }

    /**
     * Seed a redirect, resolve an incoming request through findRedirect(), and
     * return the fully-substituted destination — or null if it did not match
     * the seeded row.
     *
     * @param array<string, mixed> $seed
     */
    private function resolve(array $seed, string $fullUrl, string $pathOnly): ?string
    {
        $record = $this->seedRedirect($seed);
        $match = $this->redirects->findRedirect($fullUrl, $pathOnly);

        if ($match === null || (int)$match['id'] !== (int)$record->id) {
            return null;
        }

        return $this->matching->applyCaptures($match['destinationUrl'], $match['_captures'] ?? []);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function seed(string $source, string $destination, string $matchType, string $mode, array $extra = []): array
    {
        return array_merge([
            'sourceUrl' => $source,
            'sourceUrlParsed' => $source,
            'destinationUrl' => $destination,
            'matchType' => $matchType,
            'redirectSrcMatch' => $mode,
        ], $extra);
    }

    // Exact ──────────────────────────────────────────────────────────────

    public function testExactPathOnlyMatches(): void
    {
        $seed = $this->seed('/__rdr_test_exactp', '/dest-exact-p', 'exact', 'pathonly');
        self::assertSame('/dest-exact-p', $this->resolve($seed, 'https://example.com/__rdr_test_exactp', '/__rdr_test_exactp'));
    }

    public function testExactPathOnlyIsCaseInsensitive(): void
    {
        $seed = $this->seed('/__rdr_test_exactc', '/dest-exact-c', 'exact', 'pathonly');
        self::assertSame('/dest-exact-c', $this->resolve($seed, 'https://example.com/__RDR_TEST_EXACTC', '/__RDR_TEST_EXACTC'));
    }

    public function testExactPathOnlyRejectsSubpath(): void
    {
        $seed = $this->seed('/__rdr_test_exactr', '/dest', 'exact', 'pathonly');
        self::assertNull($this->resolve($seed, 'https://example.com/__rdr_test_exactr/sub', '/__rdr_test_exactr/sub'));
    }

    public function testExactFullUrlMatchesHostAndPath(): void
    {
        $seed = $this->seed('https://example.com/__rdr_test_exactf', '/dest-exact-f', 'exact', 'fullurl');
        self::assertSame('/dest-exact-f', $this->resolve($seed, 'https://example.com/__rdr_test_exactf', '/__rdr_test_exactf'));
    }

    public function testExactFullUrlRejectsDifferentHost(): void
    {
        $seed = $this->seed('https://example.com/__rdr_test_exactfh', '/dest', 'exact', 'fullurl');
        self::assertNull($this->resolve($seed, 'https://other.com/__rdr_test_exactfh', '/__rdr_test_exactfh'));
    }

    // Prefix ─────────────────────────────────────────────────────────────

    public function testPrefixPathOnlyAppendsRemainder(): void
    {
        $seed = $this->seed('/__rdr_test_pre', '/new$1', 'prefix', 'pathonly');
        self::assertSame('/new/a/b', $this->resolve($seed, 'https://example.com/__rdr_test_pre/a/b', '/__rdr_test_pre/a/b'));
    }

    public function testPrefixFullUrlAppendsRemainder(): void
    {
        $seed = $this->seed('https://example.com/__rdr_test_pref', '/new$1', 'prefix', 'fullurl');
        self::assertSame('/new/x', $this->resolve($seed, 'https://example.com/__rdr_test_pref/x', '/__rdr_test_pref/x'));
    }

    public function testPrefixRejectsNonPrefix(): void
    {
        $seed = $this->seed('/__rdr_test_pre2', '/new$1', 'prefix', 'pathonly');
        self::assertNull($this->resolve($seed, 'https://example.com/__rdr_test_other', '/__rdr_test_other'));
    }

    // Wildcard ───────────────────────────────────────────────────────────

    public function testWildcardPathOnlySingleStar(): void
    {
        $seed = $this->seed('/__rdr_test_wild/*', '/new/$1', 'wildcard', 'pathonly');
        self::assertSame('/new/abc/def', $this->resolve($seed, 'https://example.com/__rdr_test_wild/abc/def', '/__rdr_test_wild/abc/def'));
    }

    public function testWildcardPathOnlyRejectsMissingSegment(): void
    {
        // `/__rdr_test_wild2/*` becomes `^/__rdr_test_wild2/(.*)$` — the trailing
        // slash is required, so the bare prefix must NOT match.
        $seed = $this->seed('/__rdr_test_wild2/*', '/new/$1', 'wildcard', 'pathonly');
        self::assertNull($this->resolve($seed, 'https://example.com/__rdr_test_wild2', '/__rdr_test_wild2'));
    }

    public function testWildcardFullUrl(): void
    {
        $seed = $this->seed('https://example.com/__rdr_test_wf/*', '/new/$1', 'wildcard', 'fullurl');
        self::assertSame('/new/x/y', $this->resolve($seed, 'https://example.com/__rdr_test_wf/x/y', '/__rdr_test_wf/x/y'));
    }

    public function testWildcardMultipleStarsMapToOrderedCaptures(): void
    {
        $seed = $this->seed('/__rdr_test_m/*/p/*', '/$1/$2', 'wildcard', 'pathonly');
        self::assertSame('/users/42', $this->resolve($seed, 'https://example.com/__rdr_test_m/users/p/42', '/__rdr_test_m/users/p/42'));
    }

    // Regex ──────────────────────────────────────────────────────────────

    public function testRegexPathOnlyMultipleCaptures(): void
    {
        $seed = $this->seed('^/__rdr_test_rx/(\d+)/(.*)$', '/article/$1/$2', 'regex', 'pathonly');
        self::assertSame('/article/2024/my-post', $this->resolve($seed, 'https://example.com/__rdr_test_rx/2024/my-post', '/__rdr_test_rx/2024/my-post'));
    }

    public function testRegexFullUrl(): void
    {
        $seed = $this->seed('^https://example\.com/__rdr_test_rf/(\d+)$', '/item/$1', 'regex', 'fullurl');
        self::assertSame('/item/42', $this->resolve($seed, 'https://example.com/__rdr_test_rf/42', '/__rdr_test_rf/42'));
    }

    public function testRegexRejectsNonMatch(): void
    {
        // `(\d+)` must not match a non-numeric segment.
        $seed = $this->seed('^/__rdr_test_rz/(\d+)$', '/item/$1', 'regex', 'pathonly');
        self::assertNull($this->resolve($seed, 'https://example.com/__rdr_test_rz/abc', '/__rdr_test_rz/abc'));
    }

    public function testRegexDollarZeroSubstitutesFullMatch(): void
    {
        $seed = $this->seed('^/__rdr_test_z/.+$', '/log$0', 'regex', 'pathonly');
        self::assertSame('/log/__rdr_test_z/abc', $this->resolve($seed, 'https://example.com/__rdr_test_z/abc', '/__rdr_test_z/abc'));
    }
}

<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\redirectmanager\tests\TestCase;

/**
 * Pins the capture-extraction contract for
 * {@see \lindemannrock\redirectmanager\services\MatchingService::matchWithCaptures()}
 * and the destination-template substitution in {@see \lindemannrock\redirectmanager\services\MatchingService::applyCaptures()}.
 *
 * MatchingService is pure logic — no DB, no Craft state, no caching — so these
 * cases pin behaviour rather than integration. The shape matters because every
 * redirect (auto-generated, manually authored, GraphQL-driven) flows through
 * `matchWithCaptures()` and the captured groups drive `applyCaptures()` for
 * `$1`/`$2` substitution into destination URLs. A regression in either method
 * silently breaks every wildcard + regex redirect in production.
 *
 * @since 5.30.0
 */
final class MatchingResolutionTest extends TestCase
{
    public function testExactMatchIsCaseInsensitiveAndReturnsUrlAsCapture0(): void
    {
        $result = $this->matching->matchWithCaptures('exact', '/About-Us', '/about-us');

        $this->assertTrue($result['matched']);
        $this->assertSame(['/about-us'], $result['captures']);
    }

    public function testRegexMatchReturnsFullMatchAndGroupedCaptures(): void
    {
        // /blog/2024/my-post → captures '2024' as $1 and 'my-post' as $2
        $result = $this->matching->matchWithCaptures(
            'regex',
            '^/blog/(\d{4})/([^/]+)$',
            '/blog/2024/my-post',
        );

        $this->assertTrue($result['matched']);
        $this->assertSame('/blog/2024/my-post', $result['captures'][0], '$0 is the full match.');
        $this->assertSame('2024', $result['captures'][1]);
        $this->assertSame('my-post', $result['captures'][2]);
    }

    public function testWildcardMatchTurnsEachStarIntoACaptureGroup(): void
    {
        // /docs/* matches /docs/v2/intro — captures 'v2/intro' as $1
        $single = $this->matching->matchWithCaptures('wildcard', '/docs/*', '/docs/v2/intro');
        $this->assertTrue($single['matched']);
        $this->assertSame('v2/intro', $single['captures'][1]);

        // Multiple wildcards each produce their own capture group
        $multi = $this->matching->matchWithCaptures('wildcard', '/*/posts/*', '/users/posts/42');
        $this->assertTrue($multi['matched']);
        $this->assertSame('users', $multi['captures'][1]);
        $this->assertSame('42', $multi['captures'][2]);
    }

    public function testPrefixMatchCapturesRemainderAfterPrefix(): void
    {
        $result = $this->matching->matchWithCaptures('prefix', '/old-section', '/old-section/page/42');

        $this->assertTrue($result['matched']);
        $this->assertSame('/old-section/page/42', $result['captures'][0], '$0 is the full URL.');
        $this->assertSame('/page/42', $result['captures'][1], '$1 is the post-prefix remainder.');
    }

    public function testUnknownMatchTypeReturnsEmptyResult(): void
    {
        $result = $this->matching->matchWithCaptures('contains', '/anything', '/anything-else');

        $this->assertFalse($result['matched']);
        $this->assertSame([], $result['captures']);
    }

    public function testApplyCapturesReplacesNumericPlaceholdersHighestIndexFirst(): void
    {
        // 10 captures total — $0 = full match, $1..$10 = groups.
        // The implementation must iterate highest-to-lowest so `$1` doesn't
        // greedily eat the leading digit of `$10` before `$10` is replaced.
        $captures = ['FULL', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];
        $result = $this->matching->applyCaptures('/$10/$1/$2', $captures);

        $this->assertSame('/ten/one/two', $result);
    }

    public function testApplyCapturesRemovesUnreferencedPlaceholdersAndCollapsesDoubleSlashes(): void
    {
        // The destination references $1 and $5, but only $1 is captured.
        // $5 must be stripped (not left as literal text) and the resulting
        // empty segment must NOT leave `//` in the path.
        $captures = ['/foo/bar', 'foo'];
        $result = $this->matching->applyCaptures('/new/$1/$5/done', $captures);

        $this->assertSame('/new/foo/done', $result);
    }

    public function testApplyCapturesPreservesSchemeSlashesInAbsoluteUrls(): void
    {
        // The double-slash collapse must NOT eat the `://` in `https://`.
        // Capture leaves nothing after the host, but the scheme stays intact.
        $captures = ['/page', ''];
        $result = $this->matching->applyCaptures('https://example.com/$1page', $captures);

        $this->assertSame('https://example.com/page', $result);
    }

    public function testApplyCapturesSubstitutesDollarZeroFullMatch(): void
    {
        // $0 is the full matched string and must substitute like any other index.
        $result = $this->matching->applyCaptures('/log$0', ['/blog/2024/post']);

        $this->assertSame('/log/blog/2024/post', $result);
    }

    public function testRegexMatchingIsCaseInsensitive(): void
    {
        // Patterns are compiled with the `i` flag.
        $result = $this->matching->matchWithCaptures('regex', '^/ABOUT/(.*)$', '/about/team');

        $this->assertTrue($result['matched']);
        $this->assertSame('team', $result['captures'][1]);
    }

    public function testRegexCharacterClassCaptureAndAnchoredNonMatch(): void
    {
        // `[^/]+` captures a single segment; the `$` anchor must reject a deeper path.
        $match = $this->matching->matchWithCaptures('regex', '^/p/([^/]+)$', '/p/widget');
        $this->assertTrue($match['matched']);
        $this->assertSame('widget', $match['captures'][1]);

        $noMatch = $this->matching->matchWithCaptures('regex', '^/p/([^/]+)$', '/p/widget/extra');
        $this->assertFalse($noMatch['matched']);
    }

    public function testRegexAlternationCaptures(): void
    {
        $result = $this->matching->matchWithCaptures('regex', '^/(cats|dogs)/(.+)$', '/dogs/rex');

        $this->assertTrue($result['matched']);
        $this->assertSame('dogs', $result['captures'][1]);
        $this->assertSame('rex', $result['captures'][2]);
    }
}

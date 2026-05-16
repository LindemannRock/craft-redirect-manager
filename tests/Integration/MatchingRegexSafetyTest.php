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
 * Pins the ReDoS guard in {@see \lindemannrock\redirectmanager\services\MatchingService::matchWithCaptures()}.
 *
 * Regex patterns come from user input via the CP. Without a safety filter,
 * a single hostile pattern like `(a+)+` against a long URL can pin a PHP
 * worker for seconds. The private `validateRegexPattern()` rejects three
 * dangerous shapes — nested quantifiers, alternation-with-quantifier, and
 * overlong patterns — and the public matcher returns
 * `['matched' => false, 'captures' => []]` for any rejection. This test pins
 * those rejections so a regression to "accept anything that compiles" would
 * fail in CI.
 *
 * @since 5.30.0
 */
final class MatchingRegexSafetyTest extends TestCase
{
    public function testNestedQuantifierPatternIsRejected(): void
    {
        // Consecutive quantifiers like `.*++` or `[abc]**` are caught by the
        // guard's nested-quantifier regex (`\.\*[*+]\)?[*+]`). They're also
        // syntactically invalid PCRE, but the validator rejects them before
        // PCRE ever sees them — which is the contract we pin here.
        $result = $this->matching->matchWithCaptures('regex', '.*++', '/foo/bar');

        $this->assertFalse($result['matched']);
        $this->assertSame([], $result['captures']);
    }

    public function testAlternationWithQuantifierIsRejected(): void
    {
        // Patterns like `(a|b)*` have the same exponential failure mode on
        // mis-matched inputs. The guard rejects them before they reach PCRE.
        $result = $this->matching->matchWithCaptures('regex', '(a|b)*', '/foo');

        $this->assertFalse($result['matched']);
        $this->assertSame([], $result['captures']);
    }

    public function testOverlongPatternIsRejected(): void
    {
        // Patterns longer than 500 chars are rejected on the assumption that
        // a hand-authored redirect rarely needs more — and a generated one
        // is more likely to be hostile than legitimate at that size.
        $pattern = '/' . str_repeat('a', 600);
        $result = $this->matching->matchWithCaptures('regex', $pattern, '/aaaa');

        $this->assertFalse($result['matched']);
        $this->assertSame([], $result['captures']);
    }
}

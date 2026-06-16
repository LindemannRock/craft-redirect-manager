<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Destination validation is shared by the CP form and CSV import via
 * {@see RedirectRecord::isValidDestination()} and
 * {@see RedirectRecord::captureReferenceError()} so the two surfaces can't drift.
 * The format rule rejects executable schemes and protocol-relative `//host`
 * while accepting the contact/app protocols and capture references redirects
 * legitimately use; the capture rule rejects references the match type / source
 * pattern can't produce.
 *
 * @since 5.32.2
 */
#[CoversClass(RedirectRecord::class)]
final class ImportUrlValidationTest extends TestCase
{
    public function testRejectsExecutableSchemes(): void
    {
        self::assertFalse(RedirectRecord::isValidDestination('javascript:alert(1)'));
        self::assertFalse(RedirectRecord::isValidDestination('javascript://%0aalert(1)'));
        self::assertFalse(RedirectRecord::isValidDestination('data:text/html,<script>alert(1)</script>'));
        self::assertFalse(RedirectRecord::isValidDestination('vbscript:msgbox(1)'));
        self::assertFalse(RedirectRecord::isValidDestination('file:///etc/passwd'));
        self::assertFalse(RedirectRecord::isValidDestination("java\tscript:alert(1)"));
    }

    public function testAcceptsSupportedDestinations(): void
    {
        self::assertTrue(RedirectRecord::isValidDestination('https://example.com'));
        self::assertTrue(RedirectRecord::isValidDestination('/relative/path'));
        self::assertTrue(RedirectRecord::isValidDestination('mailto:x@y.com'));
        self::assertTrue(RedirectRecord::isValidDestination('tel:+15551234567'));
        self::assertTrue(RedirectRecord::isValidDestination('whatsapp:+15551234567'));
        self::assertTrue(RedirectRecord::isValidDestination('slack://channel'));
        self::assertTrue(RedirectRecord::isValidDestination('msteams:chat'));
        // Capture reference used by regex/wildcard/prefix redirects.
        self::assertTrue(RedirectRecord::isValidDestination('$1'));
    }

    public function testRejectsUnsupportedSchemes(): void
    {
        self::assertFalse(RedirectRecord::isValidDestination('ftp://example.com'));
        self::assertFalse(RedirectRecord::isValidDestination('not a url'));
        self::assertFalse(RedirectRecord::isValidDestination(''));
        // A scheme with no host is effectively empty.
        self::assertFalse(RedirectRecord::isValidDestination('https://'));
        self::assertFalse(RedirectRecord::isValidDestination('http://'));
    }

    public function testRejectsProtocolRelativeUrls(): void
    {
        // `//host` resolves to an external origin; rejected on both surfaces now.
        self::assertFalse(RedirectRecord::isValidDestination('//evil.com'));
        self::assertFalse(RedirectRecord::isValidDestination('//evil.com/phishing'));
    }

    public function testExactMatchRejectsCaptureReferences(): void
    {
        // Exact produces no positional captures.
        self::assertNotNull(RedirectRecord::captureReferenceError('/page/$1', 'exact', '/old-page'));
        // No reference, or only $0, is fine.
        self::assertNull(RedirectRecord::captureReferenceError('/page', 'exact', '/old-page'));
        self::assertNull(RedirectRecord::captureReferenceError('/page/$0', 'exact', '/old-page'));
    }

    public function testPrefixAllowsFirstCaptureOnly(): void
    {
        self::assertNull(RedirectRecord::captureReferenceError('/new/$1', 'prefix', '/blog'));
        self::assertNotNull(RedirectRecord::captureReferenceError('/new/$2', 'prefix', '/blog'));
    }

    public function testWildcardCaptureCapacityMatchesStarCount(): void
    {
        // One * → $1 valid, $2 invalid.
        self::assertNull(RedirectRecord::captureReferenceError('/new/$1', 'wildcard', '/blog/*'));
        self::assertNotNull(RedirectRecord::captureReferenceError('/new/$2', 'wildcard', '/blog/*'));
        // Two * → $2 valid.
        self::assertNull(RedirectRecord::captureReferenceError('/new/$2', 'wildcard', '/a/*/b/*'));
    }

    public function testRegexCaptureCapacityMatchesGroupCount(): void
    {
        // One capturing group → $1 valid, $2 invalid.
        self::assertNull(RedirectRecord::captureReferenceError('/new/$1', 'regex', '^/blog/(.*)$'));
        self::assertNotNull(RedirectRecord::captureReferenceError('/new/$2', 'regex', '^/blog/(.*)$'));
        // No capturing group → any $1 is invalid.
        self::assertNotNull(RedirectRecord::captureReferenceError('/new/$1', 'regex', '^/evil$'));
        // Escaped parens and character classes are not capturing groups.
        self::assertNotNull(RedirectRecord::captureReferenceError('/new/$1', 'regex', '^/price/\(\d+\)$'));
        self::assertNotNull(RedirectRecord::captureReferenceError('/new/$1', 'regex', '^/item/[(a-z)]+$'));
    }

    public function testModelValidationWiresDestinationRules(): void
    {
        // Protocol-relative destination is rejected by the model validator.
        $record = new RedirectRecord();
        $record->sourceUrl = '/old-page';
        $record->destinationUrl = '//evil.com';
        $record->redirectSrcMatch = 'pathonly';
        $record->matchType = 'exact';
        self::assertFalse($record->validate(['destinationUrl']));
        self::assertTrue($record->hasErrors('destinationUrl'));

        // Capture reference under Exact Match is rejected by the model validator.
        $record = new RedirectRecord();
        $record->sourceUrl = '/old-page';
        $record->destinationUrl = '/new/$1';
        $record->redirectSrcMatch = 'pathonly';
        $record->matchType = 'exact';
        self::assertFalse($record->validate(['destinationUrl']));
        self::assertTrue($record->hasErrors('destinationUrl'));

        // A valid path destination passes.
        $record = new RedirectRecord();
        $record->sourceUrl = '/old-page';
        $record->destinationUrl = '/new-page';
        $record->redirectSrcMatch = 'pathonly';
        $record->matchType = 'exact';
        self::assertTrue($record->validate(['destinationUrl']));
    }

    public function testSourceFullUrlModeRequiresHost(): void
    {
        // A bare scheme has no host — not a usable source URL in Full URL mode.
        $record = new RedirectRecord();
        $record->sourceUrl = 'https://';
        $record->destinationUrl = '/somewhere';
        $record->redirectSrcMatch = 'fullurl';
        $record->matchType = 'exact';
        self::assertFalse($record->validate(['sourceUrl']));
        self::assertTrue($record->hasErrors('sourceUrl'));

        // A full URL with a host passes.
        $record = new RedirectRecord();
        $record->sourceUrl = 'https://example.com/old';
        $record->destinationUrl = '/somewhere';
        $record->redirectSrcMatch = 'fullurl';
        $record->matchType = 'exact';
        self::assertTrue($record->validate(['sourceUrl']));
    }
}

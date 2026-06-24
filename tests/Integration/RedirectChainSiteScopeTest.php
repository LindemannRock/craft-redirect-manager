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
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionMethod;

/**
 * Covers site scoping for internal redirect-chain resolution.
 *
 * @since 5.34.0
 */
final class RedirectChainSiteScopeTest extends TestCase
{
    public function testResolveRedirectChainPrefersRequestedSiteAndFallsBackToGlobal(): void
    {
        [$siteA, $siteB, $siteC] = $this->threeSites();

        $intermediate = '/' . self::MARKER . 'chain_scope_' . substr(uniqid('', true), -8);
        $wrongSiteDestination = '/' . self::MARKER . 'wrong_site_destination_' . substr(uniqid('', true), -8);
        $globalDestination = '/' . self::MARKER . 'global_destination_' . substr(uniqid('', true), -8);
        $siteDestination = '/' . self::MARKER . 'site_destination_' . substr(uniqid('', true), -8);

        $this->seedRedirect([
            'sourceUrl' => $intermediate,
            'sourceUrlParsed' => $intermediate,
            'destinationUrl' => $wrongSiteDestination,
            'siteId' => $siteB,
        ]);
        $globalRedirect = $this->seedRedirect([
            'sourceUrl' => $intermediate,
            'sourceUrlParsed' => $intermediate,
            'destinationUrl' => $globalDestination,
        ]);
        $globalRedirect->siteId = null;
        self::assertTrue($globalRedirect->save(false));
        $this->seedRedirect([
            'sourceUrl' => $intermediate,
            'sourceUrlParsed' => $intermediate,
            'destinationUrl' => $siteDestination,
            'siteId' => $siteA,
        ]);

        $method = new ReflectionMethod($this->redirects, 'resolveRedirectChain');

        self::assertSame($siteDestination, $method->invoke($this->redirects, $intermediate, $siteA));
        self::assertSame($globalDestination, $method->invoke($this->redirects, $intermediate, $siteC));
    }

    public function testWouldCreateLoopIgnoresOtherSiteRedirects(): void
    {
        [$siteA, $siteB] = $this->threeSites();

        $source = '/' . self::MARKER . 'loop_source_' . substr(uniqid('', true), -8);
        $destination = '/' . self::MARKER . 'loop_destination_' . substr(uniqid('', true), -8);

        $this->seedRedirect([
            'sourceUrl' => $destination,
            'sourceUrlParsed' => $destination,
            'destinationUrl' => $source,
            'siteId' => $siteB,
        ]);

        $method = new ReflectionMethod($this->redirects, 'wouldCreateLoop');

        self::assertFalse($method->invoke($this->redirects, $source, $destination, null, $siteA));
    }

    public function testWouldCreateLoopDetectsSameSiteRedirects(): void
    {
        [$siteA] = $this->threeSites();

        $source = '/' . self::MARKER . 'same_site_loop_source_' . substr(uniqid('', true), -8);
        $destination = '/' . self::MARKER . 'same_site_loop_destination_' . substr(uniqid('', true), -8);

        $this->seedRedirect([
            'sourceUrl' => $destination,
            'sourceUrlParsed' => $destination,
            'destinationUrl' => $source,
            'siteId' => $siteA,
        ]);

        $method = new ReflectionMethod($this->redirects, 'wouldCreateLoop');

        self::assertTrue($method->invoke($this->redirects, $source, $destination, null, $siteA));
    }

    /**
     * @return array<int>
     */
    private function threeSites(): array
    {
        $siteIds = array_map(
            static fn($site): int => (int)$site->id,
            Craft::$app->getSites()->getAllSites(),
        );

        self::assertGreaterThanOrEqual(3, count($siteIds), 'This integration test requires at least three configured sites.');

        return array_values($siteIds);
    }
}

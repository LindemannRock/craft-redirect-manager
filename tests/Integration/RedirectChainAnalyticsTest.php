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
use lindemannrock\base\testing\StubConsoleRequest;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionMethod;
use yii\base\Request as YiiRequest;

/**
 * Covers analytics semantics for internal redirect-chain resolution.
 *
 * The public 404 handler records the visitor-requested source URL before
 * issuing the redirect. Chain resolution should only discover the final
 * destination and must not create extra handled-404 rows for intermediate
 * redirect sources the visitor did not request directly.
 *
 * @since 5.32.0
 */
final class RedirectChainAnalyticsTest extends TestCase
{
    private const TEST_SALT = '0123456789abcdef0123456789abcdef';

    private ?YiiRequest $savedRequest = null;

    private bool $savedEnableAnalytics = true;

    private bool $savedEnableGeo = false;

    private bool $savedAnonymize = false;

    private ?string $savedSalt = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedRequest = Craft::$app->getRequest();
        Craft::$app->set('request', new StubConsoleRequest(userIp: '203.0.113.42'));

        $settings = $this->settings();
        $this->savedEnableAnalytics = $settings->enableAnalytics;
        $this->savedEnableGeo = $settings->enableGeoDetection;
        $this->savedAnonymize = $settings->anonymizeIpAddress;
        $this->savedSalt = $settings->ipHashSalt;

        $settings->enableAnalytics = true;
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
        $settings->enableAnalytics = $this->savedEnableAnalytics;
        $settings->enableGeoDetection = $this->savedEnableGeo;
        $settings->anonymizeIpAddress = $this->savedAnonymize;
        $settings->ipHashSalt = $this->savedSalt;

        parent::tearDown();
    }

    public function testResolveRedirectChainDoesNotRecordIntermediateAnalyticsRows(): void
    {
        $intermediate = '/' . self::MARKER . 'chain_intermediate_' . substr(uniqid('', true), -8);
        $final = '/' . self::MARKER . 'chain_final_' . substr(uniqid('', true), -8);

        $this->seedRedirect([
            'sourceUrl' => $intermediate,
            'sourceUrlParsed' => $intermediate,
            'destinationUrl' => $final,
        ]);

        $method = new ReflectionMethod($this->redirects, 'resolveRedirectChain');
        $resolvedUrl = $method->invoke(
            $this->redirects,
            $intermediate,
            Craft::$app->getSites()->getPrimarySite()->id,
        );

        $this->assertSame($final, $resolvedUrl);
        $this->assertSame(
            0,
            $this->countRows('{{%redirectmanager_analytics}}', ['urlParsed' => $intermediate]),
            'Resolving an internal chain must not record the intermediate redirect source as a handled 404.',
        );
    }
}

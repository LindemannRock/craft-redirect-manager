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
use craft\web\Response;
use lindemannrock\base\testing\StubConsoleRequest;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionMethod;

/**
 * Covers HTTP response semantics for Gone and redirect status codes.
 *
 * @since 5.41.0
 */
final class RedirectHttpStatusTest extends TestCase
{
    public function testGoneResponseHasNoLocationAndDoesNotResolveDestination(): void
    {
        $token = bin2hex(random_bytes(4));
        $path = '/' . self::MARKER . 'gone_' . $token . '/page';
        $redirect = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'gone_' . $token . '/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'gone_' . $token . '/*',
            'destinationUrl' => 'javascript:$1',
            'matchType' => 'wildcard',
            'statusCode' => 410,
        ]);

        $result = $this->redirects->findRedirect('https://example.test' . $path, $path);

        self::assertNotNull($result);
        self::assertSame($redirect->id, (int)$result['id']);
        self::assertSame('javascript:$1', $result['destinationUrl']);
        self::assertSame(1, $this->fetchHitCountFromDb((int)$redirect->id));

        $response = $this->prepareResponse($result, 'https://example.test' . $path, $path);

        self::assertSame(410, $response->getStatusCode());
        self::assertFalse($response->headers->has('Location'));
    }

    public function testGoneResponseCannotGainLocationFromCustomHeaders(): void
    {
        $this->settings()->additionalHeaders = [
            ['name' => 'Location', 'value' => 'https://destination.example/ignored'],
        ];

        $response = $this->prepareResponse([
            'destinationUrl' => 'https://destination.example/ignored',
            'statusCode' => 410,
        ], 'https://example.test/gone', '/gone');

        self::assertSame(410, $response->getStatusCode());
        self::assertFalse($response->headers->has('Location'));
    }

    public function testGoneAcceptanceKeepsHandledAnalyticsAttribution(): void
    {
        Craft::$app->set('request', new StubConsoleRequest(userIp: '203.0.113.42'));
        $settings = $this->settings();
        $settings->enableAnalytics = true;
        $settings->autoTrimAnalytics = false;
        $settings->enableGeoDetection = false;
        $settings->anonymizeIpAddress = false;
        $settings->ipHashSalt = '0123456789abcdef0123456789abcdef';
        $redirect = $this->seedRedirect(['statusCode' => 410]);

        $result = $this->redirects->handleExternal404(
            (string)$redirect->sourceUrlParsed,
            ['source' => 'shortlink-manager'],
        );

        self::assertNotNull($result);
        $analytics = $this->fetchRow('{{%redirectmanager_analytics}}', [
            'urlParsed' => strtolower((string)$redirect->sourceUrlParsed),
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
        ]);
        self::assertNotNull($analytics);
        self::assertSame(1, (int)$analytics['handled']);
        self::assertSame($redirect->id, (int)$analytics['redirectId']);
    }

    public function testRedirectResponseKeepsLocationAndStatus(): void
    {
        $response = $this->prepareResponse([
            'destinationUrl' => 'https://destination.example/path',
            'statusCode' => 308,
        ], 'https://example.test/source', '/source');

        self::assertSame(308, $response->getStatusCode());
        self::assertSame('https://destination.example/path', $response->headers->get('Location'));
    }

    public function testRedirectResponsePlacesPreservedQueryBeforeFragment(): void
    {
        $this->settings()->preserveQueryString = true;

        $response = $this->prepareResponse([
            'destinationUrl' => '/destination?existing=value#section',
            'statusCode' => 302,
        ], 'https://example.test/source?campaign=summer', '/source?campaign=summer');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(
            'https://redirect-primary.example.test/destination?existing=value&campaign=summer#section',
            $response->headers->get('Location'),
        );
    }

    /** @param array<string, mixed> $redirect */
    private function prepareResponse(array $redirect, string $fullUrl, string $pathOnly): Response
    {
        Craft::$app->set('request', new class() extends \craft\console\Request {
            public function getIsAjax(): bool
            {
                return false;
            }
        });
        Craft::$app->set('response', new Response());
        $method = new ReflectionMethod($this->redirects, 'prepareRedirectResponse');
        $response = $method->invoke($this->redirects, $redirect, $fullUrl, $pathOnly);
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }
}

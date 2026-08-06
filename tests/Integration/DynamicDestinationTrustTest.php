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
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\testing\StubConsoleRequest;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionMethod;
use yii\base\Request as YiiRequest;

/**
 * Covers trust-preserving capture resolution and eligible-winner accounting.
 *
 * @since 5.41.0
 */
final class DynamicDestinationTrustTest extends TestCase
{
    private const TEST_SALT = '0123456789abcdef0123456789abcdef';

    private ?YiiRequest $savedRequest = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedRequest = Craft::$app->getRequest();
        Craft::$app->set('request', new StubConsoleRequest(userIp: '203.0.113.42'));

        $settings = $this->settings();
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

        parent::tearDown();
    }

    public function testUnsafeHigherPriorityRuleIsSkippedForSafeWinner(): void
    {
        [$path, $unsafe, $safe] = $this->seedUnsafeAndSafeCandidates();

        $result = $this->redirects->findRedirect('https://example.test' . $path, $path);

        self::assertNotNull($result);
        self::assertSame($safe->id, (int)$result['id']);
        self::assertSame('https://safe.example/evil.example/phish', $result['destinationUrl']);
        self::assertSame(0, $this->fetchHitCountFromDb($unsafe->id));
        self::assertNull($this->fetchRow(RedirectRecord::tableName(), ['id' => $unsafe->id])['lastHit']);
        self::assertSame(1, $this->fetchHitCountFromDb($safe->id));
        self::assertNotEmpty($this->fetchRow(RedirectRecord::tableName(), ['id' => $safe->id])['lastHit']);
    }

    public function testMultipleUnsafeRulesAreSkippedBeforeSafeWinner(): void
    {
        $token = bin2hex(random_bytes(4));
        $path = '/' . self::MARKER . 'multi_' . $token . '/https://evil.example/phish';
        $unsafeCaptureOnly = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'multi_' . $token . '/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'multi_' . $token . '/*',
            'destinationUrl' => '$1',
            'matchType' => 'wildcard',
            'priority' => 0,
        ]);
        $unsafeAuthority = $this->seedRedirect([
            'sourceUrl' => '^/' . self::MARKER . 'multi_' . $token . '/(.*)$',
            'sourceUrlParsed' => '^/' . self::MARKER . 'multi_' . $token . '/(.*)$',
            'destinationUrl' => 'https://$1',
            'matchType' => 'regex',
            'priority' => 1,
        ]);
        $safe = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'multi_' . $token . '/https://',
            'sourceUrlParsed' => '/' . self::MARKER . 'multi_' . $token . '/https://',
            'destinationUrl' => '/safe$1',
            'matchType' => 'prefix',
            'priority' => 2,
        ]);

        $result = $this->redirects->findRedirect('https://example.test' . $path, $path);

        self::assertNotNull($result);
        self::assertSame($safe->id, (int)$result['id']);
        self::assertSame('/safeevil.example/phish', $result['destinationUrl']);
        self::assertSame(0, $this->fetchHitCountFromDb($unsafeCaptureOnly->id));
        self::assertSame(0, $this->fetchHitCountFromDb($unsafeAuthority->id));
        self::assertSame(1, $this->fetchHitCountFromDb($safe->id));
    }

    public function testAllUnsafeMatchesReturnUnhandledWithoutAccounting(): void
    {
        $token = bin2hex(random_bytes(4));
        $path = '/' . self::MARKER . 'all_unsafe_' . $token . '/javascript:alert(1)';
        $captureOnly = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'all_unsafe_' . $token . '/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'all_unsafe_' . $token . '/*',
            'destinationUrl' => '$1',
            'matchType' => 'wildcard',
            'priority' => 0,
        ]);
        $dangerous = $this->seedRedirect([
            'sourceUrl' => '^/' . self::MARKER . 'all_unsafe_' . $token . '/(.*)$',
            'sourceUrlParsed' => '^/' . self::MARKER . 'all_unsafe_' . $token . '/(.*)$',
            'destinationUrl' => 'java\tscript:$1',
            'matchType' => 'regex',
            'priority' => 1,
        ]);

        self::assertNull($this->redirects->findRedirect('https://example.test' . $path, $path));
        self::assertSame(0, $this->fetchHitCountFromDb($captureOnly->id));
        self::assertSame(0, $this->fetchHitCountFromDb($dangerous->id));
    }

    public function testFixedAuthorityPathQueryAndFragmentCapturesRemainSupported(): void
    {
        $token = bin2hex(random_bytes(4));
        $path = '/' . self::MARKER . 'fixed_' . $token . '/docs/craft/intro';
        $redirect = $this->seedRedirect([
            'sourceUrl' => '^/' . self::MARKER . 'fixed_' . $token . '/([^/]+)/([^/]+)/(.*)$',
            'sourceUrlParsed' => '^/' . self::MARKER . 'fixed_' . $token . '/([^/]+)/([^/]+)/(.*)$',
            'destinationUrl' => 'HTTPS://Editor.EXAMPLE/$1?topic=$2#$3',
            'matchType' => 'regex',
        ]);

        $result = $this->redirects->findRedirect('https://example.test' . $path, $path);

        self::assertNotNull($result);
        self::assertSame($redirect->id, (int)$result['id']);
        self::assertSame('HTTPS://Editor.EXAMPLE/docs?topic=craft#intro', $result['destinationUrl']);
    }

    public function testRelativeAndSupportedApplicationTemplatesRetainTheirTrustClass(): void
    {
        $token = bin2hex(random_bytes(4));
        $relativePath = '/' . self::MARKER . 'relative_' . $token . '/https://evil.example';
        $relative = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'relative_' . $token . '/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'relative_' . $token . '/*',
            'destinationUrl' => '/continue/$1',
            'matchType' => 'wildcard',
        ]);

        $relativeResult = $this->redirects->findRedirect('https://example.test' . $relativePath, $relativePath);
        self::assertNotNull($relativeResult);
        self::assertSame($relative->id, (int)$relativeResult['id']);
        self::assertSame('/continue/https://evil.example', $relativeResult['destinationUrl']);

        $contactPath = '/' . self::MARKER . 'contact_' . $token . '/15551234567';
        $contact = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'contact_' . $token . '/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'contact_' . $token . '/*',
            'destinationUrl' => 'tel:+$1',
            'matchType' => 'wildcard',
        ]);

        $contactResult = $this->redirects->findRedirect('https://example.test' . $contactPath, $contactPath);
        self::assertNotNull($contactResult);
        self::assertSame($contact->id, (int)$contactResult['id']);
        self::assertSame('tel:+15551234567', $contactResult['destinationUrl']);
    }

    public function testDollarZeroMultipleAndMissingCapturesResolveInsideRelativeTemplate(): void
    {
        $token = bin2hex(random_bytes(4));
        $path = '/' . self::MARKER . 'captures_' . $token . '/alpha/beta';
        $redirect = $this->seedRedirect([
            'sourceUrl' => '^/' . self::MARKER . 'captures_' . $token . '/([^/]+)/(.*)$',
            'sourceUrlParsed' => '^/' . self::MARKER . 'captures_' . $token . '/([^/]+)/(.*)$',
            'destinationUrl' => '/log$0/$2/$1/$9',
            'matchType' => 'regex',
        ]);

        $result = $this->redirects->findRedirect('https://example.test' . $path, $path);

        self::assertNotNull($result);
        self::assertSame($redirect->id, (int)$result['id']);
        self::assertSame('/log' . $path . '/beta/alpha/', $result['destinationUrl']);
    }

    public function testExternalIntegrationUsesSafeWinnerForAnalyticsAndAccounting(): void
    {
        [$path, $unsafe, $safe] = $this->seedUnsafeAndSafeCandidates();

        $result = $this->redirects->handleExternal404($path, ['source' => 'trust-test']);

        self::assertNotNull($result);
        self::assertSame($safe->id, (int)$result['id']);
        self::assertSame('https://safe.example/evil.example/phish', $result['destinationUrl']);
        self::assertSame(0, $this->fetchHitCountFromDb($unsafe->id));
        self::assertSame(1, $this->fetchHitCountFromDb($safe->id));

        $analytics = $this->fetchRow('{{%redirectmanager_analytics}}', [
            'urlParsed' => strtolower($path),
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
        ]);
        self::assertNotNull($analytics);
        self::assertSame(1, (int)$analytics['handled']);
        self::assertSame($safe->id, (int)$analytics['redirectId']);
        self::assertSame('trust-test', $analytics['sourcePlugin']);
        self::assertSame(1, (int)$analytics['count']);
    }

    public function testPositiveCacheStoresOnlyTheEligibleSafeWinner(): void
    {
        $this->settings()->enableRedirectCache = true;
        $this->settings()->cacheStorageMethod = 'file';
        [$path, $unsafe, $safe] = $this->seedUnsafeAndSafeCandidates();
        $fullUrl = 'https://example.test' . $path;

        $first = $this->redirects->findRedirect($fullUrl, $path);
        $second = $this->redirects->findRedirect($fullUrl, $path);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame($safe->id, (int)$first['id']);
        self::assertSame($safe->id, (int)$second['id']);
        self::assertSame('https://safe.example/evil.example/phish', $second['destinationUrl']);
        self::assertSame(0, $this->fetchHitCountFromDb($unsafe->id));
        self::assertSame(2, $this->fetchHitCountFromDb($safe->id));
        self::assertSame(1, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
    }

    public function testInvalidCachedWinnerCannotResurrectUnsafeRedirect(): void
    {
        $this->settings()->enableRedirectCache = true;
        $this->settings()->cacheStorageMethod = 'file';
        [$path, $unsafe, $safe] = $this->seedUnsafeAndSafeCandidates();
        $fullUrl = 'https://example.test' . $path;
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        self::assertSame($safe->id, (int)$this->redirects->findRedirect($fullUrl, $path)['id']);

        $cacheFile = PluginHelper::getCachePath(RedirectManager::$plugin, 'redirects')
            . md5($fullUrl) . '_' . $siteId . '.cache';
        $unsafeCachedWinner = [
            'data' => array_merge($unsafe->toArray(), [
                'destinationUrl' => 'https://evil.example/phish',
                '_destinationTemplate' => '$1',
                '_destinationPolicyVersion' => 1,
            ]),
            'expires' => time() + 3600,
        ];
        self::assertNotFalse(file_put_contents($cacheFile, json_encode($unsafeCachedWinner)));

        $result = $this->redirects->findRedirect($fullUrl, $path);

        self::assertNotNull($result);
        self::assertSame($safe->id, (int)$result['id']);
        self::assertSame(0, $this->fetchHitCountFromDb($unsafe->id));
        self::assertSame(2, $this->fetchHitCountFromDb($safe->id));

        $rewritten = json_decode((string)file_get_contents($cacheFile), true);
        self::assertSame($safe->id, (int)$rewritten['data']['id']);
    }

    public function testRedirectChainSkipsUnsafeCandidateAtResolutionSeam(): void
    {
        $token = bin2hex(random_bytes(4));
        $intermediate = '/' . self::MARKER . 'chain_trust_' . $token . '/evil.example';
        $unsafe = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'chain_trust_' . $token . '/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'chain_trust_' . $token . '/*',
            'destinationUrl' => '$1',
            'matchType' => 'wildcard',
            'priority' => 0,
        ]);
        $safe = $this->seedRedirect([
            'sourceUrl' => '^/' . self::MARKER . 'chain_trust_' . $token . '/(.*)$',
            'sourceUrlParsed' => '^/' . self::MARKER . 'chain_trust_' . $token . '/(.*)$',
            'destinationUrl' => '/safe-chain/$1',
            'matchType' => 'regex',
            'priority' => 1,
        ]);

        $method = new ReflectionMethod($this->redirects, 'resolveRedirectChain');
        $resolved = $method->invoke(
            $this->redirects,
            $intermediate,
            Craft::$app->getSites()->getCurrentSite()->id,
        );

        self::assertSame('/safe-chain/evil.example', $resolved);
        self::assertSame(0, $this->fetchHitCountFromDb($unsafe->id));
        self::assertSame(0, $this->fetchHitCountFromDb($safe->id));
    }

    public function testTemplateAndResolvedNormalizationVariantsRespectTrustBoundary(): void
    {
        foreach ([
            ' HTTPS://example.com/path',
            "https://example.com/line\nbreak",
            "https://example.com/line\x7Fbreak",
            'java%73cript:$1',
            '//example.com/$1',
            'HtTpS://$1.example.com/path',
            'https://user:$1@example.com/path',
            'https://example.com:$1/path',
        ] as $unsafeTemplate) {
            self::assertFalse(RedirectRecord::isValidDestination($unsafeTemplate), $unsafeTemplate);
        }

        foreach ([
            '/encoded/%2F/$1',
            'HtTpS://Editor.Example/path/$1',
            'mailto:$1@example.com',
        ] as $safeTemplate) {
            self::assertTrue(RedirectRecord::isValidDestination($safeTemplate), $safeTemplate);
        }

        self::assertNull($this->matching->resolveDestination('$1', ['//evil.example/path']));
        self::assertNull($this->matching->resolveDestination('$1', ['JaVaScRiPt:alert(1)']));
        self::assertNull($this->matching->resolveDestination('$1', ['https%3A%2F%2Fevil.example']));
        self::assertSame(
            'https://editor.example/path/%2Fsafe?q=next#fragment',
            $this->matching->resolveDestination(
                'https://editor.example/path/$1?q=$2#$3',
                ['unused', '%2Fsafe', 'next', 'fragment'],
            ),
        );
    }

    public function testIntrinsicUnsafeTemplatesAreRejectedBeforeSaveOrImport(): void
    {
        foreach ([
            '$0',
            '$1',
            'https://$1/path',
            'https://user:$1@example.com/path',
            'https://example.com:$1/path',
            'http$1://example.com/path',
            ' javascript:alert(1)',
            "https://example.com/line\nbreak",
        ] as $template) {
            self::assertFalse(RedirectRecord::isValidDestination($template), $template);
        }

        foreach ([
            '/relative/$1',
            'https://example.com/$1?next=$2#$0',
            'mailto:$1@example.com',
            'slack://channel/$1',
        ] as $template) {
            self::assertTrue(RedirectRecord::isValidDestination($template), $template);
        }
    }

    /**
     * @return array{0: string, 1: RedirectRecord, 2: RedirectRecord}
     */
    private function seedUnsafeAndSafeCandidates(): array
    {
        $token = bin2hex(random_bytes(4));
        $prefix = '/' . self::MARKER . 'winner_' . $token . '/';
        $path = $prefix . 'https://evil.example/phish';
        $unsafe = $this->seedRedirect([
            'sourceUrl' => $prefix . '*',
            'sourceUrlParsed' => $prefix . '*',
            'destinationUrl' => '$1',
            'matchType' => 'wildcard',
            'priority' => 0,
        ]);
        $safe = $this->seedRedirect([
            'sourceUrl' => $prefix . 'https://*',
            'sourceUrlParsed' => $prefix . 'https://*',
            'destinationUrl' => 'https://safe.example/$1',
            'matchType' => 'wildcard',
            'priority' => 1,
        ]);

        return [$path, $unsafe, $safe];
    }
}

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
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use yii\base\Request as YiiRequest;

/**
 * Covers candidate eligibility when redirect chains cycle or exceed the bound.
 *
 * @since 5.41.0
 */
final class RedirectChainEligibilityTest extends TestCase
{
    private const TEST_SALT = '0123456789abcdef0123456789abcdef';

    private ?YiiRequest $savedRequest = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedRequest = Craft::$app->getRequest();
        Craft::$app->set('request', new StubConsoleRequest(userIp: '203.0.113.42'));
        Craft::$app->set('response', new Response());

        $settings = $this->settings();
        $settings->enableRedirectCache = true;
        $settings->cacheStorageMethod = 'file';
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

    #[DataProvider('patternCycleProvider')]
    public function testPatternCycleIsUnhandledWithoutAcceptedEffects(string $matchType, string $sourceSuffix): void
    {
        $token = bin2hex(random_bytes(4));
        $prefix = '/' . self::MARKER . 'cycle_' . $token . '/';
        $requestPath = $prefix . 'start';
        $source = match ($matchType) {
            'prefix' => $prefix,
            'wildcard' => $prefix . '*',
            'regex' => '^' . preg_quote($prefix, '#') . '.*$',
            default => throw new \InvalidArgumentException('Unsupported cycle match type.'),
        };
        $unsafe = $this->seedRedirect([
            'sourceUrl' => $source,
            'sourceUrlParsed' => $source,
            'destinationUrl' => $prefix . $sourceSuffix,
            'matchType' => $matchType,
        ]);

        $result = $this->redirects->findRedirect('https://example.test' . $requestPath, $requestPath);

        self::assertNull($result);
        self::assertSame(0, $this->fetchHitCountFromDb((int)$unsafe->id));
        self::assertSame(1, RedirectManager::$plugin->localCache->countRedirectCacheFiles());
        self::assertFalse(Craft::$app->getResponse()->headers->has('Location'));
    }

    public function testUnsafeMultiHopCandidateIsSkippedForLaterSafeCandidate(): void
    {
        $token = bin2hex(random_bytes(4));
        $requestPath = '/' . self::MARKER . 'multi_cycle_' . $token . '/start';
        $loopA = '/' . self::MARKER . 'multi_cycle_' . $token . '/loop-a';
        $loopB = '/' . self::MARKER . 'multi_cycle_' . $token . '/loop-b';
        $unsafe = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'multi_cycle_' . $token . '/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'multi_cycle_' . $token . '/*',
            'destinationUrl' => $loopA,
            'matchType' => 'wildcard',
            'priority' => 0,
        ]);
        $this->seedRedirect([
            'sourceUrl' => $loopA,
            'sourceUrlParsed' => $loopA,
            'destinationUrl' => $loopB,
        ]);
        $this->seedRedirect([
            'sourceUrl' => $loopB,
            'sourceUrlParsed' => $loopB,
            'destinationUrl' => $loopA,
        ]);
        $safe = $this->seedRedirect([
            'sourceUrl' => '^' . preg_quote($requestPath, '#') . '$',
            'sourceUrlParsed' => '^' . preg_quote($requestPath, '#') . '$',
            'destinationUrl' => '/safe-after-cycle',
            'matchType' => 'regex',
            'priority' => 1,
        ]);

        $result = $this->redirects->findRedirect('https://example.test' . $requestPath, $requestPath);

        self::assertNotNull($result);
        self::assertSame($safe->id, (int)$result['id']);
        self::assertSame(0, $this->fetchHitCountFromDb((int)$unsafe->id));
        self::assertSame(1, $this->fetchHitCountFromDb((int)$safe->id));
    }

    public function testDepthExhaustedCandidateIsSkippedForLaterSafeCandidate(): void
    {
        $token = bin2hex(random_bytes(4));
        $requestPath = '/' . self::MARKER . 'depth_' . $token . '/start';
        $unsafe = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'depth_' . $token . '/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'depth_' . $token . '/*',
            'destinationUrl' => '/' . self::MARKER . 'hop_' . $token . '_1',
            'matchType' => 'wildcard',
            'priority' => 0,
        ]);
        for ($hop = 1; $hop <= 11; $hop++) {
            $this->seedRedirect([
                'sourceUrl' => '/' . self::MARKER . 'hop_' . $token . '_' . $hop,
                'sourceUrlParsed' => '/' . self::MARKER . 'hop_' . $token . '_' . $hop,
                'destinationUrl' => '/' . self::MARKER . 'hop_' . $token . '_' . ($hop + 1),
            ]);
        }
        $safe = $this->seedRedirect([
            'sourceUrl' => '^' . preg_quote($requestPath, '#') . '$',
            'sourceUrlParsed' => '^' . preg_quote($requestPath, '#') . '$',
            'destinationUrl' => '/safe-after-depth',
            'matchType' => 'regex',
            'priority' => 1,
        ]);

        $result = $this->redirects->findRedirect('https://example.test' . $requestPath, $requestPath);

        self::assertNotNull($result);
        self::assertSame($safe->id, (int)$result['id']);
        self::assertSame(0, $this->fetchHitCountFromDb((int)$unsafe->id));
        self::assertSame(1, $this->fetchHitCountFromDb((int)$safe->id));
    }

    public function testExternalAllUnsafeResultRecordsNormalUnhandledAnalytics(): void
    {
        $token = bin2hex(random_bytes(4));
        $prefix = '/' . self::MARKER . 'external_cycle_' . $token . '/';
        $requestPath = $prefix . 'start';
        $unsafe = $this->seedRedirect([
            'sourceUrl' => $prefix . '*',
            'sourceUrlParsed' => $prefix . '*',
            'destinationUrl' => $prefix . 'loop',
            'matchType' => 'wildcard',
        ]);

        $result = $this->redirects->handleExternal404($requestPath, ['source' => 'shortlink-manager']);

        self::assertNull($result);
        self::assertSame(0, $this->fetchHitCountFromDb((int)$unsafe->id));
        $analytics = $this->fetchRow('{{%redirectmanager_analytics}}', [
            'urlParsed' => strtolower($requestPath),
            'siteId' => Craft::$app->getSites()->getCurrentSite()->id,
        ]);
        self::assertNotNull($analytics);
        self::assertSame(0, (int)$analytics['handled']);
        self::assertNull($analytics['redirectId']);
        self::assertSame('shortlink-manager', $analytics['sourcePlugin']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function patternCycleProvider(): iterable
    {
        yield 'prefix' => ['prefix', 'loop'];
        yield 'wildcard' => ['wildcard', 'loop'];
        yield 'regex' => ['regex', 'loop'];
    }
}

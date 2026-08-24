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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers site-specific winner precedence and ordering within each site rank.
 *
 * @since 5.41.0
 */
final class RedirectWinnerPrecedenceTest extends TestCase
{
    /**
     * @param array<string, mixed> $rule
     */
    #[DataProvider('matchFamilyProvider')]
    public function testSiteSpecificRuleWinsAcrossMatchFamilies(array $rule, string $requestPath): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $global = $this->seedGlobalRedirect($rule + [
            'destinationUrl' => '/global-winner',
            'priority' => 0,
        ]);
        $site = $this->seedRedirect($rule + [
            'destinationUrl' => '/site-winner',
            'priority' => 9,
            'siteId' => $siteId,
        ]);

        $result = $this->redirects->findRedirectForSite(
            'https://example.test' . $requestPath,
            $requestPath,
            $siteId,
        );

        self::assertNotNull($result);
        self::assertSame($site->id, (int)$result['id']);
        self::assertSame('/site-winner', $result['destinationUrl']);
        self::assertSame(0, $this->fetchHitCountFromDb((int)$global->id));
        self::assertSame(1, $this->fetchHitCountFromDb((int)$site->id));
    }

    public function testPriorityThenIdOrdersCandidatesWithinTheSiteRank(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $token = bin2hex(random_bytes(4));
        $requestPath = '/' . self::MARKER . 'rank_' . $token . '/page';

        $this->seedGlobalRedirect([
            'sourceUrl' => '^/' . self::MARKER . 'rank_' . $token . '/.*$',
            'sourceUrlParsed' => '^/' . self::MARKER . 'rank_' . $token . '/.*$',
            'destinationUrl' => '/global',
            'matchType' => 'regex',
            'priority' => 0,
        ]);
        $firstSite = $this->seedRedirect([
            'sourceUrl' => '/' . self::MARKER . 'rank_' . $token . '/*',
            'sourceUrlParsed' => '/' . self::MARKER . 'rank_' . $token . '/*',
            'destinationUrl' => '/first-site',
            'matchType' => 'wildcard',
            'priority' => 4,
            'siteId' => $siteId,
        ]);
        $higherPrioritySite = $this->seedRedirect([
            'sourceUrl' => '^/' . self::MARKER . 'rank_' . $token . '/page$',
            'sourceUrlParsed' => '^/' . self::MARKER . 'rank_' . $token . '/page$',
            'destinationUrl' => '/higher-priority-site',
            'matchType' => 'regex',
            'priority' => 2,
            'siteId' => $siteId,
        ]);
        $laterSamePrioritySite = $this->seedRedirect([
            'sourceUrl' => '^/' . self::MARKER . 'rank_' . $token . '/.*$',
            'sourceUrlParsed' => '^/' . self::MARKER . 'rank_' . $token . '/.*$',
            'destinationUrl' => '/later-same-priority-site',
            'matchType' => 'regex',
            'priority' => 2,
            'siteId' => $siteId,
        ]);

        $result = $this->redirects->findRedirectForSite(
            'https://example.test' . $requestPath,
            $requestPath,
            $siteId,
        );

        self::assertNotNull($result);
        self::assertSame($higherPrioritySite->id, (int)$result['id']);
        self::assertSame(0, $this->fetchHitCountFromDb((int)$firstSite->id));
        self::assertSame(1, $this->fetchHitCountFromDb((int)$higherPrioritySite->id));
        self::assertSame(0, $this->fetchHitCountFromDb((int)$laterSamePrioritySite->id));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function matchFamilyProvider(): iterable
    {
        $token = 'precedence_' . bin2hex(random_bytes(4));

        yield 'exact' => [[
            'sourceUrl' => '/' . self::MARKER . $token . '_exact',
            'sourceUrlParsed' => '/' . self::MARKER . $token . '_exact',
            'matchType' => 'exact',
        ], '/' . self::MARKER . $token . '_exact'];

        yield 'prefix' => [[
            'sourceUrl' => '/' . self::MARKER . $token . '_prefix/',
            'sourceUrlParsed' => '/' . self::MARKER . $token . '_prefix/',
            'matchType' => 'prefix',
        ], '/' . self::MARKER . $token . '_prefix/page'];

        yield 'wildcard' => [[
            'sourceUrl' => '/' . self::MARKER . $token . '_wildcard/*',
            'sourceUrlParsed' => '/' . self::MARKER . $token . '_wildcard/*',
            'matchType' => 'wildcard',
        ], '/' . self::MARKER . $token . '_wildcard/page'];

        yield 'regex' => [[
            'sourceUrl' => '^/' . self::MARKER . $token . '_regex/.*$',
            'sourceUrlParsed' => '^/' . self::MARKER . $token . '_regex/.*$',
            'matchType' => 'regex',
        ], '/' . self::MARKER . $token . '_regex/page'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedGlobalRedirect(array $overrides): RedirectRecord
    {
        $record = $this->seedRedirect($overrides);
        $record->siteId = null;
        self::assertTrue($record->save(false));

        return $record;
    }
}

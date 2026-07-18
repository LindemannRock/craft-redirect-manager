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
use yii\base\Request as YiiRequest;

/**
 * Pins the URL-casing normalization ruling.
 *
 * MySQL's ci collation made bookkeeping equality (duplicate checks, unique
 * indexes, 404-count merging) case-insensitive implicitly; PostgreSQL
 * compares case-sensitively. The one-product-one-semantics fix: equality-
 * matched rows store `sourceUrlParsed` lowercase (RedirectRecord::beforeSave)
 * and probes are lowercased in PHP, while pattern rows (regex/wildcard) stay
 * verbatim — lowercasing a pattern corrupts it (\W would become \w). The raw
 * display columns keep original casing. Runtime matching is case-blind for
 * all match types (strcasecmp / stripos / PCRE i), so none of this changes
 * which redirects fire.
 */
final class UrlCaseNormalizationTest extends TestCase
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

    public function testExactAndPrefixRedirectsStoreLowercaseParsedUrl(): void
    {
        foreach (['exact', 'prefix'] as $matchType) {
            $mixed = '/' . self::MARKER . 'Case-VARIANT_' . $matchType . '_' . substr(uniqid('', true), -8);

            $record = $this->seedRedirect([
                'sourceUrl' => $mixed,
                'sourceUrlParsed' => $mixed,
                'matchType' => $matchType,
            ]);

            $row = $this->fetchRow('{{%redirectmanager_redirects}}', ['id' => $record->id]);
            $this->assertNotNull($row);
            $this->assertSame(strtolower($mixed), $row['sourceUrlParsed'], "$matchType parsed URL must be stored lowercase.");
            $this->assertSame($mixed, $row['sourceUrl'], 'The raw display column must keep its original casing.');
        }
    }

    public function testPatternRedirectsKeepParsedUrlVerbatim(): void
    {
        // \W lowercased would become \w — the opposite pattern — so pattern
        // rows must never be case-normalized.
        $pattern = '/' . self::MARKER . 'Pattern_' . substr(uniqid('', true), -8) . '/([A-Z]+)\W-end';

        $record = $this->seedRedirect([
            'sourceUrl' => $pattern,
            'sourceUrlParsed' => $pattern,
            'matchType' => 'regex',
        ]);

        $row = $this->fetchRow('{{%redirectmanager_redirects}}', ['id' => $record->id]);
        $this->assertNotNull($row);
        $this->assertSame($pattern, $row['sourceUrlParsed'], 'Pattern rows must store the pattern verbatim.');
    }

    public function testLowercasedProbeFindsMixedCaseSeededRedirect(): void
    {
        // The bookkeeping queries lowercase their probe in PHP; with storage
        // lowercased at write, plain indexed equality is case-insensitive on
        // both engines — no LOWER() around the column needed.
        $mixed = '/' . self::MARKER . 'Probe-Check_' . substr(uniqid('', true), -8);
        $record = $this->seedRedirect([
            'sourceUrl' => $mixed,
            'sourceUrlParsed' => $mixed,
            'matchType' => 'exact',
        ]);

        $found = (new \craft\db\Query())
            ->from('{{%redirectmanager_redirects}}')
            ->where(['sourceUrlParsed' => strtolower($mixed)])
            ->andWhere(['id' => $record->id])
            ->exists();

        $this->assertTrue($found, 'A lowercased probe must find the stored row via plain equality.');
    }

    public function testRecord404StoresLowercaseParsedUrlAndMergesCaseVariants(): void
    {
        $base = '/' . self::MARKER . '404Case_' . substr(uniqid('', true), -8);
        $mixed = strtoupper($base);

        $this->analytics->record404($mixed, false);
        $row = $this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => strtolower($base)]);
        $this->assertNotNull($row, 'urlParsed must be stored lowercase.');
        $this->assertSame($mixed, $row['url'], 'The raw url column must keep the original casing.');
        $this->assertSame(1, (int)$row['count']);

        // A case-variant hit merges into the same row on both engines.
        $this->analytics->record404(strtolower($base), false);
        $merged = $this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => strtolower($base)]);
        $this->assertNotNull($merged);
        $this->assertSame((int)$row['id'], (int)$merged['id'], 'Case variants must merge into one analytics row.');
        $this->assertSame(2, (int)$merged['count']);
    }
}

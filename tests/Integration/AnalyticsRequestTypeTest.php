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
use lindemannrock\redirectmanager\helpers\AnalyticsRequestTypeHelper;
use lindemannrock\redirectmanager\tests\TestCase;
use yii\base\Request as YiiRequest;

/**
 * Pins the stored analytics `requestType` classification introduced with the
 * SQL-backed request-type filter (audit batch 2.10).
 *
 * {@see \lindemannrock\redirectmanager\helpers\AnalyticsRequestTypeHelper::detect()}
 * collapses a 404 into one of three buckets — `probe` (security-scanner URL
 * pattern), `bot` (a non-probe hit flagged as a robot), or `normal` — and
 * `record404()` persists that value into the indexed `requestType` column so
 * the dashboard can filter in SQL instead of scanning every row in PHP. A
 * security probe outranks the bot flag: `/wp-config.php` from a crawler is a
 * probe, not a bot.
 *
 * The pure `detect()` assertions need no fixtures. The storage assertion drives
 * the full `record404()` path, which reaches for `Craft::$app->request`
 * web-only accessors; the integration bootstrap runs Craft as a console
 * application, so a {@see StubConsoleRequest} is swapped in (mirroring
 * {@see AnalyticsRecord404DedupTest}). The probe case is asserted through the
 * DB because probe classification is deterministic regardless of the stub's
 * user-agent — probe always wins over the robot flag.
 *
 * @since 5.32.0
 */
final class AnalyticsRequestTypeTest extends TestCase
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

    public function testDetectClassifiesSecurityProbeUrls(): void
    {
        $this->assertSame('probe', AnalyticsRequestTypeHelper::detect('/wp-config.php'));
        $this->assertSame('probe', AnalyticsRequestTypeHelper::detect('/.env'));
        $this->assertSame('probe', AnalyticsRequestTypeHelper::detect('/.git/config'));
        $this->assertSame('probe', AnalyticsRequestTypeHelper::detect('/phpmyadmin'));
    }

    public function testDetectClassifiesRobotHitsAsBot(): void
    {
        $this->assertSame(
            'bot',
            AnalyticsRequestTypeHelper::detect('/some/missing/page', true),
            'A non-probe URL flagged as a robot is a bot.',
        );
    }

    public function testDetectClassifiesOrdinaryHitsAsNormal(): void
    {
        $this->assertSame(
            'normal',
            AnalyticsRequestTypeHelper::detect('/some/missing/page', false),
        );
        $this->assertSame(
            'normal',
            AnalyticsRequestTypeHelper::detect('/some/missing/page'),
            'isRobot defaults to false → normal.',
        );
    }

    public function testProbeOutranksBot(): void
    {
        $this->assertSame(
            'probe',
            AnalyticsRequestTypeHelper::detect('/wp-config.php', true),
            'A security-scanner pattern is a probe even when the request is a robot.',
        );
    }

    public function testRecord404PersistsProbeRequestType(): void
    {
        // Marker rides as the leading path segment (so purgeTestRows() drains
        // the row) while the trailing /wp-config.php triggers probe detection.
        $url = '/' . self::MARKER . substr(uniqid('', true), -8) . '/wp-config.php';

        $this->analytics->record404($url, false);

        $row = $this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => $url]);
        $this->assertNotNull($row, 'record404() must insert the analytics row.');
        $this->assertSame(
            'probe',
            $row['requestType'],
            'record404() must persist the detected request type into the indexed column.',
        );
    }

    public function testRecord404PersistsNormalRequestType(): void
    {
        $url = '/' . self::MARKER . 'plain_' . substr(uniqid('', true), -8);

        $this->analytics->record404($url, false);

        $row = $this->fetchRow('{{%redirectmanager_analytics}}', ['urlParsed' => $url]);
        $this->assertNotNull($row);
        $this->assertNotSame(
            'probe',
            $row['requestType'],
            'An ordinary path must not be classified as a security probe.',
        );
    }
}

<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\base\testing\SqlDialectLinter;
use lindemannrock\redirectmanager\tests\TestCase;

/**
 * Pins PostgreSQL dialect safety for hand-written SQL.
 *
 * CI runs on MySQL, which is case-insensitive for identifiers, allows
 * MAX()/MIN() over its tinyint booleans, and resolves upsert column
 * references without ambiguity — so none of these bugs surface there.
 * PostgreSQL folds unquoted identifiers to lowercase, has no boolean
 * max/min, and rejects bare column references inside ON CONFLICT DO UPDATE.
 */
final class PostgresDialectSafetyTest extends TestCase
{
    /**
     * Boolean columns of this plugin's tables (from Install.php) — MAX()/MIN()
     * directly over one is a PostgreSQL type error; the linter needs the names
     * since types aren't visible in source.
     */
    private const BOOLEAN_COLUMNS = [
        'anonymizeIpAddress', 'apiEndpointEnabled', 'autoCreateRedirects', 'autoTrimAnalytics',
        'backupEnabled', 'backupOnImport', 'cacheDeviceDetection', 'enableAnalytics',
        'enableGeoDetection', 'enableRedirectCache', 'enabled', 'exportsCsv', 'exportsExcel',
        'exportsJson', 'handled', 'isMobileApp', 'isRobot', 'isSystemAgent',
        'preserveQueryString', 'setNoCacheHeaders', 'showSeconds', 'stripQueryString',
        'stripQueryStringFromStats',
    ];

    public function testRawSqlLiteralsAreDialectSafe(): void
    {
        $violations = SqlDialectLinter::scanDirectory(
            dirname(__DIR__, 2) . '/src',
            [],
            self::BOOLEAN_COLUMNS,
        );

        self::assertSame([], $violations, "PostgreSQL-unsafe raw SQL found:\n" . implode("\n", $violations));
    }

    /**
     * The analytics 404 upsert increments [[count]] on conflict — the
     * existing-row reference must be table-qualified via
     * DbHelper::existingColumn() or PostgreSQL raises SQLSTATE 42702.
     */
    public function testAnalyticsCountUpsertQualifiesExistingRowColumn(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/services/analytics/AnalyticsTrackingService.php');
        self::assertIsString($source);

        self::assertStringContainsString(
            "DbHelper::existingColumn('redirectmanager_analytics', 'count')",
            $source
        );
        self::assertStringNotContainsString("new Expression('[[count]] + 1')", $source);
    }
}

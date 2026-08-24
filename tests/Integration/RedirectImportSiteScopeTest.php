<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\redirectmanager\controllers\ImportExportController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionMethod;

/**
 * Pins the final CSV import editable-site write scope.
 *
 * @since 5.35.0
 */
final class RedirectImportSiteScopeTest extends TestCase
{
    public function testFinalImportRowsExcludeNonEditableSites(): void
    {
        $rows = [
            [
                'siteId' => null,
                'sourceUrl' => '/' . self::MARKER . 'global',
            ],
            [
                'siteId' => '10',
                'sourceUrl' => '/' . self::MARKER . 'editable',
            ],
            [
                'siteId' => 20,
                'sourceUrl' => '/' . self::MARKER . 'blocked',
            ],
        ];

        [$filtered, $skipped] = $this->filterImportRows($rows, [10]);

        self::assertSame(1, $skipped);
        self::assertSame(
            [
                '/' . self::MARKER . 'global',
                '/' . self::MARKER . 'editable',
            ],
            array_column($filtered, 'sourceUrl')
        );
        self::assertNull($filtered[0]['siteId']);
        self::assertSame(10, $filtered[1]['siteId']);
    }

    public function testImportUsesTheSharedPathOnlySourceIdentity(): void
    {
        $controller = new ImportExportController('import-export', RedirectManager::getInstance());
        $method = new ReflectionMethod($controller, 'normalizeImportSource');
        $method->setAccessible(true);

        /** @var array<string, mixed> $normalized */
        $normalized = $method->invoke($controller, [
            'sourceUrl' => 'https://import.example.test/base/old?drop=yes#drop',
            'redirectSrcMatch' => 'pathonly',
            'matchType' => 'prefix',
        ]);

        self::assertSame('/base/old', $normalized['sourceUrl']);
        self::assertSame('/base/old', $normalized['sourceUrlParsed']);
        self::assertSame('pathonly', $normalized['redirectSrcMatch']);
        self::assertSame('prefix', $normalized['matchType']);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int> $editableSiteIds
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function filterImportRows(array $rows, array $editableSiteIds): array
    {
        $controller = new ImportExportController('import-export', RedirectManager::getInstance());
        $method = new ReflectionMethod($controller, 'filterImportRowsForEditableSites');
        $method->setAccessible(true);

        return $method->invoke($controller, $rows, $editableSiteIds);
    }
}

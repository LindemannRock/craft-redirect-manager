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
use craft\helpers\Db;
use craft\helpers\StringHelper;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\tests\TestCase;
use yii\db\IntegrityException;

/**
 * Covers the database-backed redirect uniqueness contract.
 *
 * @since 5.34.0
 */
final class RedirectUniqueScopeTest extends TestCase
{
    public function testSameSourceIsAllowedAcrossDifferentSiteScopes(): void
    {
        [$siteA, $siteB] = $this->siteIds();
        $source = '/' . self::MARKER . 'unique_scope_' . substr(uniqid('', true), -8);

        $this->insertRedirect($source, null);
        $this->insertRedirect($source, $siteA);
        $this->insertRedirect($source, $siteB);

        self::assertSame(3, $this->countRows(RedirectRecord::tableName(), ['sourceUrlParsed' => $source]));
    }

    public function testDuplicateGlobalRedirectSourceIsRejected(): void
    {
        $source = '/' . self::MARKER . 'unique_global_' . substr(uniqid('', true), -8);

        $this->insertRedirect($source, null);

        $this->expectException(IntegrityException::class);
        $this->insertRedirect($source, null);
    }

    public function testDuplicateSiteRedirectSourceIsRejected(): void
    {
        [$siteA] = $this->siteIds();
        $source = '/' . self::MARKER . 'unique_site_' . substr(uniqid('', true), -8);

        $this->insertRedirect($source, $siteA);

        $this->expectException(IntegrityException::class);
        $this->insertRedirect($source, $siteA);
    }

    /**
     * @return array<int>
     */
    private function siteIds(): array
    {
        $siteIds = array_map(
            static fn($site): int => (int)$site->id,
            Craft::$app->getSites()->getAllSites(),
        );

        self::assertGreaterThanOrEqual(2, count($siteIds), 'This integration test requires at least two configured sites.');

        return array_values($siteIds);
    }

    private function insertRedirect(string $sourceUrl, ?int $siteId): void
    {
        $now = Db::prepareDateForDb(new \DateTime());

        Craft::$app->getDb()->createCommand()->insert(RedirectRecord::tableName(), [
            'siteId' => $siteId,
            'sourceUrl' => $sourceUrl,
            'sourceUrlParsed' => $sourceUrl,
            'siteIdKey' => RedirectRecord::siteIdKey($siteId),
            'destinationUrl' => '/destination',
            'redirectSrcMatch' => 'pathonly',
            'matchType' => 'exact',
            'statusCode' => 301,
            'enabled' => true,
            'priority' => 0,
            'creationType' => 'manual',
            'sourcePlugin' => 'redirect-manager',
            'hitCount' => 0,
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => StringHelper::UUID(),
        ])->execute();
    }
}

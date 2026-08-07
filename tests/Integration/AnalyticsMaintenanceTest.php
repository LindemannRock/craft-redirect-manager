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
use lindemannrock\base\testing\StubConsoleRequest;
use lindemannrock\redirectmanager\jobs\CleanupAnalyticsJob;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\analytics\AnalyticsExportService;
use lindemannrock\redirectmanager\services\analytics\AnalyticsMaintenanceService;
use lindemannrock\redirectmanager\services\AnalyticsService;
use lindemannrock\redirectmanager\tests\TestCase;
use RuntimeException;

/**
 * Verifies independent retention and limit cleanup behavior.
 *
 * @since 5.41.0
 */
final class AnalyticsMaintenanceTest extends TestCase
{
    public function testCleanupPolicyCoversEverySupportedSettingsCombination(): void
    {
        $settings = $this->settings();

        $settings->enableAnalytics = false;
        $settings->analyticsRetention = 30;
        $settings->autoTrimAnalytics = true;
        self::assertSame(
            ['retention' => false, 'limit' => false, 'eligible' => false],
            $this->analytics->maintenance->getPolicy(),
        );

        $settings->enableAnalytics = true;
        $settings->analyticsRetention = 30;
        $settings->autoTrimAnalytics = false;
        self::assertSame(
            ['retention' => true, 'limit' => false, 'eligible' => true],
            $this->analytics->maintenance->getPolicy(),
        );

        $settings->analyticsRetention = 0;
        $settings->autoTrimAnalytics = true;
        self::assertSame(
            ['retention' => false, 'limit' => true, 'eligible' => true],
            $this->analytics->maintenance->getPolicy(),
        );

        $settings->analyticsRetention = 30;
        self::assertSame(
            ['retention' => true, 'limit' => true, 'eligible' => true],
            $this->analytics->maintenance->getPolicy(),
        );

        $settings->analyticsRetention = 0;
        $settings->autoTrimAnalytics = false;
        self::assertSame(
            ['retention' => false, 'limit' => false, 'eligible' => false],
            $this->analytics->maintenance->getPolicy(),
        );
    }

    public function testLimitCleanupConvergesAboveLimitAndPreservesNewestRows(): void
    {
        $this->enableLimitOnly(3);
        $oldest = $this->insertAnalytics('limit-oldest', '2030-01-01 00:00:00');
        $second = $this->insertAnalytics('limit-second', '2030-01-02 00:00:00');
        $kept = [
            $this->insertAnalytics('limit-third', '2030-01-03 00:00:00'),
            $this->insertAnalytics('limit-fourth', '2030-01-04 00:00:00'),
            $this->insertAnalytics('limit-newest', '2030-01-05 00:00:00'),
        ];

        $result = $this->analytics->maintenance->runCleanup();

        self::assertSame(['retentionDeleted' => 0, 'limitDeleted' => 2], $result);
        self::assertSame(3, $this->analyticsRowCount());
        self::assertFalse($this->analyticsRowExists($oldest));
        self::assertFalse($this->analyticsRowExists($second));
        foreach ($kept as $id) {
            self::assertTrue($this->analyticsRowExists($id));
        }
    }

    public function testLimitCleanupLeavesEqualAndBelowLimitStatesUnchanged(): void
    {
        $this->enableLimitOnly(3);
        $this->insertAnalytics('equal-one', '2030-01-01 00:00:00');
        $this->insertAnalytics('equal-two', '2030-01-02 00:00:00');
        $this->insertAnalytics('equal-three', '2030-01-03 00:00:00');

        self::assertSame(0, $this->analytics->maintenance->runCleanup()['limitDeleted']);
        self::assertSame(3, $this->analyticsRowCount());

        Craft::$app->getDb()->createCommand()->delete(
            '{{%redirectmanager_analytics}}',
            ['urlParsed' => '/' . self::MARKER . 'equal-three'],
        )->execute();

        self::assertSame(0, $this->analytics->maintenance->runCleanup()['limitDeleted']);
        self::assertSame(2, $this->analyticsRowCount());
    }

    public function testRetentionOnlyCleanupDoesNotEnforceLimit(): void
    {
        $settings = $this->settings();
        $settings->enableAnalytics = true;
        $settings->analyticsRetention = 30;
        $settings->autoTrimAnalytics = false;
        $settings->analyticsLimit = 1;
        $this->insertAnalytics('retention-fresh-one', '2030-01-01 00:00:00');
        $this->insertAnalytics('retention-fresh-two', '2030-01-02 00:00:00');

        $result = $this->analytics->maintenance->runCleanup();

        self::assertSame(['retentionDeleted' => 0, 'limitDeleted' => 0], $result);
        self::assertSame(2, $this->analyticsRowCount());
    }

    public function testLimitOnlyCleanupDoesNotApplyRetention(): void
    {
        $this->enableLimitOnly(3);
        $id = $this->insertAnalytics('limit-old-but-kept', '2020-01-01 00:00:00');

        $result = $this->analytics->maintenance->runCleanup();

        self::assertSame(['retentionDeleted' => 0, 'limitDeleted' => 0], $result);
        self::assertTrue($this->analyticsRowExists($id));
    }

    public function testRetentionAndLimitCleanupCooperateInOneExecution(): void
    {
        $settings = $this->settings();
        $settings->enableAnalytics = true;
        $settings->analyticsRetention = 30;
        $settings->autoTrimAnalytics = true;
        $settings->analyticsLimit = 2;
        $this->insertAnalytics('both-expired-one', '2020-01-01 00:00:00');
        $this->insertAnalytics('both-expired-two', '2020-01-02 00:00:00');
        $this->insertAnalytics('both-fresh-one', '2030-01-01 00:00:00');
        $this->insertAnalytics('both-fresh-two', '2030-01-02 00:00:00');
        $this->insertAnalytics('both-fresh-three', '2030-01-03 00:00:00');

        $result = $this->analytics->maintenance->runCleanup();

        self::assertSame(['retentionDeleted' => 2, 'limitDeleted' => 1], $result);
        self::assertSame(2, $this->analyticsRowCount());
    }

    public function testChangedLimitIsAppliedOnNextCleanup(): void
    {
        $this->enableLimitOnly(3);
        for ($index = 1; $index <= 4; $index++) {
            $this->insertAnalytics("changed-limit-{$index}", "2030-01-0{$index} 00:00:00");
        }

        self::assertSame(1, $this->analytics->maintenance->runCleanup()['limitDeleted']);
        self::assertSame(3, $this->analyticsRowCount());

        $this->settings()->analyticsLimit = 1;
        self::assertSame(2, $this->analytics->maintenance->runCleanup()['limitDeleted']);
        self::assertSame(1, $this->analyticsRowCount());
    }

    public function testNewAnalyticsWriteSurvivesTemporaryOverflowAndNextConvergence(): void
    {
        $this->enableLimitOnly(2);
        $this->insertAnalytics('overflow-old-one', '2020-01-01 00:00:00');
        $this->insertAnalytics('overflow-old-two', '2020-01-02 00:00:00');
        $this->analytics->maintenance->runCleanup();

        $newId = $this->insertAnalytics('overflow-new', '2030-01-01 00:00:00', 1);
        self::assertSame(3, $this->analyticsRowCount());
        self::assertSame(1, $this->analyticsCount($newId));

        self::assertSame(1, $this->analytics->maintenance->runCleanup()['limitDeleted']);
        self::assertSame(2, $this->analyticsRowCount());
        self::assertTrue($this->analyticsRowExists($newId));
        self::assertSame(1, $this->analyticsCount($newId));
    }

    public function testLimitCleanupPreservesACandidateRefreshedBeforeDeletion(): void
    {
        $this->enableLimitOnly(2);
        $this->settings()->enableGeoDetection = false;
        $this->settings()->ipHashSalt = '0123456789abcdef0123456789abcdef';
        Craft::$app->set('request', new StubConsoleRequest(userIp: '203.0.113.85'));
        $refreshedUrl = '/' . self::MARKER . 'concurrent-refresh';
        $refreshedId = $this->insertAnalytics(
            'concurrent-refresh',
            '2020-01-01 00:00:00',
            siteId: Craft::$app->getSites()->getPrimarySite()->id,
        );
        $this->insertAnalytics('concurrent-middle', '2020-01-02 00:00:00');
        $this->insertAnalytics('concurrent-newest', '2020-01-03 00:00:00');
        $export = new class($refreshedUrl) extends AnalyticsExportService {
            public function __construct(private readonly string $refreshedUrl)
            {
            }

            protected function deleteUnchangedTrimCandidates(array $candidates): int
            {
                RedirectManager::$plugin->analytics->record404($this->refreshedUrl, false, [
                    'source' => 'frontend',
                ]);

                return parent::deleteUnchangedTrimCandidates($candidates);
            }
        };
        $maintenance = new AnalyticsMaintenanceService($export);

        self::assertSame(0, $maintenance->runCleanup()['limitDeleted']);

        self::assertTrue($this->analyticsRowExists($refreshedId));
        self::assertSame(2, $this->analyticsCount($refreshedId));
        self::assertSame(3, $this->analyticsRowCount());

        self::assertSame(1, $this->analytics->maintenance->runCleanup()['limitDeleted']);
        self::assertSame(2, $this->analyticsRowCount());
        self::assertTrue($this->analyticsRowExists($refreshedId));
        self::assertSame(2, $this->analyticsCount($refreshedId));
    }

    public function testCleanupFailureDoesNotScheduleASecondRecurringFamily(): void
    {
        $this->settings()->enableAnalytics = true;
        $this->settings()->analyticsRetention = 0;
        $this->settings()->autoTrimAnalytics = true;
        $this->pushOwnedJob(new CleanupAnalyticsJob(['reschedule' => true]), 300);
        $analytics = new AnalyticsService();
        $analytics->maintenance = new class($analytics->export) extends AnalyticsMaintenanceService {
            public function runCleanup(): array
            {
                throw new RuntimeException('Synthetic analytics cleanup failure.');
            }
        };
        $this->replacePluginComponent('analytics', $analytics);
        $job = new CleanupAnalyticsJob(['reschedule' => true]);

        try {
            $job->execute(Craft::$app->getQueue());
            self::fail('The synthetic cleanup failure must escape for the queue to record it.');
        } catch (RuntimeException $exception) {
            self::assertSame('Synthetic analytics cleanup failure.', $exception->getMessage());
        }

        self::assertFalse($job->canRetry(1, new RuntimeException()));
        self::assertSame(1, $this->countRows('{{%queue}}'));
    }

    private function enableLimitOnly(int $limit): void
    {
        $settings = $this->settings();
        $settings->enableAnalytics = true;
        $settings->analyticsRetention = 0;
        $settings->autoTrimAnalytics = true;
        $settings->analyticsLimit = $limit;
    }

    private function insertAnalytics(string $suffix, string $lastHit, int $count = 1, ?int $siteId = null): int
    {
        $url = '/' . self::MARKER . $suffix;
        $date = Db::prepareDateForDb(new \DateTime($lastHit));
        Craft::$app->getDb()->createCommand()->insert('{{%redirectmanager_analytics}}', [
            'url' => $url,
            'urlParsed' => $url,
            'handled' => false,
            'siteId' => $siteId,
            'count' => $count,
            'lastHit' => $date,
            'dateCreated' => $date,
            'dateUpdated' => $date,
            'uid' => StringHelper::UUID(),
        ])->execute();

        return (int)Craft::$app->getDb()->getLastInsertID();
    }

    private function analyticsRowCount(): int
    {
        return $this->countRows('{{%redirectmanager_analytics}}');
    }

    private function analyticsRowExists(int $id): bool
    {
        return $this->fetchRow('{{%redirectmanager_analytics}}', ['id' => $id]) !== null;
    }

    private function analyticsCount(int $id): int
    {
        $row = $this->fetchRow('{{%redirectmanager_analytics}}', ['id' => $id]);
        self::assertNotNull($row);

        return (int)$row['count'];
    }
}

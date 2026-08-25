<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\migrations;

use craft\db\Migration;
use craft\db\Table;
use Throwable;

/**
 * Adds the post-cutover daily dimensional analytics authority.
 *
 * Existing summary rows are intentionally not backfilled: their cumulative
 * counts do not retain the historical dimensions required for truthful rows.
 *
 * @since 5.41.0
 */
final class m260825_000000_create_analytics_daily extends Migration
{
    public function safeUp(): bool
    {
        self::createDailyTable($this);

        return true;
    }

    /**
     * Create the identical fresh-install and upgrade schema.
     */
    public static function createDailyTable(Migration $migration): void
    {
        if ($migration->db->tableExists('{{%redirectmanager_analytics_daily}}')) {
            return;
        }

        try {
            $migration->createTable('{{%redirectmanager_analytics_daily}}', [
                'id' => $migration->primaryKey(),
                'bucketKey' => $migration->char(64)->notNull(),
                'hitDate' => $migration->date()->notNull(),
                'siteId' => $migration->integer(),
                'url' => $migration->string(500)->notNull(),
                'urlParsed' => $migration->string(500)->notNull(),
                'handled' => $migration->boolean()->notNull()->defaultValue(false),
                'redirectId' => $migration->integer()->null(),
                'sourcePlugin' => $migration->string(50)->notNull()->defaultValue('redirect-manager'),
                'count' => $migration->integer()->notNull()->defaultValue(1),
                'referrer' => $migration->string(500),
                'ip' => $migration->string(64)->null(),
                'userAgent' => $migration->string(500),
                'language' => $migration->string(10)->null(),
                'deviceType' => $migration->string(50)->null(),
                'deviceBrand' => $migration->string(50)->null(),
                'deviceModel' => $migration->string(100)->null(),
                'browser' => $migration->string(100)->null(),
                'browserVersion' => $migration->string(20)->null(),
                'browserEngine' => $migration->string(50)->null(),
                'osName' => $migration->string(50)->null(),
                'osVersion' => $migration->string(50)->null(),
                'clientType' => $migration->string(50)->null(),
                'isRobot' => $migration->boolean()->defaultValue(false),
                'isMobileApp' => $migration->boolean()->defaultValue(false),
                'botName' => $migration->string(100)->null(),
                'botCategory' => $migration->string(100)->null(),
                'botUrl' => $migration->string(255)->null(),
                'botProducerName' => $migration->string(100)->null(),
                'botProducerUrl' => $migration->string(255)->null(),
                'isSystemAgent' => $migration->boolean()->defaultValue(false),
                'trafficType' => $migration->string(20)->notNull()->defaultValue('human'),
                'requestType' => $migration->string(20)->notNull()->defaultValue('normal'),
                'country' => $migration->string(2)->null(),
                'city' => $migration->string(100)->null(),
                'region' => $migration->string(100)->null(),
                'latitude' => $migration->decimal(10, 8)->null(),
                'longitude' => $migration->decimal(11, 8)->null(),
                'lastHit' => $migration->dateTime()->notNull(),
                'uid' => $migration->uid(),
                'dateCreated' => $migration->dateTime()->notNull(),
                'dateUpdated' => $migration->dateTime()->notNull(),
            ]);

            $migration->createIndex('idx_redirectmanager_analytics_daily_bucket_unq', '{{%redirectmanager_analytics_daily}}', ['bucketKey'], true);
            foreach ([['hitDate'], ['siteId'], ['urlParsed'], ['handled'], ['redirectId'], ['lastHit'], ['requestType'], ['deviceType'], ['browser'], ['osName'], ['country']] as $columns) {
                $migration->createIndex(null, '{{%redirectmanager_analytics_daily}}', $columns);
            }
            $migration->addForeignKey(null, '{{%redirectmanager_analytics_daily}}', ['siteId'], Table::SITES, ['id'], 'CASCADE', 'CASCADE');
        } catch (Throwable $exception) {
            // MySQL DDL auto-commits. Remove only this migration's newly
            // created table so a failed upgrade remains exactly retryable.
            if ($migration->db->tableExists('{{%redirectmanager_analytics_daily}}')) {
                $migration->dropTable('{{%redirectmanager_analytics_daily}}');
            }
            throw $exception;
        }
    }

    public function safeDown(): bool
    {
        if ($this->db->tableExists('{{%redirectmanager_analytics_daily}}')) {
            $this->dropTable('{{%redirectmanager_analytics_daily}}');
        }

        return true;
    }
}

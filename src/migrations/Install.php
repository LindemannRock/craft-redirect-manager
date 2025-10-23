<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\StringHelper;

/**
 * Install Migration
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Create redirects table
        if (!$this->db->tableExists('{{%redirectmanager_redirects}}')) {
            $this->createTable('{{%redirectmanager_redirects}}', [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer(),
                'sourceUrl' => $this->string(255)->notNull(),
                'sourceUrlParsed' => $this->string(255)->notNull(),
                'destinationUrl' => $this->string(500)->notNull(),
                'redirectSrcMatch' => $this->string(20)->notNull()->defaultValue('pathonly'),
                'matchType' => $this->enum('matchType', ['exact', 'regex', 'wildcard', 'prefix'])->notNull()->defaultValue('exact'),
                'statusCode' => $this->integer()->notNull()->defaultValue(301),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'priority' => $this->integer()->notNull()->defaultValue(0),
                'creationType' => $this->string(50)->notNull()->defaultValue('manual'),
                'sourcePlugin' => $this->string(50)->notNull()->defaultValue('redirect-manager'),
                'elementId' => $this->integer()->null(),
                'hitCount' => $this->integer()->notNull()->defaultValue(0),
                'lastHit' => $this->dateTime(),
                'uid' => $this->uid(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
            ]);

            // Add indexes for performance
            $this->createIndex(null, '{{%redirectmanager_redirects}}', ['sourceUrlParsed'], false);
            $this->createIndex(null, '{{%redirectmanager_redirects}}', ['matchType'], false);
            $this->createIndex(null, '{{%redirectmanager_redirects}}', ['siteId'], false);
            $this->createIndex(null, '{{%redirectmanager_redirects}}', ['enabled'], false);
            $this->createIndex(null, '{{%redirectmanager_redirects}}', ['priority'], false);

            // Add foreign key for siteId
            $this->addForeignKey(
                null,
                '{{%redirectmanager_redirects}}',
                ['siteId'],
                Table::SITES,
                ['id'],
                'CASCADE',
                'CASCADE'
            );
        }

        // Create settings table
        if (!$this->db->tableExists('{{%redirectmanager_settings}}')) {
            $this->createTable('{{%redirectmanager_settings}}', [
                'id' => $this->primaryKey(),
                'pluginName' => $this->string(255)->notNull()->defaultValue('Redirect Manager'),
                'autoCreateRedirects' => $this->boolean()->notNull()->defaultValue(true),
                'undoWindowMinutes' => $this->integer()->notNull()->defaultValue(60),
                'redirectSrcMatch' => $this->string(20)->notNull()->defaultValue('pathonly'),
                'stripQueryString' => $this->boolean()->notNull()->defaultValue(false),
                'preserveQueryString' => $this->boolean()->notNull()->defaultValue(false),
                'setNoCacheHeaders' => $this->boolean()->notNull()->defaultValue(true),
                'enableAnalytics' => $this->boolean()->notNull()->defaultValue(true),
                'anonymizeIpAddress' => $this->boolean()->notNull()->defaultValue(false),
                'enableGeoDetection' => $this->boolean()->notNull()->defaultValue(false),
                'cacheDeviceDetection' => $this->boolean()->notNull()->defaultValue(true),
                'deviceDetectionCacheDuration' => $this->integer()->notNull()->defaultValue(3600),
                'stripQueryStringFromStats' => $this->boolean()->notNull()->defaultValue(true),
                'statisticsLimit' => $this->integer()->notNull()->defaultValue(1000),
                'statisticsRetention' => $this->integer()->notNull()->defaultValue(30),
                'autoTrimStatistics' => $this->boolean()->notNull()->defaultValue(true),
                'refreshIntervalSecs' => $this->integer()->notNull()->defaultValue(5),
                'redirectsDisplayLimit' => $this->integer()->notNull()->defaultValue(100),
                'statisticsDisplayLimit' => $this->integer()->notNull()->defaultValue(100),
                'itemsPerPage' => $this->integer()->notNull()->defaultValue(100),
                'enableApiEndpoint' => $this->boolean()->notNull()->defaultValue(false),
                'excludePatterns' => $this->text()->null()->comment('JSON array'),
                'additionalHeaders' => $this->text()->null()->comment('JSON array'),
                'logLevel' => $this->string(20)->notNull()->defaultValue('error'),
                'enableRedirectCache' => $this->boolean()->notNull()->defaultValue(true),
                'redirectCacheDuration' => $this->integer()->notNull()->defaultValue(3600),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // Insert default settings row
            $this->insert('{{%redirectmanager_settings}}', [
                'dateCreated' => Db::prepareDateForDb(new \DateTime()),
                'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
                'uid' => StringHelper::UUID(),
            ]);
        }

        // Create statistics table
        if (!$this->db->tableExists('{{%redirectmanager_analytics}}')) {
            $this->createTable('{{%redirectmanager_analytics}}', [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer(),
                'url' => $this->string(500)->notNull(),
                'urlParsed' => $this->string(500)->notNull(),
                'handled' => $this->boolean()->notNull()->defaultValue(false),
                'sourcePlugin' => $this->string(50)->notNull()->defaultValue('redirect-manager'),
                'count' => $this->integer()->notNull()->defaultValue(1),
                'referrer' => $this->string(500),
                'ip' => $this->string(64)->null(),
                'userAgent' => $this->string(500),
                // Device detection fields
                'deviceType' => $this->string(50)->null(),
                'deviceBrand' => $this->string(50)->null(),
                'deviceModel' => $this->string(100)->null(),
                'browser' => $this->string(100)->null(),
                'browserVersion' => $this->string(20)->null(),
                'browserEngine' => $this->string(50)->null(),
                'osName' => $this->string(50)->null(),
                'osVersion' => $this->string(50)->null(),
                'clientType' => $this->string(50)->null(),
                'isRobot' => $this->boolean()->defaultValue(false),
                'isMobileApp' => $this->boolean()->defaultValue(false),
                'botName' => $this->string(100)->null(),
                // Geographic data
                'country' => $this->string(2)->null(),
                'city' => $this->string(100)->null(),
                'region' => $this->string(100)->null(),
                'latitude' => $this->decimal(10, 8)->null(),
                'longitude' => $this->decimal(11, 8)->null(),
                'lastHit' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
            ]);

            // Add indexes for performance
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['urlParsed'], false);
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['handled'], false);
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['siteId'], false);
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['lastHit'], false);
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['count'], false);
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['sourcePlugin'], false);
            // Device detection indexes
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['isRobot'], false);
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['deviceType'], false);
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['browser'], false);
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['osName'], false);
            // Geographic indexes
            $this->createIndex(null, '{{%redirectmanager_analytics}}', ['country'], false);

            // Add foreign key for siteId
            $this->addForeignKey(
                null,
                '{{%redirectmanager_analytics}}',
                ['siteId'],
                Table::SITES,
                ['id'],
                'CASCADE',
                'CASCADE'
            );
        }

        // Create import history table
        if (!$this->db->tableExists('{{%redirectmanager_import_history}}')) {
            $this->createTable('{{%redirectmanager_import_history}}', [
                'id' => $this->primaryKey(),
                'importedCount' => $this->integer()->notNull()->defaultValue(0),
                'duplicatesCount' => $this->integer()->notNull()->defaultValue(0),
                'errorsCount' => $this->integer()->notNull()->defaultValue(0),
                'backupPath' => $this->string(500),
                'importedBy' => $this->integer(),
                'uid' => $this->uid(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
            ]);

            // Add foreign key for importedBy (user)
            $this->addForeignKey(
                null,
                '{{%redirectmanager_import_history}}',
                ['importedBy'],
                Table::USERS,
                ['id'],
                'SET NULL',
                'CASCADE'
            );

            // Add index on dateCreated
            $this->createIndex(null, '{{%redirectmanager_import_history}}', ['dateCreated'], false);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop tables in reverse order
        $this->dropTableIfExists('{{%redirectmanager_import_history}}');
        $this->dropTableIfExists('{{%redirectmanager_analytics}}');
        $this->dropTableIfExists('{{%redirectmanager_settings}}');
        $this->dropTableIfExists('{{%redirectmanager_redirects}}');

        return true;
    }
}

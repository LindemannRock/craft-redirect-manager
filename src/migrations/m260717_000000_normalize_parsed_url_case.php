<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * Lowercases equality-matched sourceUrlParsed values and all analytics
 * urlParsed values so bookkeeping (duplicate checks, unique indexes, loop
 * detection, 404-count merging) behaves case-insensitively on PostgreSQL the
 * way MySQL's ci collation always did implicitly. Pattern rows
 * (regex/wildcard) are intentionally untouched — lowercasing a pattern
 * corrupts it (\W would become \w). Runtime matching is case-blind for all
 * match types, so this never changes which redirects fire.
 *
 * Collisions are impossible on MySQL (its ci unique indexes never allowed
 * case-variant duplicates) but could exist on a pre-existing PostgreSQL
 * install, so they are handled gracefully: redirect rows are skipped with a
 * warning; analytics rows are merged (counts summed, latest lastHit kept).
 */
class m260717_000000_normalize_parsed_url_case extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->normalizeRedirects();
        $this->normalizeAnalytics();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260717_000000_normalize_parsed_url_case cannot be reverted (original casing is not preserved).\n";

        return false;
    }

    private function normalizeRedirects(): void
    {
        // Collect first, mutate after — keeps iteration independent of updates.
        $candidates = [];
        foreach ((new Query())
            ->select(['id', 'sourceUrlParsed', 'siteIdKey'])
            ->from('{{%redirectmanager_redirects}}')
            ->where(['matchType' => ['exact', 'prefix']])
            ->batch(500, $this->db) as $batch) {
            foreach ($batch as $row) {
                if (strtolower($row['sourceUrlParsed']) !== $row['sourceUrlParsed']) {
                    $candidates[] = $row;
                }
            }
        }

        foreach ($candidates as $row) {
            $lower = strtolower($row['sourceUrlParsed']);

            $conflict = (new Query())
                ->from('{{%redirectmanager_redirects}}')
                ->where(['sourceUrlParsed' => $lower, 'siteIdKey' => $row['siteIdKey']])
                ->andWhere(['!=', 'id', $row['id']])
                ->exists($this->db);

            if ($conflict) {
                echo "    > skipped redirect #{$row['id']}: lowercasing would collide with an existing row\n";
                continue;
            }

            $this->update('{{%redirectmanager_redirects}}', ['sourceUrlParsed' => $lower], ['id' => $row['id']], [], false);
        }
    }

    private function normalizeAnalytics(): void
    {
        $candidates = [];
        foreach ((new Query())
            ->select(['id', 'urlParsed', 'siteId'])
            ->from('{{%redirectmanager_analytics}}')
            ->batch(500, $this->db) as $batch) {
            foreach ($batch as $row) {
                if (strtolower($row['urlParsed']) !== $row['urlParsed']) {
                    $candidates[] = $row;
                }
            }
        }

        foreach ($candidates as $row) {
            $lower = strtolower($row['urlParsed']);

            $survivor = (new Query())
                ->select(['id', 'count', 'lastHit'])
                ->from('{{%redirectmanager_analytics}}')
                ->where(['urlParsed' => $lower, 'siteId' => $row['siteId']])
                ->andWhere(['!=', 'id', $row['id']])
                ->one($this->db);

            if ($survivor) {
                $variant = (new Query())
                    ->select(['count', 'lastHit'])
                    ->from('{{%redirectmanager_analytics}}')
                    ->where(['id' => $row['id']])
                    ->one($this->db);

                $this->update('{{%redirectmanager_analytics}}', [
                    'count' => (int)$survivor['count'] + (int)($variant['count'] ?? 0),
                    'lastHit' => max((string)$survivor['lastHit'], (string)($variant['lastHit'] ?? '')),
                ], ['id' => $survivor['id']], [], false);
                $this->delete('{{%redirectmanager_analytics}}', ['id' => $row['id']]);
            } else {
                $this->update('{{%redirectmanager_analytics}}', ['urlParsed' => $lower], ['id' => $row['id']], [], false);
            }
        }
    }
}

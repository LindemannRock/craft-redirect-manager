<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\records;

use craft\db\ActiveRecord;
use craft\records\Site;
use yii\db\ActiveQueryInterface;

/**
 * Statistic Record
 *
 * @property int $id
 * @property int|null $siteId
 * @property string $url
 * @property string $urlParsed
 * @property bool $handled
 * @property string $sourcePlugin
 * @property int $count
 * @property string|null $referrer
 * @property string|null $ip
 * @property string $lastHit
 * @property string $uid
 * @property string $dateCreated
 * @property string $dateUpdated
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
class StatisticRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%redirectmanager_analytics}}';
    }

    /**
     * Returns the statistic's site
     *
     * @return ActiveQueryInterface
     */
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'siteId']);
    }
}

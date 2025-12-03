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
 * Analytics Record
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
 * @property string|null $userAgent
 * @property string|null $deviceType
 * @property string|null $deviceBrand
 * @property string|null $deviceModel
 * @property string|null $browser
 * @property string|null $browserVersion
 * @property string|null $browserEngine
 * @property string|null $osName
 * @property string|null $osVersion
 * @property string|null $clientType
 * @property bool|null $isRobot
 * @property bool|null $isMobileApp
 * @property string|null $botName
 * @property string|null $country
 * @property string|null $city
 * @property string|null $region
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string $lastHit
 * @property string $uid
 * @property string $dateCreated
 * @property string $dateUpdated
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
class AnalyticsRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%redirectmanager_analytics}}';
    }

    /**
     * Returns the analytics record's site
     *
     * @return ActiveQueryInterface
     */
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'siteId']);
    }
}

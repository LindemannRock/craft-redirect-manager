<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\records;

use craft\db\ActiveRecord;
use craft\db\SoftDeleteTrait;
use craft\records\Site;
use yii\db\ActiveQueryInterface;

/**
 * Redirect Record
 *
 * @property int $id
 * @property int|null $siteId
 * @property string $sourceUrl
 * @property string $sourceUrlParsed
 * @property string $destinationUrl
 * @property string $redirectSrcMatch
 * @property string $matchType
 * @property int $statusCode
 * @property bool $enabled
 * @property int $priority
 * @property string $creationType
 * @property string $sourcePlugin
 * @property int|null $elementId
 * @property int $hitCount
 * @property string|null $lastHit
 * @property string $uid
 * @property string $dateCreated
 * @property string $dateUpdated
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     1.0.0
 */
class RedirectRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%redirectmanager_redirects}}';
    }

    /**
     * Returns the redirect's site
     *
     * @return ActiveQueryInterface
     */
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'siteId']);
    }
}

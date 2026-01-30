<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\records;

use craft\db\ActiveRecord;

/**
 * Import history record
 *
 * @property int $id
 * @property int|null $userId
 * @property string|null $filename
 * @property int|null $filesize
 * @property int $imported
 * @property int $failed
 * @property string|null $backupPath
 * @property \DateTime|null $dateCreated
 * @property \DateTime|null $dateUpdated
 * @property string|null $uid
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.23.0
 */
class ImportHistoryRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%redirectmanager_import_history}}';
    }
}

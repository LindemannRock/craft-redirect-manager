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
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['sourceUrl', 'destinationUrl', 'redirectSrcMatch'], 'required'];
        $rules[] = [['sourceUrl'], 'validateSourceUrl'];
        $rules[] = [['destinationUrl'], 'validateDestinationUrl'];

        return $rules;
    }

    /**
     * Validate source URL based on match mode and match type
     */
    public function validateSourceUrl($attribute): void
    {
        $url = $this->$attribute;

        if (empty($url)) {
            return;
        }

        // For regex patterns, validate they contain the expected format
        if ($this->matchType === 'regex') {
            // Check if pattern contains meaningful regex special characters
            // Exclude plain dots in domain names - look for actual regex features:
            // - Anchors: ^ $
            // - Quantifiers: * + ? {n,m}
            // - Character classes: []
            // - Groups: ()
            // - Alternation: |
            // - Escaped characters: \. \d \w \s etc
            // - Wildcard patterns: .*
            if (!preg_match('/[\^\$\*\+\?\[\]\(\)\{\}\|]|\\\\.|\.[\*\+\?]/', $url)) {
                $this->addError($attribute, \Craft::t('redirect-manager', 'Regex pattern must contain regex special characters (e.g., ^, $, .*, +, [], etc.). For exact matching, use Exact Match instead.'));
            }

            if ($this->redirectSrcMatch === 'pathonly') {
                // Regex should contain a path pattern (has / somewhere)
                if (strpos($url, '/') === false) {
                    $this->addError($attribute, \Craft::t('redirect-manager', 'Regex pattern must contain a path (e.g., ^/blog/.* or /category/[^/]+) when using Path Only mode.'));
                }
            } elseif ($this->redirectSrcMatch === 'fullurl') {
                // Regex should contain https:// or http://
                if (strpos($url, 'https://') === false && strpos($url, 'http://') === false) {
                    $this->addError($attribute, \Craft::t('redirect-manager', 'Regex pattern must contain a full URL with https:// or http:// (e.g., ^https://example.com/blog/.*) when using Full URL mode.'));
                }
            }
            return;
        }

        // For all other match types, enforce format based on source match mode
        $isPath = str_starts_with($url, '/');
        $isFullUrl = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');

        if ($this->redirectSrcMatch === 'pathonly') {
            if (!$isPath) {
                $this->addError($attribute, \Craft::t('redirect-manager', 'Source URL must be a path starting with / (e.g., /old-page or /blog/*) when using Path Only mode.'));
            }
        } elseif ($this->redirectSrcMatch === 'fullurl') {
            if (!$isFullUrl) {
                $this->addError($attribute, \Craft::t('redirect-manager', 'Source URL must be a full URL starting with https:// or http:// (e.g., https://example.com/old-page) when using Full URL mode.'));
            }
        }

        // Check for wildcard character usage
        if (strpos($url, '*') !== false) {
            if ($this->matchType === 'wildcard') {
                // Wildcards are expected and allowed
            } elseif ($this->matchType === 'prefix' || $this->matchType === 'begins_with') {
                $this->addError($attribute, \Craft::t('redirect-manager', 'Wildcard character (*) is not allowed in Prefix Match. Use Wildcard Match instead, or remove the *.'));
            } elseif ($this->matchType === 'exact' || $this->matchType === 'contains') {
                $this->addError($attribute, \Craft::t('redirect-manager', 'Wildcard character (*) is not allowed in {matchType} Match. Use Wildcard Match instead.', ['matchType' => ucfirst($this->matchType)]));
            }
        } elseif ($this->matchType === 'wildcard') {
            // Wildcard match requires at least one * character
            $this->addError($attribute, \Craft::t('redirect-manager', 'Wildcard Match requires at least one * wildcard character in the pattern (e.g., /blog/* or https://example.com/*).'));
        }
    }

    /**
     * Validate destination URL
     */
    public function validateDestinationUrl($attribute): void
    {
        $url = $this->$attribute;

        if (empty($url)) {
            return;
        }

        $isPath = str_starts_with($url, '/');
        $isFullUrl = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');

        if (!$isPath && !$isFullUrl) {
            $this->addError($attribute, \Craft::t('redirect-manager', 'Please enter a valid URL starting with https:// or http://, or a path starting with / (e.g., https://example.com or /page)'));
        }
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

<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\records;

use craft\db\ActiveRecord;
use craft\records\Site;
use lindemannrock\base\helpers\UrlSafetyHelper;
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
 * @since     5.0.0
 */
class RedirectRecord extends ActiveRecord
{
    /**
     * Schemes accepted as redirect destinations in addition to relative paths
     * and http(s) URLs. These back vanity action links (e.g. /call → tel:).
     */
    private const DESTINATION_SCHEMES = ['mailto', 'tel', 'whatsapp', 'sms', 'fax', 'skype', 'slack', 'msteams'];

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
        $rules[] = [['destinationUrl'], 'validateCaptureReferences'];

        return $rules;
    }

    /**
     * Validate source URL based on match mode and match type
     *
     * @param string $attribute
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
        $isFullUrl = UrlSafetyHelper::isHttpUrlWithHost($url);

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
            } elseif ($this->matchType === 'prefix') {
                $this->addError($attribute, \Craft::t('redirect-manager', 'Wildcard character (*) is not allowed in Prefix Match. Use Wildcard Match instead, or remove the *.'));
            } elseif ($this->matchType === 'exact') {
                $this->addError($attribute, \Craft::t('redirect-manager', 'Wildcard character (*) is not allowed in {matchType} Match. Use Wildcard Match instead.', ['matchType' => ucfirst($this->matchType)]));
            }
        } elseif ($this->matchType === 'wildcard') {
            // Wildcard match requires at least one * character
            $this->addError($attribute, \Craft::t('redirect-manager', 'Wildcard Match requires at least one * wildcard character in the pattern (e.g., /blog/* or https://example.com/*).'));
        }
    }

    /**
     * Validate destination URL
     *
     * @param string $attribute
     */
    public function validateDestinationUrl($attribute): void
    {
        $url = $this->$attribute;

        if (empty($url)) {
            return;
        }

        if (!self::isValidDestination($url)) {
            $this->addError($attribute, \Craft::t('redirect-manager', 'Enter a path (/page), a full URL (https://example.com), or a contact link (e.g. mailto:, tel:). Protocol-relative URLs (//host) are not allowed.'));
        }
    }

    /**
     * Validate that capture references ($1, $2, …) in the destination can be
     * produced by the chosen match type and source pattern.
     *
     * @param string $attribute
     * @since 5.33.0
     */
    public function validateCaptureReferences($attribute): void
    {
        $url = $this->$attribute;

        if (empty($url)) {
            return;
        }

        $error = self::captureReferenceError($url, (string)$this->matchType, (string)$this->sourceUrl);
        if ($error !== null) {
            $this->addError($attribute, $error);
        }
    }

    /**
     * Whether a destination value is a valid redirect target: a relative path
     * (not protocol-relative //host), an http(s) URL, a recognized contact/app
     * scheme, or a capture reference ($1, $2, …).
     *
     * Shared by the CP form and CSV import so the two surfaces can't drift.
     *
     * @param string $url
     * @return bool
     * @since 5.33.0
     */
    public static function isValidDestination(string $url): bool
    {
        if (UrlSafetyHelper::hasDangerousScheme($url)) {
            return false;
        }

        // Capture reference ($1, $2, …) resolved at redirect time.
        if (preg_match('#^\$\d#', $url) === 1) {
            return true;
        }

        return UrlSafetyHelper::isSafeRedirectUrl($url, self::DESTINATION_SCHEMES);
    }

    /**
     * Returns an error message when the destination references a capture group
     * ($1, $2, …) the match type / source pattern can't produce, or null when
     * the references are valid. $0 (full match) is always allowed.
     *
     * @param string $destination
     * @param string $matchType
     * @param string $sourceUrl
     * @return string|null
     * @since 5.33.0
     */
    public static function captureReferenceError(string $destination, string $matchType, string $sourceUrl): ?string
    {
        if (preg_match_all('/\$(\d+)/', $destination, $matches) < 1) {
            return null;
        }

        $refs = array_map('intval', $matches[1]);
        if ($refs === []) {
            return null;
        }

        $maxRef = max($refs);
        if ($maxRef < 1) {
            return null;
        }

        if ($matchType === 'exact') {
            return \Craft::t('redirect-manager', "Exact Match produces no captures, so the destination can't use $1, $2, etc. Choose Wildcard, Prefix, or RegEx, or remove the capture reference.");
        }

        $capacity = match ($matchType) {
            'prefix' => 1,
            'wildcard' => substr_count($sourceUrl, '*'),
            'regex' => self::countCaptureGroups($sourceUrl),
            default => 0,
        };

        if ($maxRef > $capacity) {
            return \Craft::t('redirect-manager', "The destination references {ref}, but the source pattern doesn't provide that many captures.", ['ref' => '$' . $maxRef]);
        }

        return null;
    }

    /**
     * Count capturing groups in a regex source pattern. Deliberately over-counts
     * (treats non-capturing groups as capturing) so a valid capture reference is
     * never wrongly rejected — only genuinely impossible references are flagged.
     *
     * @param string $pattern
     * @return int
     */
    private static function countCaptureGroups(string $pattern): int
    {
        $stripped = preg_replace('/\\\\./', '', $pattern) ?? $pattern;
        $stripped = preg_replace('/\[[^\]]*\]/', '', $stripped) ?? $stripped;

        return substr_count($stripped, '(');
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

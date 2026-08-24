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
use lindemannrock\redirectmanager\services\MatchingService;
use yii\db\ActiveQueryInterface;

/**
 * Redirect Record
 *
 * @property int $id
 * @property int|null $siteId
 * @property string $sourceUrl
 * @property string $sourceUrlParsed
 * @property int $siteIdKey
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
     * Return the normalized site key used for redirect uniqueness.
     *
     * Global redirects use `0` so the database can enforce one global redirect
     * per parsed source URL even though `siteId` itself remains nullable.
     *
     * @since 5.34.0
     */
    public static function siteIdKey(?int $siteId): int
    {
        return $siteId ?? 0;
    }

    /**
     * Return the canonical database identity for one parsed redirect source.
     *
     * Match type and source match mode describe how a redirect is evaluated;
     * the unique database identity is the parsed source plus its site scope.
     *
     * @since 5.41.0
     */
    public static function sourceIdentityKey(string $sourceUrlParsed, int $siteIdKey): string
    {
        return $sourceUrlParsed . "\n" . $siteIdKey;
    }

    /**
     * Normalize one source URL for persistence and identity comparisons.
     *
     * Path-only Exact and Prefix sources accept an HTTP(S) URL as input and
     * retain only its path. Query strings and fragments are intentionally not
     * part of a path-only source identity. Regex and Wildcard patterns remain
     * byte-for-byte unchanged so pattern syntax is never parsed as a URL.
     *
     * @return array{sourceUrl: string, sourceUrlParsed: string}
     * @since 5.41.0
     */
    public static function normalizeSourceUrl(string $sourceUrl, string $redirectSrcMatch, string $matchType): array
    {
        if (in_array($matchType, ['regex', 'wildcard'], true)) {
            return [
                'sourceUrl' => $sourceUrl,
                'sourceUrlParsed' => $sourceUrl,
            ];
        }

        $sourceUrlParsed = trim(str_replace(["\r", "\n", "\t"], '', $sourceUrl));
        if ($redirectSrcMatch === 'pathonly' && UrlSafetyHelper::isHttpUrlWithHost($sourceUrlParsed)) {
            $path = parse_url($sourceUrlParsed, PHP_URL_PATH);
            $sourceUrl = is_string($path) && $path !== '' ? $path : '/';
            $sourceUrlParsed = $sourceUrl;
        }

        // Normalize repeated slashes in the comparison identity without
        // changing a supported path input's persisted presentation.
        if (preg_match('#^(https?://[^/]+)(.*)$#i', $sourceUrlParsed, $matches)) {
            $sourceUrlParsed = $matches[1] . preg_replace('#/+#', '/', $matches[2]);
        } else {
            $sourceUrlParsed = preg_replace('#/+#', '/', $sourceUrlParsed) ?? $sourceUrlParsed;
        }

        if (in_array($matchType, ['exact', 'prefix'], true)) {
            $sourceUrlParsed = strtolower($sourceUrlParsed);
        }

        return [
            'sourceUrl' => $sourceUrl,
            'sourceUrlParsed' => $sourceUrlParsed,
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        if (is_string($this->sourceUrl)) {
            $normalized = self::normalizeSourceUrl(
                $this->sourceUrl,
                (string)$this->redirectSrcMatch,
                (string)$this->matchType,
            );
            $this->sourceUrl = $normalized['sourceUrl'];
            $this->sourceUrlParsed = $normalized['sourceUrlParsed'];
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert): bool
    {
        $this->siteIdKey = self::siteIdKey($this->siteId ? (int)$this->siteId : null);

        // Equality-matched rows store sourceUrlParsed lowercase so bookkeeping
        // (duplicate checks, unique index, loop detection) behaves
        // case-insensitively on MySQL AND PostgreSQL — MySQL's ci collation did
        // this implicitly; PostgreSQL compares case-sensitively. Pattern rows
        // (regex/wildcard) stay verbatim: lowercasing a pattern corrupts it
        // (\W would become \w, inverting its meaning). Runtime matchers are
        // case-blind for all types (strcasecmp/stripos/PCRE i), so storage
        // casing never affects matching; ASCII strtolower matches strcasecmp's
        // ASCII-only case folding.
        if (in_array($this->matchType, ['exact', 'prefix'], true) && is_string($this->sourceUrlParsed)) {
            $this->sourceUrlParsed = strtolower($this->sourceUrlParsed);
        }

        return parent::beforeSave($insert);
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
     * Whether a destination template has a fixed safe trust class: a relative
     * path (not protocol-relative //host), an HTTP(S) URL with fixed authority,
     * or a recognized contact/application scheme. Captures may refine the safe
     * portion of those templates but cannot supply their trust boundary.
     *
     * Shared by the CP form and CSV import so the two surfaces can't drift.
     *
     * @param string $url
     * @return bool
     * @since 5.33.0
     */
    public static function isValidDestination(string $url): bool
    {
        return MatchingService::isSafeDestinationTemplate($url);
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

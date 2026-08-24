<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use DirectoryIterator;
use lindemannrock\base\cache\DisposableCacheStorageDecision;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\events\RedirectEvent;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use yii\db\IntegrityException;
use yii\web\NotFoundHttpException;

/**
 * Redirects Service
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class RedirectsService extends Component
{
    use LoggingTrait;

    private const CACHE_RESULT_VERSION = 3;
    private const CACHE_STATE_ABSENT = 'absent';
    private const CACHE_STATE_NEGATIVE = 'negative';
    private const CACHE_STATE_POSITIVE = 'positive';
    private const CACHE_MAX_ENTRIES = 1000;

    private const FILE_CACHE_WRITE_MUTEX = 'redirect-manager:redirect-cache-file-write';
    /**
     * Cache key prefix
     */
    public const CACHE_KEY = 'redirectmanager_redirect_';

    /**
     * Cache tag for all redirects
     */
    public const CACHE_TAG = 'redirectmanager_redirects';

    /**
     * Event triggered before a redirect is saved
     */
    public const EVENT_BEFORE_SAVE_REDIRECT = 'beforeSaveRedirect';

    /**
     * Event triggered after a redirect is saved
     */
    public const EVENT_AFTER_SAVE_REDIRECT = 'afterSaveRedirect';

    /**
     * Event triggered before a redirect is deleted
     */
    public const EVENT_BEFORE_DELETE_REDIRECT = 'beforeDeleteRedirect';

    /**
     * Event triggered after a redirect is deleted
     */
    public const EVENT_AFTER_DELETE_REDIRECT = 'afterDeleteRedirect';

    /**
     * @var array Stashed element URIs for tracking changes
     */
    private array $_stashedUris = [];

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

    /**
     * Handle 404 exception by attempting to find and execute a redirect
     *
     * @param NotFoundHttpException $exception
     * @return void
     */
    public function handle404(NotFoundHttpException $exception): void
    {
        $request = Craft::$app->getRequest();

        try {
            $fullUrl = urldecode($request->getAbsoluteUrl());
            $pathOnly = urldecode($request->getUrl());
        } catch (\Exception $e) {
            $this->logError('Error getting URL from request', ['error' => $e->getMessage()]);
            return;
        }

        // Build full path with query string for analytics
        // Check if pathOnly already includes query string
        $queryString = $request->getQueryString();
        if ($queryString && strpos($pathOnly, '?') === false) {
            $originalPath = $pathOnly . '?' . $queryString;
        } else {
            $originalPath = $pathOnly;
        }
        $originalFullUrl = $fullUrl;

        [$fullUrlForMatching, $pathOnlyForMatching] = $this->normalizeMatchingInputs($fullUrl, $pathOnly);

        // Strip site base path for matching (e.g., /en/about-us → /about-us)
        // This allows redirects to be stored without site prefix while matching URLs with prefix
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $siteBaseUrl = $currentSite->getBaseUrl();
        $siteBasePath = parse_url($siteBaseUrl, PHP_URL_PATH) ?: '';
        $siteBasePath = '/' . trim($siteBasePath, '/');

        $pathOnlyStripped = $pathOnlyForMatching;
        if ($siteBasePath !== '/' && str_starts_with($pathOnlyForMatching, $siteBasePath . '/')) {
            $pathOnlyStripped = substr($pathOnlyForMatching, strlen($siteBasePath));
        }

        $this->logDebug('Handling 404', [
            'originalFullUrl' => $fullUrl,
            'pathForMatching' => $pathOnlyForMatching,
            'pathStripped' => $pathOnlyStripped,
            'siteBasePath' => $siteBasePath,
            'userAgent' => $request->getUserAgent(),
        ]);

        // Check if URL should be excluded
        if ($this->isExcluded($pathOnlyForMatching)) {
            $this->logDebug('URL excluded from redirect handling', ['url' => $pathOnlyForMatching]);
            return;
        }

        $redirect = $this->findRedirectForSiteCandidatesInternal(
            $fullUrlForMatching,
            [$pathOnlyStripped, $pathOnlyForMatching],
            (int)$currentSite->id,
        );

        if ($redirect) {
            // Record the hit BEFORE executing redirect (since redirect ends the script)
            RedirectManager::$plugin->analytics->record404($originalPath, true, [
                'redirectId' => $redirect['id'] ?? null,
            ]);
            // Pass original fullUrl to preserve query string
            $this->executeRedirect($redirect, $originalFullUrl, $pathOnlyForMatching);
        }

        // Record unhandled 404 if no redirect was found
        if (!$redirect) {
            RedirectManager::$plugin->analytics->record404($originalPath, false);
        }
    }

    /**
     * Find a redirect for the given URLs
     *
     * @param string $fullUrl
     * @param string $pathOnly
     * @return array|null Returns the first eligible redirect with its resolved destination.
     */
    public function findRedirect(string $fullUrl, string $pathOnly): ?array
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        return $this->findRedirectForSite($fullUrl, $pathOnly, $siteId);
    }

    /**
     * Find a redirect for the given URLs and site ID.
     *
     * This is the site-aware variant used by GraphQL and integrations that
     * need to resolve against an explicit site instead of Craft's current
     * request site. Matching still includes global redirects (`siteId` null).
     *
     * @param string $fullUrl
     * @param string $pathOnly
     * @param int $siteId
     * @return array|null Returns the first eligible redirect with its resolved destination.
     * @since 5.33.0
     */
    public function findRedirectForSite(string $fullUrl, string $pathOnly, int $siteId): ?array
    {
        return $this->findRedirectForSiteCandidates($fullUrl, [$pathOnly], $siteId);
    }

    /**
     * Find one redirect across ordered path-only candidates for a site.
     *
     * The candidate rows are loaded once. Full-URL rules are evaluated once
     * because their matching input is identical across path-only attempts.
     *
     * @param array<int, string> $pathCandidates
     * @return array<string, mixed>|null
     * @since 5.41.0
     */
    public function findRedirectForSiteCandidates(string $fullUrl, array $pathCandidates, int $siteId): ?array
    {
        $queryString = $this->queryStringFromUrl($fullUrl);
        $redirect = $this->findRedirectForSiteCandidatesInternal($fullUrl, $pathCandidates, $siteId);

        return $redirect === null ? null : $this->prepareRedirectForConsumer($redirect, $queryString);
    }

    /**
     * Resolve one accepted candidate while retaining internal response metadata.
     *
     * @param array<int, string> $pathCandidates
     * @return array<string, mixed>|null
     */
    private function findRedirectForSiteCandidatesInternal(string $fullUrl, array $pathCandidates, int $siteId): ?array
    {
        [$fullUrl, $pathCandidates] = $this->normalizeMatchingCandidateInputs($fullUrl, $pathCandidates);
        $pathCandidates = $this->normalizePathCandidates($pathCandidates);
        $lookupIdentity = $this->buildLookupIdentity($siteId, $fullUrl, $pathCandidates);
        $settings = RedirectManager::$plugin->getSettings();
        $storageDecision = $settings->enableRedirectCache
            ? RedirectManager::$plugin->localCache->getStorageDecision()
            : null;
        $cached = $this->getFromCache($lookupIdentity, $siteId, $storageDecision);

        if ($cached['state'] === self::CACHE_STATE_NEGATIVE) {
            return null;
        }

        if ($cached['state'] === self::CACHE_STATE_POSITIVE) {
            $redirect = $cached['redirect'];
            $redirect['_requestSiteId'] = $siteId;
            $this->incrementHitCount((int)$redirect['id']);

            return $redirect;
        }

        $redirect = $this->resolveFirstEligibleCandidateForPaths(
            $this->getEnabledRedirects($siteId),
            $fullUrl,
            $pathCandidates,
            $siteId,
        );
        if ($redirect === null) {
            $this->saveToCache($lookupIdentity, $siteId, $this->negativeCacheResult(), $storageDecision);

            return null;
        }

        $this->saveToCache($lookupIdentity, $siteId, $this->positiveCacheResult($redirect), $storageDecision);
        $redirect['_requestSiteId'] = $siteId;
        $this->incrementHitCount((int)$redirect['id']);

        return $redirect;
    }

    /**
     * Return every eligible matching redirect without cache, hit, or analytics effects.
     *
     * The CP URL tester uses this diagnostic view of the same ordered candidate
     * resolution and trust policy as frontend, GraphQL, and integrations.
     *
     * @param array<int> $siteIds
     * @return array<int, array<string, mixed>>
     * @since 5.41.0
     */
    public function testRedirects(string $fullUrl, string $pathOnly, array $siteIds): array
    {
        $queryString = $this->queryStringFromUrl($fullUrl);
        [$fullUrl, $pathOnly] = $this->normalizeMatchingInputs($fullUrl, $pathOnly);

        $matches = [];
        $redirects = $this->getEnabledRedirects($siteIds);
        foreach ($redirects as $redirect) {
            $resolved = $this->resolveEligibleCandidate($redirect, $fullUrl, $pathOnly, $siteIds, $redirects);
            if ($resolved !== null) {
                $template = (string)$resolved['_destinationTemplate'];
                $prepared = $this->prepareRedirectForConsumer($resolved, $queryString);
                $resolvedDestination = $prepared['destinationUrl'];
                $resolved = $prepared;
                $resolved['destinationUrl'] = $template;
                $resolved['resolvedDestinationUrl'] = $resolvedDestination;
                $matches[] = $resolved;
            }
        }

        return $matches;
    }

    /**
     * Handle 404 from external plugin
     *
     * This method allows other plugins to integrate with Redirect Manager's 404 handling
     * by checking for matching redirects and tracking analytics with source plugin information.
     *
     * @param string $url The 404 URL
     * @param array $context Context data (source plugin, metadata)
     * @return array|null Redirect data if found, null otherwise
     * @since 5.3.0
     */
    public function handleExternal404(string $url, array $context = []): ?array
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $siteId = $currentSite->id;

        $fullUrl = $url;
        $pathOnly = $this->pathAndQueryFromUrl($url);

        // Strip site base path for matching (e.g., /ar/go/slug -> /go/slug)
        $siteBaseUrl = $currentSite->getBaseUrl();
        $siteBasePath = parse_url($siteBaseUrl, PHP_URL_PATH) ?: '';
        $siteBasePath = '/' . trim($siteBasePath, '/');

        $pathOnlyStripped = $pathOnly;
        if ($siteBasePath !== '/' && str_starts_with($pathOnly, $siteBasePath . '/')) {
            $pathOnlyStripped = substr($pathOnly, strlen($siteBasePath));
        }

        $this->logDebug('Handling external 404', [
            'url' => $url,
            'pathOnly' => $pathOnly,
            'pathOnlyStripped' => $pathOnlyStripped,
            'siteBasePath' => $siteBasePath,
            'source' => $context['source'] ?? 'unknown',
            'context' => $context,
        ]);

        $redirect = $this->findRedirectForSiteCandidates(
            $fullUrl,
            [$pathOnlyStripped, $pathOnly],
            (int)$siteId,
        );

        // Record 404 with source tracking
        $analyticsContext = $context;
        if ($redirect) {
            $analyticsContext['redirectId'] = $redirect['id'] ?? null;
        }
        RedirectManager::$plugin->analytics->record404(
            $pathOnly,
            (bool)$redirect,
            $analyticsContext
        );

        if ($redirect) {
            // If we stripped site base path and destination is a relative path, add it back
            if (
                (int)$redirect['statusCode'] !== 410
                && $siteBasePath !== '/'
                && $pathOnlyStripped !== $pathOnly
            ) {
                $destUrl = $redirect['destinationUrl'];
                // Only prepend if destination is a relative path starting with /
                if ($destUrl && $destUrl[0] === '/' && !str_starts_with($destUrl, $siteBasePath . '/')) {
                    $redirect['destinationUrl'] = $siteBasePath . $destUrl;
                }
            }

            $this->logDebug('External 404 matched redirect', [
                'source' => $context['source'] ?? 'unknown',
                'url' => $pathOnly,
                'destination' => $redirect['destinationUrl'],
                'siteBasePath' => $siteBasePath,
            ]);
        }

        return $redirect;
    }

    /**
     * Resolve a matching redirect only when its substituted destination is safe.
     *
     * @param array<string, mixed> $redirect
     * @param string $fullUrl
     * @param string $pathOnly
     * @param array<int, array<string, mixed>>|null $chainCandidates
     * @return array<string, mixed>|null
     */
    private function resolveEligibleCandidate(
        array $redirect,
        string $fullUrl,
        string $pathOnly,
        int|array|null $siteId,
        ?array $chainCandidates = null,
    ): ?array {
        $resolved = $this->resolveMatchingCandidate($redirect, $fullUrl, $pathOnly);
        if ($resolved === null) {
            return null;
        }

        if ((int)$resolved['statusCode'] === 410) {
            return $resolved;
        }

        $chainSiteId = is_array($siteId)
            ? (isset($redirect['siteId']) && is_numeric($redirect['siteId'])
                ? (int)$redirect['siteId']
                : (isset($siteId[0]) ? (int)$siteId[0] : null))
            : $siteId;
        $chain = $this->resolveRedirectChainOutcome(
            (string)$resolved['destinationUrl'],
            $chainSiteId,
            candidates: $chainCandidates,
        );
        if (!$chain['safe']) {
            $this->logWarning('Skipped redirect with unsafe chain', [
                'redirectId' => $redirect['id'] ?? null,
                'sourceUrl' => $redirect['sourceUrl'] ?? null,
                'destinationUrl' => $resolved['destinationUrl'],
                'reason' => $chain['reason'],
            ]);

            return null;
        }

        $resolved['_chainDestinationUrl'] = $chain['destination'];

        return $resolved;
    }

    /**
     * Match a candidate and resolve only its own capture-aware destination.
     *
     * @param array<string, mixed> $redirect
     * @return array<string, mixed>|null
     */
    private function resolveMatchingCandidate(array $redirect, string $fullUrl, string $pathOnly): ?array
    {
        $matchType = $redirect['matchType'];
        $sourceUrlParsed = $redirect['sourceUrlParsed'];
        $redirectSrcMatch = $redirect['redirectSrcMatch'] ?? 'pathonly';

        // Use pathOnly or fullUrl based on per-redirect setting
        $urlToMatch = $redirectSrcMatch === 'fullurl' ? $fullUrl : $pathOnly;

        // Use matchWithCaptures to get both match result and captured groups
        $result = RedirectManager::$plugin->matching->matchWithCaptures($matchType, $sourceUrlParsed, $urlToMatch);

        if (!$result['matched']) {
            return null;
        }

        $template = (string)$redirect['destinationUrl'];
        if ((int)$redirect['statusCode'] === 410) {
            $redirect['_destinationTemplate'] = $template;
            $redirect['_destinationPolicyVersion'] = 2;
            $redirect['_responseType'] = 'gone';

            return $redirect;
        }

        $resolved = RedirectManager::$plugin->matching->resolveDestination($template, $result['captures']);
        if ($resolved === null) {
            $this->logWarning('Skipped redirect with unsafe resolved destination', [
                'redirectId' => $redirect['id'] ?? null,
                'sourceUrl' => $redirect['sourceUrl'] ?? null,
                'destinationTemplate' => $template,
            ]);
            return null;
        }

        $redirect['destinationUrl'] = $resolved;
        $redirect['_destinationTemplate'] = $template;
        $redirect['_destinationPolicyVersion'] = 2;
        $redirect['_responseType'] = 'redirect';

        return $redirect;
    }

    /**
     * @param array<int, array<string, mixed>> $redirects
     * @param array<int, string> $pathCandidates
     * @return array<string, mixed>|null
     */
    private function resolveFirstEligibleCandidateForPaths(
        array $redirects,
        string $fullUrl,
        array $pathCandidates,
        int $siteId,
    ): ?array {
        foreach ($redirects as $redirect) {
            foreach ($pathCandidates as $pathOnly) {
                $resolved = $this->resolveEligibleCandidate(
                    $redirect,
                    $fullUrl,
                    $pathOnly,
                    $siteId,
                    $redirects,
                );
                if ($resolved !== null) {
                    return $resolved;
                }

                if (($redirect['redirectSrcMatch'] ?? 'pathonly') === 'fullurl') {
                    break;
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $redirect */
    private function withoutDestinationPolicyMetadata(array $redirect): array
    {
        unset(
            $redirect['_destinationTemplate'],
            $redirect['_destinationPolicyVersion'],
            $redirect['_responseType'],
            $redirect['_chainDestinationUrl'],
        );

        return $redirect;
    }

    /**
     * Apply request-specific destination settings without changing cached data.
     *
     * @param array<string, mixed> $redirect
     * @return array<string, mixed>
     */
    private function prepareRedirectForConsumer(array $redirect, ?string $queryString): array
    {
        if (
            (int)$redirect['statusCode'] !== 410
            && RedirectManager::$plugin->getSettings()->preserveQueryString
            && $queryString !== null
        ) {
            $redirect['destinationUrl'] = $this->appendQueryString(
                (string)$redirect['destinationUrl'],
                $queryString,
            );
        }

        return $this->withoutDestinationPolicyMetadata($redirect);
    }

    /**
     * Execute a redirect
     *
     * @param array $redirect
     * @param string $fullUrl
     * @param string $pathOnly
     * @return void
     */
    private function executeRedirect(array $redirect, string $fullUrl, string $pathOnly): void
    {
        $response = $this->prepareRedirectResponse($redirect, $fullUrl, $pathOnly);

        $response->send();
        Craft::$app->end();
    }

    /**
     * Prepare the accepted HTTP response without sending it.
     *
     * @param array<string, mixed> $redirect
     */
    private function prepareRedirectResponse(array $redirect, string $fullUrl, string $pathOnly): \yii\web\Response
    {
        $statusCode = (int)$redirect['statusCode'];
        $settings = RedirectManager::$plugin->getSettings();
        $response = Craft::$app->getResponse();

        if ($statusCode === 410) {
            $destination = null;
            $response->setStatusCode(410);
            $response->headers->remove('Location');
        } else {
            $destination = (string)($redirect['_chainDestinationUrl'] ?? $redirect['destinationUrl']);
            if ($settings->preserveQueryString) {
                $queryString = $this->queryStringFromUrl($fullUrl);
                if ($queryString !== null) {
                    $destination = $this->appendQueryString($destination, $queryString);
                }
            }

            if (!UrlHelper::isAbsoluteUrl($destination)) {
                $destination = UrlHelper::siteUrl($destination);
            }

            $response->redirect($destination, $statusCode);
        }

        $this->logDebug('Executing redirect', [
            'from' => $pathOnly,
            'to' => $destination,
            'statusCode' => $statusCode,
        ]);

        // Set no-cache headers if configured
        if ($settings->setNoCacheHeaders) {
            $response->setNoCacheHeaders();
        }

        // Add custom headers
        foreach ($settings->additionalHeaders as $header) {
            if (isset($header['name']) && isset($header['value'])) {
                $response->headers->set($header['name'], $header['value']);
            }
        }

        if ($statusCode === 410) {
            $response->headers->remove('Location');
        }

        return $response;
    }

    /**
     * Stash element URI before it's changed
     *
     * @param ElementInterface $element
     * @return void
     */
    public function stashElementUri(ElementInterface $element): void
    {
        if (!$element->id || $element->getIsRevision() || $element->getIsDraft()) {
            return;
        }

        // Get the OLD URI from the database (not from the element)
        $oldElement = Craft::$app->getElements()->getElementById(
            $element->id,
            get_class($element),
            $element->siteId
        );

        if ($oldElement && $oldElement->uri && $oldElement->getUrl()) {
            $this->_stashedUris[$element->id . '_' . $element->siteId] = [
                'uri' => $oldElement->uri,
                'siteId' => $element->siteId,
            ];

            $this->logDebug('Stashed element URI from database', [
                'elementId' => $element->id,
                'elementType' => get_class($element),
                'oldUri' => $oldElement->uri,
                'currentUri' => $element->uri,
                'siteId' => $element->siteId,
            ]);
        }
    }

    /**
     * Handle element URI change by creating a redirect if needed
     *
     * @param ElementInterface $element
     * @return void
     */
    public function handleElementUriChange(ElementInterface $element): void
    {
        if (!$element->id || $element->getIsRevision() || $element->getIsDraft()) {
            return;
        }

        $key = $element->id . '_' . $element->siteId;

        if (!isset($this->_stashedUris[$key])) {
            $this->logDebug('No stashed URI found for element', [
                'elementId' => $element->id,
                'siteId' => $element->siteId,
                'elementType' => get_class($element),
                'stashedKeys' => array_keys($this->_stashedUris),
            ]);
            return;
        }

        $oldUri = $this->_stashedUris[$key]['uri'];
        $newUri = $element->uri;
        $siteId = $element->siteId;

        $this->logDebug('Checking URI change', [
            'elementId' => $element->id,
            'siteId' => $siteId,
            'oldUri' => $oldUri,
            'newUri' => $newUri,
            'changed' => $oldUri !== $newUri,
        ]);

        // Only create redirect if URI actually changed
        if ($oldUri !== $newUri && $newUri) {
            $oldUrl = '/' . ltrim($oldUri, '/');
            $newUrl = '/' . ltrim($newUri, '/');

            // Get most recent redirect for this element
            $mostRecentRedirect = (new Query())
                ->from(RedirectRecord::tableName())
                ->where(['elementId' => $element->id])
                ->andWhere(['siteId' => $siteId])
                ->andWhere(['creationType' => 'entry-change'])
                ->orderBy(['dateCreated' => SORT_DESC])
                ->one();

            $this->logDebug('Looking for recent redirect (Site ID: ' . $siteId . ')', [
                'elementId' => $element->id,
                'found' => !empty($mostRecentRedirect),
                'mostRecent' => $mostRecentRedirect ? ($mostRecentRedirect['sourceUrl'] . ' → ' . $mostRecentRedirect['destinationUrl']) : 'none',
                'dateCreated' => $mostRecentRedirect['dateCreated'] ?? null,
            ]);

            // SCENARIO 1: Detect IMMEDIATE UNDO (flip-flop) - Use centralized method
            if ($this->handleUndoRedirect($oldUrl, $newUrl, $siteId, 'entry-change', 'redirect-manager')) {
                // Undo was handled, clear stashed URI and exit
                unset($this->_stashedUris[$key]);
                return;
            }

            // SCENARIO 2: Detect GOING BACKWARDS (returning to old URL in chain)
            // If new URL already exists as a source, we're going back
            $goingBackwards = (new Query())
                ->from(RedirectRecord::tableName())
                ->where(['sourceUrlParsed' => strtolower($newUrl)])
                ->andWhere(['elementId' => $element->id])
                ->andWhere(['siteId' => $siteId])
                ->andWhere(['creationType' => 'entry-change'])
                ->exists();

            if ($goingBackwards) {
                // Going back to a previous URL - delete entire chain for this element
                $conflictingRedirects = (new Query())
                    ->from(RedirectRecord::tableName())
                    ->where(['elementId' => $element->id])
                    ->andWhere(['siteId' => $siteId])
                    ->andWhere(['creationType' => 'entry-change'])
                    ->all();

                foreach ($conflictingRedirects as $redirect) {
                    $this->deleteRedirect($redirect['id']);
                    $this->logInfo('Deleted old auto-redirect for element (Site ID: ' . $siteId . ')', [
                        'id' => $redirect['id'],
                        'elementId' => $element->id,
                        'from' => $redirect['sourceUrl'],
                        'to' => $redirect['destinationUrl'],
                        'reason' => 'Entry returned to previous URL in chain - cleaning up',
                    ]);
                }

                Craft::$app->getSession()->setNotice(
                    Craft::t('redirect-manager', '{count, number} {count, plural, =1{outdated automatic redirect removed} other{outdated automatic redirects removed}} because the entry returned to a previous URL.', [
                        'count' => count($conflictingRedirects),
                    ])
                );
            }

            // SCENARIO 3: FORWARD PROGRESSION
            // Just keep existing redirects and add new one (default behavior)

            // FINALLY: Check if this would create a circular redirect (after cleanup)
            if ($this->wouldCreateLoop($oldUrl, $newUrl, null, $siteId)) {
                $this->logError('Cannot create redirect: would create circular loop', [
                    'elementId' => $element->id,
                    'oldUri' => $oldUri,
                    'newUri' => $newUri,
                ]);

                // Show error message in CP
                Craft::$app->getSession()->setError(
                    Craft::t('redirect-manager', 'Entry saved, but automatic redirect was not created because it would create a circular redirect loop. Please create a different redirect manually or change the slug.')
                );

                // Clear stashed URI and exit
                unset($this->_stashedUris[$key]);
                return;
            }

            $result = $this->createRedirect([
                'sourceUrl' => $oldUrl,
                'sourceUrlParsed' => $oldUrl,
                'destinationUrl' => $newUrl,
                'matchType' => 'exact',
                'redirectSrcMatch' => RedirectManager::$plugin->getSettings()->redirectSrcMatch,
                'statusCode' => 301,
                'siteId' => $siteId,
                'enabled' => true,
                'priority' => 0,
                'creationType' => 'entry-change',
                'sourcePlugin' => 'redirect-manager',
                'elementId' => $element->id,
            ], true); // Show notification

            if ($result) {
                $this->logInfo('Auto-created redirect for entry URI change', [
                    'elementId' => $element->id,
                    'siteId' => $siteId,
                    'from' => $oldUri,
                    'to' => $newUri,
                ]);
            }
        } else {
            $this->logDebug('URI did not change, skipping redirect creation', [
                'elementId' => $element->id,
                'uri' => $newUri,
            ]);
        }

        // Clear stashed URI
        unset($this->_stashedUris[$key]);
    }

    /**
     * Handle undo redirect - detects and removes flip-flop redirects within undo window
     *
     * @param string $oldUrl The previous URL
     * @param string $newUrl The new URL
     * @param int $siteId Site ID
     * @param string $creationType Creation type (e.g., 'entry-change', 'shortlink-slug-change')
     * @param string $sourcePlugin Source plugin (e.g., 'redirect-manager', 'shortlink-manager')
     * @return bool True if undo was detected and handled, false otherwise
     * @since 5.3.0
     */
    public function handleUndoRedirect(
        string $oldUrl,
        string $newUrl,
        ?int $siteId,
        string $creationType,
        string $sourcePlugin,
    ): bool {
        // Get most recent reverse redirect (new → old)
        $mostRecentRedirect = (new Query())
            ->from(RedirectRecord::tableName())
            ->where(['sourceUrl' => $newUrl])
            ->andWhere(['destinationUrl' => $oldUrl])
            ->andWhere(['siteId' => $siteId]) // null means all sites
            ->andWhere(['creationType' => $creationType])
            ->andWhere(['sourcePlugin' => $sourcePlugin])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->one();

        if ($mostRecentRedirect) {
            // Get undo window from settings
            $settings = RedirectManager::$plugin->getSettings();
            $undoWindowMinutes = $settings->undoWindowMinutes ?? 60;

            // Check if redirect was created within undo window
            $createdTime = new \DateTime($mostRecentRedirect['dateCreated'], new \DateTimeZone('UTC'));
            $createdTime->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
            $now = new \DateTime('now', new \DateTimeZone(Craft::$app->getTimeZone()));
            $minutesAgo = ($now->getTimestamp() - $createdTime->getTimestamp()) / 60;

            // DEBUG
            $this->logDebug('Undo check: Found reverse redirect', [
                'minutesAgo' => round($minutesAgo, 2),
                'undoWindow' => $undoWindowMinutes === 0 ? 'disabled' : $undoWindowMinutes,
                'allowUndo' => true,
            ]);

            // If undo window is 0 (disabled), always allow undo regardless of time
            // Otherwise check if within the time window
            if ($undoWindowMinutes === 0 || $minutesAgo < $undoWindowMinutes) {
                // Immediate undo detected - delete the reverse redirect
                $this->deleteRedirect($mostRecentRedirect['id']);

                $this->logInfo('Immediate undo detected - deleted reverse redirect', [
                    'deletedRedirect' => $newUrl . ' → ' . $oldUrl,
                    'minutesAgo' => round($minutesAgo, 2),
                    'siteId' => $siteId,
                    'creationType' => $creationType,
                    'sourcePlugin' => $sourcePlugin,
                ]);

                Craft::$app->getSession()->setNotice(
                    Craft::t('redirect-manager', 'Slug change undone - previous redirect removed.')
                );

                return true; // Undo was handled
            }
        }

        return false; // No undo detected
    }

    /**
     * Create a new redirect
     *
     * @param array $attributes
     * @param bool $showNotification Whether to show user notification
     * @return int|false The new redirect ID on success, false on failure
     */
    public function createRedirect(array $attributes, bool $showNotification = false): int|false
    {
        // Validate required fields FIRST
        $hasErrors = false;

        if (empty($attributes['sourceUrl']) || trim($attributes['sourceUrl']) === '') {
            $hasErrors = true;
        }

        if (empty($attributes['destinationUrl']) || trim($attributes['destinationUrl']) === '') {
            $hasErrors = true;
        }

        // Return early if validation failed (record will be created in controller with errors)
        if ($hasErrors) {
            return false;
        }

        // Parse source URL
        if (!isset($attributes['sourceUrlParsed'])) {
            $attributes['sourceUrlParsed'] = $this->parseUrl($attributes['sourceUrl']);
        }

        // Set default sourcePlugin if not provided
        if (!isset($attributes['sourcePlugin'])) {
            $attributes['sourcePlugin'] = 'redirect-manager';
        }

        $siteId = isset($attributes['siteId']) ? (int)$attributes['siteId'] : null;

        // Check for circular redirects
        if ($this->wouldCreateLoop($attributes['sourceUrl'], $attributes['destinationUrl'], null, $siteId)) {
            $this->logError('Cannot create redirect: would create circular loop', [
                'sourceUrl' => $attributes['sourceUrl'],
                'destinationUrl' => $attributes['destinationUrl'],
            ]);

            // Show specific error message to user
            Craft::$app->getSession()->setError(
                Craft::t('redirect-manager', 'Cannot create redirect: This would create a circular redirect loop. The destination eventually redirects back to the source.')
            );

            return false;
        }

        // Trigger before save event
        $event = new RedirectEvent(['redirect' => $attributes]);
        $this->trigger(self::EVENT_BEFORE_SAVE_REDIRECT, $event);

        if (!$event->isValid) {
            return false;
        }

        // Check for duplicate. Stored exact/prefix parsed URLs are lowercase
        // (RedirectRecord::beforeSave), so lowercase the probe for those types;
        // pattern rows (regex/wildcard) compare verbatim — engine-native.
        $duplicateProbe = in_array($attributes['matchType'] ?? 'exact', ['exact', 'prefix'], true)
            ? strtolower($attributes['sourceUrlParsed'])
            : $attributes['sourceUrlParsed'];
        $existing = (new Query())
            ->from(RedirectRecord::tableName())
            ->where(['sourceUrlParsed' => $duplicateProbe])
            ->andWhere(['siteId' => $attributes['siteId'] ?? null])
            ->one();

        $this->logDebug('Duplicate check', [
            'sourceUrlParsed' => $attributes['sourceUrlParsed'],
            'siteId' => $attributes['siteId'] ?? null,
            'existing' => $existing ? ['id' => $existing['id']] : false,
        ]);

        if ($existing) {
            $this->logWarning('Redirect already exists', ['sourceUrl' => $attributes['sourceUrl']]);

            // Show notification to user
            Craft::$app->getSession()->setNotice(
                Craft::t('redirect-manager', 'Redirect already exists: {source} → {dest}', [
                    'source' => $attributes['sourceUrl'],
                    'dest' => $attributes['destinationUrl'],
                ])
            );

            return false;
        }

        // Create record
        $record = new RedirectRecord();
        $record->setAttributes($attributes, false);
        $record->hitCount = 0;

        try {
            if (!$record->save()) {
                $this->logError('Failed to save redirect', ['errors' => $record->getErrors()]);
                return false;
            }
        } catch (IntegrityException $e) {
            if ($this->handleDuplicateRedirectIntegrityException($e, $attributes['sourceUrl'], $attributes['destinationUrl'])) {
                return false;
            }

            $this->logError('Failed to save redirect', ['error' => $e->getMessage()]);
            return false;
        }

        // Cache invalidation is best-effort after the redirect has committed.
        RedirectManager::$plugin->localCache->invalidateRedirectMutation(
            $record->siteId === null ? null : (int)$record->siteId,
            $record->siteId === null ? null : (int)$record->siteId,
        );

        // Trigger after save event
        $this->trigger(self::EVENT_AFTER_SAVE_REDIRECT, $event);

        $this->logInfo('Redirect created', ['id' => $record->id, 'sourceUrl' => $attributes['sourceUrl']]);

        // Show notification if requested
        if ($showNotification) {
            Craft::$app->getSession()->setNotice(
                Craft::t('redirect-manager', 'Redirect created: {source} → {dest}', [
                    'source' => $attributes['sourceUrl'],
                    'dest' => $attributes['destinationUrl'],
                ])
            );
        }

        return $record->id;
    }

    /**
     * Update an existing redirect
     *
     * @param int $id
     * @param array $attributes
     * @param RedirectRecord|null $record Already-loaded redirect record
     * @return bool
     */
    public function updateRedirect(int $id, array $attributes, ?RedirectRecord $record = null): bool
    {
        $record ??= RedirectRecord::findOne($id);

        if (!$record) {
            $this->logError('Redirect not found', ['id' => $id]);
            return false;
        }

        $oldSiteId = $record->siteId === null ? null : (int)$record->siteId;

        // Parse source URL if changed
        if (isset($attributes['sourceUrl']) && !isset($attributes['sourceUrlParsed'])) {
            $attributes['sourceUrlParsed'] = $this->parseUrl($attributes['sourceUrl']);
        }

        // Check for circular redirects (if destination is being changed)
        if (isset($attributes['destinationUrl'])) {
            $sourceUrl = $attributes['sourceUrl'] ?? $record->sourceUrl;
            $destinationUrl = $attributes['destinationUrl'];
            $siteId = isset($attributes['siteId']) ? (int)$attributes['siteId'] : ($record->siteId ? (int)$record->siteId : null);

            if ($this->wouldCreateLoop($sourceUrl, $destinationUrl, $id, $siteId)) {
                $this->logError('Cannot update redirect: would create circular loop', [
                    'id' => $id,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                ]);

                // Show specific error message to user
                Craft::$app->getSession()->setError(
                    Craft::t('redirect-manager', 'Cannot update redirect: This would create a circular redirect loop. The destination eventually redirects back to the source.')
                );

                return false;
            }
        }

        // Trigger before save event
        $event = new RedirectEvent(['redirect' => array_merge($record->toArray(), $attributes)]);
        $this->trigger(self::EVENT_BEFORE_SAVE_REDIRECT, $event);

        if (!$event->isValid) {
            return false;
        }

        $record->setAttributes($attributes, false);

        try {
            if (!$record->save()) {
                $this->logError('Failed to update redirect', ['id' => $id, 'errors' => $record->getErrors()]);
                return false;
            }
        } catch (IntegrityException $e) {
            if ($this->handleDuplicateRedirectIntegrityException($e, (string)$record->sourceUrl, (string)$record->destinationUrl)) {
                return false;
            }

            $this->logError('Failed to update redirect', ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        }

        // Cache invalidation is best-effort after the redirect has committed.
        RedirectManager::$plugin->localCache->invalidateRedirectMutation(
            $oldSiteId,
            $record->siteId === null ? null : (int)$record->siteId,
        );

        // Trigger after save event
        $this->trigger(self::EVENT_AFTER_SAVE_REDIRECT, $event);

        $this->logInfo('Redirect updated', ['id' => $id]);

        return true;
    }

    /**
     * Delete a redirect
     *
     * @param int $id
     * @param RedirectRecord|null $record Already-loaded redirect record
     * @return bool
     */
    public function deleteRedirect(int $id, ?RedirectRecord $record = null): bool
    {
        $record ??= RedirectRecord::findOne($id);

        if (!$record) {
            $this->logError('Redirect not found', ['id' => $id]);
            return false;
        }

        $siteId = $record->siteId === null ? null : (int)$record->siteId;

        // Trigger before delete event
        $event = new RedirectEvent(['redirect' => $record->toArray()]);
        $this->trigger(self::EVENT_BEFORE_DELETE_REDIRECT, $event);

        if (!$event->isValid) {
            return false;
        }

        if (!$record->delete()) {
            $this->logError('Failed to delete redirect', ['id' => $id]);
            return false;
        }

        // Cache invalidation is best-effort after the redirect has committed.
        RedirectManager::$plugin->localCache->invalidateRedirectMutation($siteId, $siteId);

        // Trigger after delete event
        $this->trigger(self::EVENT_AFTER_DELETE_REDIRECT, $event);

        $this->logInfo('Redirect deleted', ['id' => $id]);

        return true;
    }

    /**
     * Get all enabled redirects using site rank, priority, then ID.
     *
     * @param int|array<int>|null $siteId
     * @return array
     */
    public function getEnabledRedirects(int|array|null $siteId = null): array
    {
        $query = (new Query())
            ->from(RedirectRecord::tableName())
            ->where(['enabled' => true])
            ->orderBy(['priority' => SORT_ASC, 'id' => SORT_ASC]);

        if ($siteId !== null) {
            $query->andWhere([
                'or',
                ['siteId' => $siteId],
                ['siteId' => null],
            ]);
        }

        $redirects = $query->all();
        if ($siteId === null) {
            return $redirects;
        }

        $siteIds = is_array($siteId) ? array_map('intval', $siteId) : [$siteId];
        usort($redirects, static function(array $a, array $b) use ($siteIds): int {
            $aSiteRank = isset($a['siteId']) && in_array((int)$a['siteId'], $siteIds, true) ? 0 : 1;
            $bSiteRank = isset($b['siteId']) && in_array((int)$b['siteId'], $siteIds, true) ? 0 : 1;

            return $aSiteRank <=> $bSiteRank
                ?: (int)$a['priority'] <=> (int)$b['priority']
                ?: (int)$a['id'] <=> (int)$b['id'];
        });

        return $redirects;
    }

    /**
     * Get cache directory path
     *
     * @return string
     */
    private function getCachePath(): string
    {
        return PluginHelper::getCachePath(RedirectManager::$plugin, 'redirects');
    }

    /**
     * @param array<int, string> $pathCandidates
     * @return array<int, string>
     */
    private function normalizePathCandidates(array $pathCandidates): array
    {
        $normalized = [];
        foreach ($pathCandidates as $pathCandidate) {
            if (!in_array($pathCandidate, $normalized, true)) {
                $normalized[] = $pathCandidate;
            }
        }

        return $normalized;
    }

    /** @return array{0: string, 1: string} */
    private function normalizeMatchingInputs(string $fullUrl, string $pathOnly): array
    {
        if (!RedirectManager::$plugin->getSettings()->stripQueryString) {
            return [$fullUrl, $pathOnly];
        }

        return [$this->stripQueryString($fullUrl), $this->stripQueryString($pathOnly)];
    }

    /**
     * @param array<int, string> $pathCandidates
     * @return array{0: string, 1: array<int, string>}
     */
    private function normalizeMatchingCandidateInputs(string $fullUrl, array $pathCandidates): array
    {
        if (!RedirectManager::$plugin->getSettings()->stripQueryString) {
            return [$fullUrl, $pathCandidates];
        }

        return [
            $this->stripQueryString($fullUrl),
            array_map($this->stripQueryString(...), $pathCandidates),
        ];
    }

    /** @param array<int, string> $pathCandidates */
    private function buildLookupIdentity(int $siteId, string $fullUrl, array $pathCandidates): string
    {
        return hash('sha256', serialize([
            'version' => self::CACHE_RESULT_VERSION,
            'siteId' => $siteId,
            'fullUrl' => $fullUrl,
            'pathCandidates' => $pathCandidates,
        ]));
    }

    /** @return array{state: 'absent'} */
    private function absentCacheResult(): array
    {
        return ['state' => self::CACHE_STATE_ABSENT];
    }

    /** @return array{state: 'negative'} */
    private function negativeCacheResult(): array
    {
        return ['state' => self::CACHE_STATE_NEGATIVE];
    }

    /**
     * @param array<string, mixed> $redirect
     * @return array{state: 'positive', redirect: array<string, mixed>}
     */
    private function positiveCacheResult(array $redirect): array
    {
        return ['state' => self::CACHE_STATE_POSITIVE, 'redirect' => $redirect];
    }

    /**
     * @return array{state: 'absent'|'negative'|'positive', redirect?: array<string, mixed>}
     */
    private function getFromCache(
        string $lookupIdentity,
        int $siteId,
        ?DisposableCacheStorageDecision $storageDecision,
    ): array {
        if ($storageDecision === null || $storageDecision->isDisabled()) {
            return $this->absentCacheResult();
        }

        if ($storageDecision->usesApplicationCache()) {
            $cache = RedirectManager::$plugin->localCache->getScopedCache(
                $storageDecision,
                LocalCacheService::FAMILY_REDIRECT_LOOKUPS,
            );
            if ($cache === null) {
                return $this->absentCacheResult();
            }

            $result = $cache->get($lookupIdentity, ['siteId' => $siteId]);
            if ($result->isFailure()) {
                $this->logWarning('Redirect result cache read failed; resolving uncached', [
                    'backend' => 'application',
                ]);
                return $this->absentCacheResult();
            }
            if ($result->isMiss()) {
                return $this->absentCacheResult();
            }

            $decoded = $this->decodeCachedResult($result->value);
            if ($decoded !== null) {
                $this->logDebug('Redirect result cache hit (Application)', ['identity' => $lookupIdentity]);

                return $decoded;
            }

            if (!$cache->delete($lookupIdentity, ['siteId' => $siteId])) {
                $this->logWarning('Invalid redirect result cache entry could not be removed', [
                    'backend' => 'application',
                ]);
            }

            return $this->absentCacheResult();
        }

        $filepath = $this->cacheFilepath($lookupIdentity);
        if (!is_file($filepath)) {
            return $this->absentCacheResult();
        }

        try {
            $data = file_get_contents($filepath);
            $stored = is_string($data) ? json_decode($data, true, flags: JSON_THROW_ON_ERROR) : null;
            if (!is_array($stored) || !is_numeric($stored['expires'] ?? null) || (int)$stored['expires'] <= time()) {
                @unlink($filepath);

                return $this->absentCacheResult();
            }

            $decoded = $this->decodeCachedResult($stored['result'] ?? null);
            if ($decoded !== null) {
                $this->logDebug('Redirect result cache hit (File)', ['identity' => $lookupIdentity]);

                return $decoded;
            }
        } catch (\Throwable $exception) {
            $this->logWarning('Redirect result cache read failed; resolving uncached', [
                'backend' => 'file',
                'error' => $exception->getMessage(),
            ]);
        }

        @unlink($filepath);

        return $this->absentCacheResult();
    }

    private function cacheFilepath(string $lookupIdentity): string
    {
        return $this->getCachePath() . $lookupIdentity . '.cache';
    }

    /**
     * @return array{state: 'negative'|'positive', redirect?: array<string, mixed>}|null
     */
    private function decodeCachedResult(mixed $cached): ?array
    {
        if (!is_array($cached) || ($cached['version'] ?? null) !== self::CACHE_RESULT_VERSION) {
            return null;
        }

        if (($cached['state'] ?? null) === self::CACHE_STATE_NEGATIVE) {
            return $this->negativeCacheResult();
        }

        if (($cached['state'] ?? null) !== self::CACHE_STATE_POSITIVE) {
            return null;
        }

        $redirect = $this->validatedCachedRedirect($cached['redirect'] ?? null);

        return $redirect === null ? null : $this->positiveCacheResult($redirect);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validatedCachedRedirect(mixed $cached): ?array
    {
        if (
            !is_array($cached)
            || ($cached['_destinationPolicyVersion'] ?? null) !== 2
            || !is_string($cached['_destinationTemplate'] ?? null)
        ) {
            return null;
        }

        if (($cached['_responseType'] ?? null) === 'gone' && (int)($cached['statusCode'] ?? 0) === 410) {
            return $cached;
        }

        if (
            ($cached['_responseType'] ?? null) !== 'redirect'
            || !is_string($cached['destinationUrl'] ?? null)
            || !is_string($cached['_chainDestinationUrl'] ?? null)
            || !RedirectRecord::isValidDestination($cached['_chainDestinationUrl'])
            || !MatchingService::isResolvedDestinationSafe(
                $cached['_destinationTemplate'],
                $cached['destinationUrl'],
            )
        ) {
            return null;
        }

        return $cached;
    }

    /**
     * @param array{state: 'negative'|'positive', redirect?: array<string, mixed>} $result
     */
    private function saveToCache(
        string $lookupIdentity,
        int $siteId,
        array $result,
        ?DisposableCacheStorageDecision $storageDecision,
    ): void {
        if ($storageDecision === null || $storageDecision->isDisabled()) {
            return;
        }

        $settings = RedirectManager::$plugin->getSettings();
        $duration = max(1, (int)($settings->redirectCacheDuration ?? 3600));
        $storedResult = ['version' => self::CACHE_RESULT_VERSION] + $result;

        if ($storageDecision->usesApplicationCache()) {
            $cache = RedirectManager::$plugin->localCache->getScopedCache(
                $storageDecision,
                LocalCacheService::FAMILY_REDIRECT_LOOKUPS,
            );
            if ($cache === null) {
                return;
            }

            if (!$cache->set($lookupIdentity, $storedResult, $duration, ['siteId' => $siteId])) {
                $this->logWarning('Redirect result cache write failed; continuing uncached', [
                    'backend' => 'application',
                ]);
                return;
            }
            $this->logDebug('Redirect result cached (Application)', [
                'identity' => $lookupIdentity,
                'state' => $result['state'],
                'duration' => $duration,
            ]);

            return;
        }

        $cachePath = $this->getCachePath();
        $filepath = $this->cacheFilepath($lookupIdentity);
        $mutex = Craft::$app->getMutex();
        $mutexAcquired = false;
        try {
            FileHelper::createDirectory($cachePath);
            $mutexAcquired = $mutex->acquire(self::FILE_CACHE_WRITE_MUTEX, 3);
            if (!$mutexAcquired) {
                throw new \RuntimeException('Unable to acquire the redirect result cache write lock.');
            }
            $this->pruneFileCacheForWrite($cachePath);
            $payload = json_encode([
                'result' => $storedResult,
                'expires' => time() + $duration,
            ], JSON_THROW_ON_ERROR);
            if (file_put_contents($filepath, $payload, LOCK_EX) === false) {
                throw new \RuntimeException('Unable to write redirect result cache file.');
            }
            $this->logDebug('Redirect result cached (File)', [
                'identity' => $lookupIdentity,
                'state' => $result['state'],
                'duration' => $duration,
            ]);
        } catch (\Throwable $exception) {
            $this->logWarning('Redirect result cache write failed; continuing uncached', [
                'backend' => 'file',
                'error' => $exception->getMessage(),
            ]);
        } finally {
            if ($mutexAcquired) {
                try {
                    $mutex->release(self::FILE_CACHE_WRITE_MUTEX);
                } catch (\Throwable $exception) {
                    $this->logWarning('Redirect result cache lock release failed', [
                        'backend' => 'file',
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }
    }

    private function pruneFileCacheForWrite(string $cachePath): void
    {
        $files = [];
        foreach (new DirectoryIterator($cachePath) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.cache')) {
                $files[] = $file->getPathname();
            }
        }

        if (count($files) < self::CACHE_MAX_ENTRIES) {
            return;
        }

        $now = time();
        foreach ($files as $index => $filepath) {
            try {
                $data = file_get_contents($filepath);
                $stored = is_string($data) ? json_decode($data, true, flags: JSON_THROW_ON_ERROR) : null;
                if (!is_array($stored) || !is_numeric($stored['expires'] ?? null) || (int)$stored['expires'] <= $now) {
                    $this->deleteFileCacheEntryForPruning($filepath);
                    unset($files[$index]);
                }
            } catch (\Throwable) {
                $this->deleteFileCacheEntryForPruning($filepath);
                unset($files[$index]);
            }
        }

        usort($files, static function(string $left, string $right): int {
            return [filemtime($left) ?: 0, $left] <=> [filemtime($right) ?: 0, $right];
        });
        while (count($files) >= self::CACHE_MAX_ENTRIES) {
            $filepath = array_shift($files);
            if (is_string($filepath)) {
                $this->deleteFileCacheEntryForPruning($filepath);
            }
        }
    }

    private function deleteFileCacheEntryForPruning(string $filepath): void
    {
        if (is_file($filepath) && !@unlink($filepath)) {
            throw new \RuntimeException('Unable to remove an owned redirect result cache file.');
        }
    }

    /**
     * Invalidate all redirect caches
     *
     * @return void
     */
    public function invalidateCaches(): void
    {
        RedirectManager::$plugin->localCache->invalidateRedirectFamily();
    }

    /**
     * Increment hit count for a redirect
     *
     * @param int $id
     * @return void
     */
    private function incrementHitCount(int $id): void
    {
        Craft::$app->getDb()->createCommand()
            ->update(
                RedirectRecord::tableName(),
                [
                    'hitCount' => new \yii\db\Expression('[[hitCount]] + 1'),
                    'lastHit' => Db::prepareDateForDb(new \DateTime()),
                ],
                ['id' => $id]
            )
            ->execute();
    }

    /**
     * Check if a URL should be excluded from redirect handling
     *
     * @param string $url
     * @return bool
     */
    private function isExcluded(string $url): bool
    {
        $settings = RedirectManager::$plugin->getSettings();

        foreach ($settings->excludePatterns as $pattern) {
            if (isset($pattern['pattern']) && !empty($pattern['pattern'])) {
                if ($this->matchesExcludePattern((string)$pattern['pattern'], $url)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Match a configured exclude regex with hot-path safety guards.
     *
     * @param string $pattern
     * @param string $url
     * @return bool
     */
    private function matchesExcludePattern(string $pattern, string $url): bool
    {
        if (strlen($pattern) > 500) {
            $this->logWarning('Rejected exclude pattern: exceeds length limit', ['pattern' => substr($pattern, 0, 100) . '...']);
            return false;
        }

        if (preg_match('/(\.\*|\.\+|\[.+\])[*+]\)?[*+]/', $pattern) === 1) {
            $this->logWarning('Rejected unsafe exclude pattern: nested quantifiers detected', ['pattern' => $pattern]);
            return false;
        }

        if (preg_match('/\([^)]*\|[^)]*\)[*+]/', $pattern) === 1) {
            $this->logWarning('Rejected unsafe exclude pattern: alternation with quantifier', ['pattern' => $pattern]);
            return false;
        }

        $regex = '`' . $pattern . '`i';
        if (@preg_match($regex, '') === false) {
            $this->logWarning('Rejected invalid exclude pattern: compilation failed', ['pattern' => $pattern]);
            return false;
        }

        $oldBacktrack = ini_get('pcre.backtrack_limit');
        $oldRecursion = ini_get('pcre.recursion_limit');
        ini_set('pcre.backtrack_limit', '10000');
        ini_set('pcre.recursion_limit', '1000');

        try {
            return @preg_match($regex, $url) === 1;
        } finally {
            ini_set('pcre.backtrack_limit', (string)$oldBacktrack);
            ini_set('pcre.recursion_limit', (string)$oldRecursion);
        }
    }

    /**
     * Strip query string from URL
     *
     * @param string $url
     * @return string
     */
    private function stripQueryString(string $url): string
    {
        return strtok($url, '?');
    }

    private function queryStringFromUrl(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? $query : null;
    }

    private function pathAndQueryFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }

        $query = $this->queryStringFromUrl($url);

        return $query === null ? $path : $path . '?' . $query;
    }

    /**
     * Append a source query string using the redirect response's current merge semantics.
     */
    private function appendQueryString(string $destination, string $queryString): string
    {
        $fragmentPosition = strpos($destination, '#');
        if ($fragmentPosition === false) {
            $base = $destination;
            $fragment = '';
        } else {
            $base = substr($destination, 0, $fragmentPosition);
            $fragment = substr($destination, $fragmentPosition);
        }

        $separator = strpos($base, '?') === false ? '?' : '&';

        return $base . $separator . $queryString . $fragment;
    }

    /**
     * Parse and clean URL
     *
     * @param string $url
     * @return string
     */
    private function parseUrl(string $url): string
    {
        // Clean up the URL
        $url = trim($url);
        $url = str_replace(["\r", "\n", "\t"], '', $url);

        // Normalize multiple slashes, but preserve scheme separator (://)
        if (preg_match('#^(https?://[^/]+)(.*)$#i', $url, $matches)) {
            // Full URL: normalize only the path portion
            $url = $matches[1] . preg_replace('#/+#', '/', $matches[2]);
        } else {
            // Relative path: normalize all slashes
            $url = preg_replace('#/+#', '/', $url);
        }

        return $url;
    }

    /**
     * Resolve a chain only when one of the ordered paths reaches a safe endpoint.
     *
     * @param array<int, string> $visited
     * @param array<int, array<string, mixed>>|null $candidates
     * @return array{safe: bool, destination: string, reason: string|null}
     */
    private function resolveRedirectChainOutcome(
        string $url,
        ?int $siteId,
        int $maxDepth = 10,
        array $visited = [],
        int $depth = 0,
        ?array $candidates = null,
    ): array {
        if (in_array($url, $visited, true)) {
            return ['safe' => false, 'destination' => $url, 'reason' => 'cycle'];
        }

        $visited[] = $url;
        $pathOnly = $this->pathAndQueryFromUrl($url);
        $fullUrl = UrlHelper::isAbsoluteUrl($url)
            ? $url
            : UrlHelper::siteUrl(ltrim($pathOnly, '/'), null, null, $siteId);
        [$fullUrl, $pathOnly] = $this->normalizeMatchingInputs($fullUrl, $pathOnly);
        $matchedCandidate = false;

        $candidates ??= $this->getEnabledRedirects($siteId);
        foreach ($candidates as $candidate) {
            $next = $this->resolveMatchingCandidate($candidate, $fullUrl, $this->parseUrl($pathOnly));
            if ($next === null) {
                continue;
            }

            $matchedCandidate = true;
            if ((int)$next['statusCode'] === 410) {
                return ['safe' => true, 'destination' => $url, 'reason' => null];
            }

            $nextUrl = (string)$next['destinationUrl'];
            if ($depth >= $maxDepth || in_array($nextUrl, $visited, true)) {
                continue;
            }

            $outcome = $this->resolveRedirectChainOutcome(
                $nextUrl,
                $siteId,
                $maxDepth,
                $visited,
                $depth + 1,
                $candidates,
            );
            if ($outcome['safe']) {
                return $outcome;
            }
        }

        if (!$matchedCandidate) {
            return ['safe' => true, 'destination' => $url, 'reason' => null];
        }

        return [
            'safe' => false,
            'destination' => $url,
            'reason' => $depth >= $maxDepth ? 'depth' : 'cycle-or-depth',
        ];
    }

    /**
     * Check if creating a redirect would create a circular loop
     *
     * @param string $sourceUrl The source URL (what we're redirecting FROM)
     * @param string $destinationUrl The destination URL (what we're redirecting TO)
     * @param int|null $excludeId Redirect ID to exclude from check (when updating)
     * @param int|null $siteId Site ID to scope chain checks to; null checks global redirects only
     * @return bool True if this would create a loop
     */
    private function wouldCreateLoop(string $sourceUrl, string $destinationUrl, ?int $excludeId = null, ?int $siteId = null): bool
    {
        // Parse and clean URLs
        $sourceParsed = $this->parseUrl($sourceUrl);
        $destParsed = $this->parseUrl($destinationUrl);

        // Same source and destination is obviously a loop
        if ($sourceParsed === $destParsed) {
            return true;
        }

        // Follow the chain from destination to see if it leads back to source
        $visited = [];
        $currentUrl = $destParsed;
        $maxDepth = 10;

        for ($i = 0; $i < $maxDepth; $i++) {
            // Prevent infinite checking
            if (in_array($currentUrl, $visited)) {
                break;
            }

            $visited[] = $currentUrl;

            // Check if this URL is a source for another redirect
            $nextRedirect = $this->findNextRedirectInChain($currentUrl, $siteId, $excludeId);

            if (!$nextRedirect) {
                // Chain ends here, no loop
                break;
            }

            // Get the destination of this redirect
            $nextDest = $this->parseUrl($nextRedirect['destinationUrl']);

            // If this redirects back to our source, we have a loop!
            if ($nextDest === $sourceParsed) {
                $this->logWarning('Circular redirect detected', [
                    'source' => $sourceParsed,
                    'destination' => $destParsed,
                    'chain' => array_merge($visited, [$nextDest]),
                ]);
                return true;
            }

            // Continue following the chain
            $currentUrl = $nextDest;
        }

        return false;
    }

    /**
     * Find the next redirect in a chain for the active site scope.
     *
     * Site-specific redirects win over global redirects when both define the
     * same source URL, matching normal redirect resolution.
     *
     * @return array<string, mixed>|null
     */
    private function findNextRedirectInChain(string $sourceUrlParsed, ?int $siteId, ?int $excludeId = null): ?array
    {
        // Stored exact/prefix parsed URLs are lowercase (RedirectRecord::beforeSave);
        // lowercase the probe so chain lookups stay case-insensitive on PostgreSQL.
        $sourceUrlParsed = strtolower($sourceUrlParsed);
        $candidateSiteIds = $siteId === null ? [null] : [$siteId, null];

        foreach ($candidateSiteIds as $candidateSiteId) {
            $query = (new Query())
                ->from(RedirectRecord::tableName())
                ->where(['enabled' => true])
                ->andWhere(['sourceUrlParsed' => $sourceUrlParsed])
                ->andWhere(['siteId' => $candidateSiteId])
                ->orderBy(['priority' => SORT_ASC, 'id' => SORT_ASC]);

            if ($excludeId !== null) {
                $query->andWhere(['!=', 'id', $excludeId]);
            }

            $redirect = $query->one();
            if (is_array($redirect)) {
                return $redirect;
            }
        }

        return null;
    }

    private function handleDuplicateRedirectIntegrityException(IntegrityException $e, string $sourceUrl, string $destinationUrl): bool
    {
        if (!str_contains($e->getMessage(), 'idx_redirectmanager_redirects_source_sitekey_unq')) {
            return false;
        }

        $this->logWarning('Redirect already exists', ['sourceUrl' => $sourceUrl]);
        Craft::$app->getSession()->setNotice(
            Craft::t('redirect-manager', 'Redirect already exists: {source} → {dest}', [
                'source' => $sourceUrl,
                'dest' => $destinationUrl,
            ])
        );

        return true;
    }
}

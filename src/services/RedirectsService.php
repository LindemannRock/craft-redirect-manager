<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\events\RedirectEvent;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
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
     * @var array Captured groups from the last successful match
     */
    private array $_lastMatchCaptures = [];

    /**
     * Initialize the service
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('redirect-manager');
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

        $settings = RedirectManager::$plugin->getSettings();

        // Build full path with query string for analytics
        // Check if pathOnly already includes query string
        $queryString = $request->getQueryString();
        if ($queryString && strpos($pathOnly, '?') === false) {
            $originalPath = $pathOnly . '?' . $queryString;
        } else {
            $originalPath = $pathOnly;
        }
        $originalFullUrl = $fullUrl;

        // Strip query string for matching if configured
        if ($settings->stripQueryString) {
            $pathOnlyForMatching = $this->stripQueryString($pathOnly);
            $fullUrlForMatching = $this->stripQueryString($fullUrl);
        } else {
            $pathOnlyForMatching = $pathOnly;
            $fullUrlForMatching = $fullUrl;
        }

        $this->logDebug('Handling 404', [
            'originalFullUrl' => $fullUrl,
            'pathForMatching' => $pathOnlyForMatching,
            'userAgent' => $request->getUserAgent(),
        ]);

        // Check if URL should be excluded
        if ($this->isExcluded($pathOnlyForMatching)) {
            $this->logDebug('URL excluded from redirect handling', ['url' => $pathOnlyForMatching]);
            return;
        }

        // Try to find a redirect (using query-stripped URLs for matching)
        $redirect = $this->findRedirect($fullUrlForMatching, $pathOnlyForMatching);

        if ($redirect) {
            // Record the 404 BEFORE executing redirect (since redirect ends the script)
            RedirectManager::$plugin->analytics->record404($originalPath, true);
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
     * @return array|null Returns redirect array with '_captures' key if match uses capture groups
     */
    public function findRedirect(string $fullUrl, string $pathOnly): ?array
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        // Try cache first (exact match only - cached redirects don't need capture recalculation)
        $redirect = $this->getFromCache($pathOnly, $siteId);
        if ($redirect) {
            $this->incrementHitCount($redirect['id']);
            return $redirect;
        }

        // Query database for redirects
        $redirects = $this->getEnabledRedirects($siteId);

        foreach ($redirects as $redirect) {
            if ($this->matchesRedirect($redirect, $fullUrl, $pathOnly)) {
                // Add captures to redirect for external use
                if (!empty($this->_lastMatchCaptures)) {
                    $redirect['_captures'] = $this->_lastMatchCaptures;
                }

                // Cache the matched redirect (without captures - they're URL-specific)
                $this->saveToCache($pathOnly, $redirect, $siteId);
                $this->incrementHitCount($redirect['id']);

                return $redirect;
            }
        }

        return null;
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
     */
    public function handleExternal404(string $url, array $context = []): ?array
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $siteId = $currentSite->id;

        // Strip query string for matching
        $fullUrl = $this->stripQueryString($url);
        $pathOnly = parse_url($fullUrl, PHP_URL_PATH) ?: $fullUrl;

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

        // Try to find redirect with stripped path first, then fall back to original
        $redirect = $this->findRedirect($fullUrl, $pathOnlyStripped);

        if (!$redirect && $pathOnlyStripped !== $pathOnly) {
            $redirect = $this->findRedirect($fullUrl, $pathOnly);
        }

        // Record 404 with source tracking
        RedirectManager::$plugin->analytics->record404(
            $pathOnly,
            (bool)$redirect,
            $context
        );

        if ($redirect) {
            // Apply captured groups to destination URL ($1, $2, etc.)
            if (!empty($redirect['_captures'])) {
                $originalDestination = $redirect['destinationUrl'];
                $redirect['destinationUrl'] = RedirectManager::$plugin->matching->applyCaptures(
                    $redirect['destinationUrl'],
                    $redirect['_captures']
                );

                if ($redirect['destinationUrl'] !== $originalDestination) {
                    $this->logDebug('Applied capture groups to external redirect destination', [
                        'original' => $originalDestination,
                        'resolved' => $redirect['destinationUrl'],
                        'captures' => $redirect['_captures'],
                    ]);
                }
            }

            // If we stripped site base path and destination is a relative path, add it back
            if ($siteBasePath !== '/' && $pathOnlyStripped !== $pathOnly) {
                $destUrl = $redirect['destinationUrl'];
                // Only prepend if destination is a relative path starting with /
                if ($destUrl && $destUrl[0] === '/' && !str_starts_with($destUrl, $siteBasePath . '/')) {
                    $redirect['destinationUrl'] = $siteBasePath . $destUrl;
                }
            }

            $this->logInfo('External 404 matched redirect', [
                'source' => $context['source'] ?? 'unknown',
                'url' => $pathOnly,
                'destination' => $redirect['destinationUrl'],
                'siteBasePath' => $siteBasePath,
            ]);
        }

        return $redirect;
    }

    /**
     * Check if a redirect matches the given URLs
     *
     * Also stores captured groups for use in destination URL replacement.
     *
     * @param array $redirect
     * @param string $fullUrl
     * @param string $pathOnly
     * @return bool
     */
    private function matchesRedirect(array $redirect, string $fullUrl, string $pathOnly): bool
    {
        $matchType = $redirect['matchType'];
        $sourceUrlParsed = $redirect['sourceUrlParsed'];
        $redirectSrcMatch = $redirect['redirectSrcMatch'] ?? 'pathonly';

        // Use pathOnly or fullUrl based on per-redirect setting
        $urlToMatch = $redirectSrcMatch === 'fullurl' ? $fullUrl : $pathOnly;

        // Use matchWithCaptures to get both match result and captured groups
        $result = RedirectManager::$plugin->matching->matchWithCaptures($matchType, $sourceUrlParsed, $urlToMatch);

        if ($result['matched']) {
            // Store captures for use in executeRedirect
            $this->_lastMatchCaptures = $result['captures'];
        }

        return $result['matched'];
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
        $destination = $redirect['destinationUrl'];
        $statusCode = $redirect['statusCode'];
        $settings = RedirectManager::$plugin->getSettings();

        // Apply captured groups to destination URL ($1, $2, etc.)
        // Check both the redirect array (from findRedirect) and the instance variable (from matchesRedirect)
        $captures = $redirect['_captures'] ?? $this->_lastMatchCaptures;

        if (!empty($captures)) {
            $originalDestination = $destination;
            $destination = RedirectManager::$plugin->matching->applyCaptures($destination, $captures);

            if ($destination !== $originalDestination) {
                $this->logDebug('Applied capture groups to destination', [
                    'original' => $originalDestination,
                    'resolved' => $destination,
                    'captures' => $captures,
                ]);
            }

            // Clear captures after use
            $this->_lastMatchCaptures = [];
        }

        // Resolve redirect chains to get final destination
        try {
            $destination = $this->resolveRedirectChain($destination);
        } catch (\Exception $e) {
            $this->logError('Failed to resolve redirect chain', ['error' => $e->getMessage()]);
        }

        // Handle query string preservation
        if ($settings->preserveQueryString) {
            $queryString = parse_url($fullUrl, PHP_URL_QUERY);
            if ($queryString) {
                $separator = strpos($destination, '?') === false ? '?' : '&';
                $destination .= $separator . $queryString;
            }
        }

        // Make destination URL absolute if relative
        if (!UrlHelper::isAbsoluteUrl($destination)) {
            $destination = UrlHelper::siteUrl($destination);
        }

        $this->logDebug('Executing redirect', [
            'from' => $pathOnly,
            'to' => $destination,
            'statusCode' => $statusCode,
        ]);

        // Set no-cache headers if configured
        if ($settings->setNoCacheHeaders) {
            Craft::$app->getResponse()->setNoCacheHeaders();
        }

        // Add custom headers
        foreach ($settings->additionalHeaders as $header) {
            if (isset($header['name']) && isset($header['value'])) {
                Craft::$app->getResponse()->headers->set($header['name'], $header['value']);
            }
        }

        // Perform the redirect
        Craft::$app->getResponse()->redirect($destination, $statusCode)->send();
        Craft::$app->end();
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

            $this->logInfo('Stashed element URI from database', [
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

        $this->logInfo('Checking URI change', [
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
                ->where(['sourceUrlParsed' => $newUrl])
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
                    count($conflictingRedirects) . ' outdated automatic redirect(s) removed because entry returned to a previous URL.'
                );
            }

            // SCENARIO 3: FORWARD PROGRESSION
            // Just keep existing redirects and add new one (default behavior)

            // FINALLY: Check if this would create a circular redirect (after cleanup)
            if ($this->wouldCreateLoop($oldUrl, $newUrl)) {
                $this->logError('Cannot create redirect: would create circular loop', [
                    'elementId' => $element->id,
                    'oldUri' => $oldUri,
                    'newUri' => $newUri,
                ]);

                // Show error message in CP
                Craft::$app->getSession()->setError(
                    'Entry saved, but automatic redirect was not created because it would create a circular redirect loop. ' .
                    'Please create a different redirect manually or change the slug.'
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

        // Check for circular redirects
        if ($this->wouldCreateLoop($attributes['sourceUrl'], $attributes['destinationUrl'])) {
            $this->logError('Cannot create redirect: would create circular loop', [
                'sourceUrl' => $attributes['sourceUrl'],
                'destinationUrl' => $attributes['destinationUrl'],
            ]);

            // Show specific error message to user
            Craft::$app->getSession()->setError(
                'Cannot create redirect: This would create a circular redirect loop. The destination eventually redirects back to the source.'
            );

            return false;
        }

        // Trigger before save event
        $event = new RedirectEvent(['redirect' => $attributes]);
        $this->trigger(self::EVENT_BEFORE_SAVE_REDIRECT, $event);

        if (!$event->isValid) {
            return false;
        }

        // Check for duplicate
        $existing = (new Query())
            ->from(RedirectRecord::tableName())
            ->where(['sourceUrlParsed' => $attributes['sourceUrlParsed']])
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

        if (!$record->save()) {
            $this->logError('Failed to save redirect', ['errors' => $record->getErrors()]);
            return false;
        }

        // Invalidate caches
        $this->invalidateCaches();

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
     * @return bool
     */
    public function updateRedirect(int $id, array $attributes): bool
    {
        $record = RedirectRecord::findOne($id);

        if (!$record) {
            $this->logError('Redirect not found', ['id' => $id]);
            return false;
        }

        // Parse source URL if changed
        if (isset($attributes['sourceUrl']) && !isset($attributes['sourceUrlParsed'])) {
            $attributes['sourceUrlParsed'] = $this->parseUrl($attributes['sourceUrl']);
        }

        // Check for circular redirects (if destination is being changed)
        if (isset($attributes['destinationUrl'])) {
            $sourceUrl = $attributes['sourceUrl'] ?? $record->sourceUrl;
            $destinationUrl = $attributes['destinationUrl'];

            if ($this->wouldCreateLoop($sourceUrl, $destinationUrl, $id)) {
                $this->logError('Cannot update redirect: would create circular loop', [
                    'id' => $id,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                ]);

                // Show specific error message to user
                Craft::$app->getSession()->setError(
                    'Cannot update redirect: This would create a circular redirect loop. The destination eventually redirects back to the source.'
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

        if (!$record->save()) {
            $this->logError('Failed to update redirect', ['id' => $id, 'errors' => $record->getErrors()]);
            return false;
        }

        // Invalidate caches
        $this->invalidateCaches();

        // Trigger after save event
        $this->trigger(self::EVENT_AFTER_SAVE_REDIRECT, $event);

        $this->logInfo('Redirect updated', ['id' => $id]);

        return true;
    }

    /**
     * Delete a redirect
     *
     * @param int $id
     * @return bool
     */
    public function deleteRedirect(int $id): bool
    {
        $record = RedirectRecord::findOne($id);

        if (!$record) {
            $this->logError('Redirect not found', ['id' => $id]);
            return false;
        }

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

        // Invalidate caches
        $this->invalidateCaches();

        // Trigger after delete event
        $this->trigger(self::EVENT_AFTER_DELETE_REDIRECT, $event);

        $this->logInfo('Redirect deleted', ['id' => $id]);

        return true;
    }

    /**
     * Get all enabled redirects for a site, ordered by priority
     *
     * @param int|null $siteId
     * @return array
     */
    public function getEnabledRedirects(?int $siteId = null): array
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

        return $query->all();
    }

    /**
     * Get cache directory path
     *
     * @return string
     */
    private function getCachePath(): string
    {
        return Craft::$app->getPath()->getRuntimePath() . '/redirect-manager/cache/redirects/';
    }

    /**
     * Get a redirect from cache
     *
     * @param string $url
     * @param int $siteId
     * @return array|null
     */
    private function getFromCache(string $url, int $siteId): ?array
    {
        $settings = RedirectManager::$plugin->getSettings();

        // Check if caching is enabled
        if (!$settings->enableRedirectCache) {
            return null;
        }

        $cacheKey = 'redirectmanager:redirect:' . md5($url . '_' . $siteId);

        // Use Redis/database cache if configured
        if ($settings->cacheStorageMethod === 'redis') {
            $cached = Craft::$app->cache->get($cacheKey);
            if ($cached !== false) {
                $this->logDebug('Redirect cache hit (Redis)', ['url' => $url]);
                return $cached;
            }
            return null;
        }

        // Use file-based cache (default)
        $cachePath = $this->getCachePath();
        $filename = md5($url) . '_' . $siteId . '.cache';
        $filepath = $cachePath . $filename;

        if (file_exists($filepath)) {
            $data = @file_get_contents($filepath);
            if ($data) {
                $cache = @unserialize($data);
                if ($cache && isset($cache['expires']) && $cache['expires'] > time()) {
                    $this->logDebug('Redirect cache hit (File)', ['url' => $url]);
                    return $cache['data'];
                }
                // Expired - delete file
                @unlink($filepath);
            }
        }

        return null;
    }

    /**
     * Save a redirect to cache
     *
     * @param string $url
     * @param array $redirect
     * @param int $siteId
     * @return void
     */
    private function saveToCache(string $url, array $redirect, int $siteId): void
    {
        $settings = RedirectManager::$plugin->getSettings();

        // Check if caching is enabled
        if (!$settings->enableRedirectCache) {
            return;
        }

        $duration = $settings->redirectCacheDuration ?? 3600;
        $cacheKey = 'redirectmanager:redirect:' . md5($url . '_' . $siteId);

        // Use Redis/database cache if configured
        if ($settings->cacheStorageMethod === 'redis') {
            $cache = Craft::$app->cache;
            $cache->set($cacheKey, $redirect, $duration);

            // Track key in set for selective deletion
            if ($cache instanceof \yii\redis\Cache) {
                $redis = $cache->redis;
                $redis->executeCommand('SADD', ['redirectmanager-redirect-keys', $cacheKey]);
            }

            $this->logDebug('Redirect cached (Redis)', ['url' => $url, 'duration' => $duration]);
            return;
        }

        // Use file-based cache (default)
        $cachePath = $this->getCachePath();

        // Create cache directory if it doesn't exist
        if (!is_dir($cachePath)) {
            \craft\helpers\FileHelper::createDirectory($cachePath);
        }

        $filename = md5($url) . '_' . $siteId . '.cache';
        $filepath = $cachePath . $filename;

        $cacheData = [
            'data' => $redirect,
            'expires' => time() + $duration,
        ];

        @file_put_contents($filepath, serialize($cacheData));

        $this->logDebug('Redirect cached (File)', ['url' => $url, 'duration' => $duration]);
    }

    /**
     * Invalidate all redirect caches
     *
     * @return void
     */
    public function invalidateCaches(): void
    {
        $settings = RedirectManager::$plugin->getSettings();

        if ($settings->cacheStorageMethod === 'redis') {
            // Clear Redis cache
            $cache = Craft::$app->cache;
            if ($cache instanceof \yii\redis\Cache) {
                $redis = $cache->redis;

                // Get all redirect cache keys from tracking set
                $keys = $redis->executeCommand('SMEMBERS', ['redirectmanager-redirect-keys']) ?: [];

                // Delete redirect cache keys using Craft's cache component
                foreach ($keys as $key) {
                    $cache->delete($key);
                }

                // Clear the tracking set
                $redis->executeCommand('DEL', ['redirectmanager-redirect-keys']);

                $this->logDebug('Redirect caches invalidated (Redis)', ['count' => count($keys)]);
            }
        } else {
            // Clear file cache
            $cachePath = $this->getCachePath();

            if (is_dir($cachePath)) {
                $files = glob($cachePath . '*.cache');
                $count = 0;
                foreach ($files as $file) {
                    if (@unlink($file)) {
                        $count++;
                    }
                }
                $this->logDebug('Redirect caches invalidated (File)', ['count' => $count]);
            }
        }
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
                $regex = '`' . $pattern['pattern'] . '`i';
                try {
                    if (preg_match($regex, $url)) {
                        return true;
                    }
                } catch (\Exception $e) {
                    $this->logError('Invalid exclude pattern regex', ['pattern' => $pattern['pattern']]);
                }
            }
        }

        return false;
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

        // Remove multiple slashes
        $url = preg_replace('#/+#', '/', $url);

        return $url;
    }

    /**
     * Resolve redirect chain to get final destination
     *
     * @param string $url
     * @param int $maxDepth Maximum chain depth to prevent infinite loops
     * @return string Final destination URL
     */
    private function resolveRedirectChain(string $url, int $maxDepth = 10): string
    {
        $visited = [];
        $currentUrl = $url;
        $chain = [$url];

        for ($i = 0; $i < $maxDepth; $i++) {
            // Prevent loops
            if (in_array($currentUrl, $visited)) {
                $this->logWarning('Redirect loop detected', ['chain' => $chain]);
                break;
            }

            $visited[] = $currentUrl;

            // If URL is absolute, extract just the path
            if (UrlHelper::isAbsoluteUrl($currentUrl)) {
                $urlPath = parse_url($currentUrl, PHP_URL_PATH);
                $searchUrl = $urlPath ?: $currentUrl;
            } else {
                $searchUrl = '/' . ltrim($currentUrl, '/');
            }

            $parsedUrl = $this->parseUrl($searchUrl);

            $this->logInfo('Checking for next redirect in chain', [
                'currentUrl' => $currentUrl,
                'searchUrl' => $searchUrl,
                'parsedUrl' => $parsedUrl,
            ]);

            // Check if this URL is a source for another redirect
            $nextRedirect = (new Query())
                ->from(RedirectRecord::tableName())
                ->where(['enabled' => true])
                ->andWhere(['sourceUrlParsed' => $parsedUrl])
                ->one();

            if (!$nextRedirect) {
                $this->logInfo('No more redirects in chain', ['stoppedAt' => $currentUrl]);
                // No more redirects in chain
                break;
            }

            $this->logInfo('Found next redirect in chain', [
                'from' => $nextRedirect['sourceUrlParsed'],
                'to' => $nextRedirect['destinationUrl'],
            ]);

            // Record analytics for this URL in the chain as handled
            RedirectManager::$plugin->analytics->record404($searchUrl, true);

            $currentUrl = $nextRedirect['destinationUrl'];
            $chain[] = $currentUrl;
        }

        if (count($chain) > 1) {
            $this->logInfo('Resolved redirect chain', [
                'originalUrl' => $url,
                'finalUrl' => $currentUrl,
                'chain' => $chain,
                'depth' => count($chain) - 1,
            ]);
        }

        return $currentUrl;
    }

    /**
     * Check if creating a redirect would create a circular loop
     *
     * @param string $sourceUrl The source URL (what we're redirecting FROM)
     * @param string $destinationUrl The destination URL (what we're redirecting TO)
     * @param int|null $excludeId Redirect ID to exclude from check (when updating)
     * @return bool True if this would create a loop
     */
    private function wouldCreateLoop(string $sourceUrl, string $destinationUrl, ?int $excludeId = null): bool
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
            $query = (new Query())
                ->from(RedirectRecord::tableName())
                ->where(['enabled' => true])
                ->andWhere(['sourceUrlParsed' => $currentUrl]);

            // Exclude the redirect we're updating
            if ($excludeId !== null) {
                $query->andWhere(['!=', 'id', $excludeId]);
            }

            $nextRedirect = $query->one();

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
}

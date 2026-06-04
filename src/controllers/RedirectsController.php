<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Redirects Controller
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class RedirectsController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

    /**
     * Verify that the user has access to the given redirect's site.
     *
     * @param RedirectRecord $redirect
     * @throws ForbiddenHttpException
     */
    private function _requireEditableRedirect(RedirectRecord $redirect): void
    {
        if ($redirect->siteId !== null) {
            $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
            if (!in_array((int)$redirect->siteId, $editableSiteIds, true)) {
                throw new ForbiddenHttpException(Craft::t('redirect-manager', 'User does not have permission to modify redirects for this site.'));
            }
        }
    }

    /**
     * List all redirects
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('redirectManager:manageRedirects');

        $request = Craft::$app->getRequest();
        $settings = RedirectManager::$plugin->getSettings();

        // ---- Param parsing + allowlist validation -------------------------

        $statusFilter = (string) $request->getQueryParam('status', 'all');
        $validStatuses = ['all', 'enabled', 'disabled'];
        if (!in_array($statusFilter, $validStatuses, true)) {
            $statusFilter = 'all';
        }

        $matchTypeFilter = (string) $request->getQueryParam('matchType', 'all');
        $validMatchTypes = ['all', 'exact', 'regex', 'wildcard', 'prefix'];
        if (!in_array($matchTypeFilter, $validMatchTypes, true)) {
            $matchTypeFilter = 'all';
        }

        $creationTypeFilter = (string) $request->getQueryParam('creationType', 'all');
        $validCreationTypes = ['all', 'manual', 'entry-change'];
        if (!in_array($creationTypeFilter, $validCreationTypes, true)) {
            $creationTypeFilter = 'all';
        }

        $search = trim((string) $request->getQueryParam('search', ''));
        if (mb_strlen($search) > 64) {
            $search = mb_substr($search, 0, 64);
        }

        $validSortFields = [
            'sourceUrl', 'matchType', 'creationType', 'siteId',
            'statusCode', 'hitCount', 'enabled', 'sourcePlugin', 'dateCreated',
        ];
        $sort = (string) $request->getQueryParam('sort', 'dateCreated');
        if (!in_array($sort, $validSortFields, true)) {
            $sort = 'dateCreated';
        }
        $dir = strtolower((string) $request->getQueryParam('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $page = max(1, (int) $request->getQueryParam('page', 1));
        $limit = max(1, (int) $settings->itemsPerPage);
        $offset = ($page - 1) * $limit;

        // ---- Build query --------------------------------------------------

        // Scope to user's editable sites (redirects with null siteId = all sites, always visible)
        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();

        $query = (new \craft\db\Query())
            ->from(\lindemannrock\redirectmanager\records\RedirectRecord::tableName())
            ->andWhere(['or', ['siteId' => null], ['siteId' => $editableSiteIds]]);

        if ($statusFilter === 'enabled') {
            $query->andWhere(['enabled' => true]);
        } elseif ($statusFilter === 'disabled') {
            $query->andWhere(['enabled' => false]);
        }

        if ($matchTypeFilter !== 'all') {
            $query->andWhere(['matchType' => $matchTypeFilter]);
        }

        if ($creationTypeFilter !== 'all') {
            $query->andWhere(['creationType' => $creationTypeFilter]);
        }

        if ($search !== '') {
            $query->andWhere([
                'or',
                ['like', 'sourceUrl', $search],
                ['like', 'destinationUrl', $search],
            ]);
        }

        // Sort (column allowlist enforced above; map to DB column array).
        $sortDirection = $dir === 'asc' ? SORT_ASC : SORT_DESC;
        $query->orderBy([$sort => $sortDirection]);

        // Total count reflects the filtered subset so the pager matches the
        // visible list. Computed before LIMIT/OFFSET.
        $totalCount = (int) $query->count();

        $query->limit($limit)->offset($offset);
        $redirects = $query->all();

        return $this->renderTemplate('redirect-manager/redirects/index', [
            'redirects' => $redirects,
            'settings' => $settings,
            'statusFilter' => $statusFilter,
            'matchTypeFilter' => $matchTypeFilter,
            'creationTypeFilter' => $creationTypeFilter,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'limit' => $limit,
            'totalCount' => $totalCount,
            'canCreate' => Craft::$app->getUser()->checkPermission('redirectManager:createRedirects'),
            'canEdit' => Craft::$app->getUser()->checkPermission('redirectManager:editRedirects'),
            'canDelete' => Craft::$app->getUser()->checkPermission('redirectManager:deleteRedirects'),
            'canImportExport' => Craft::$app->getUser()->checkPermission('redirectManager:manageImportExport'),
        ]);
    }

    /**
     * Edit a redirect
     *
     * @param int|null $redirectId
     * @return Response
     */
    public function actionEdit(?int $redirectId = null): Response
    {
        $this->requirePermission($redirectId ? 'redirectManager:editRedirects' : 'redirectManager:createRedirects');

        $redirect = null;

        if ($redirectId) {
            $redirect = RedirectRecord::findOne($redirectId);

            if (!$redirect) {
                throw new \yii\web\NotFoundHttpException(Craft::t('redirect-manager', 'Redirect not found'));
            }
        }

        $matchTypes = RedirectManager::$plugin->matching->getMatchTypes();
        $statusCodes = [
            301 => Craft::t('redirect-manager', '301 - Moved Permanently'),
            302 => Craft::t('redirect-manager', '302 - Found (Temporary)'),
            303 => Craft::t('redirect-manager', '303 - See Other'),
            307 => Craft::t('redirect-manager', '307 - Temporary Redirect'),
            308 => Craft::t('redirect-manager', '308 - Permanent Redirect'),
            410 => Craft::t('redirect-manager', '410 - Gone'),
        ];

        // Get editable sites for dropdown
        $siteOptions = [
            ['label' => Craft::t('redirect-manager', 'All Sites'), 'value' => ''],
        ];
        foreach (Craft::$app->getSites()->getEditableSites() as $site) {
            $siteOptions[] = [
                'label' => $site->name,
                'value' => $site->id,
            ];
        }

        // Verify user has access to this redirect's site
        if ($redirect && $redirect->siteId !== null) {
            $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
            if (!in_array((int)$redirect->siteId, $editableSiteIds, true)) {
                throw new ForbiddenHttpException(Craft::t('redirect-manager', 'User does not have permission to edit redirects for this site.'));
            }
        }

        return $this->renderTemplate('redirect-manager/redirects/edit', [
            'redirect' => $redirect,
            'matchTypes' => $matchTypes,
            'statusCodes' => $statusCodes,
            'siteOptions' => $siteOptions,
            'isNew' => $redirect === null,
            'pluginHandle' => RedirectManager::$plugin->id,
        ]);
    }

    /**
     * Save a redirect
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $redirectId = $request->getBodyParam('redirectId');

        $this->requirePermission($redirectId ? 'redirectManager:editRedirects' : 'redirectManager:createRedirects');

        $submittedSiteId = $request->getBodyParam('siteId') ? (int)$request->getBodyParam('siteId') : null;

        // Validate that user has access to the submitted site
        if ($submittedSiteId !== null) {
            $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
            if (!in_array($submittedSiteId, $editableSiteIds, true)) {
                throw new ForbiddenHttpException(Craft::t('redirect-manager', 'User does not have permission to create redirects for this site.'));
            }
        }

        $attributes = [
            'sourceUrl' => $request->getBodyParam('sourceUrl'),
            'destinationUrl' => $request->getBodyParam('destinationUrl'),
            'redirectSrcMatch' => $request->getBodyParam('redirectSrcMatch', 'pathonly'),
            'matchType' => $request->getBodyParam('matchType', 'exact'),
            'statusCode' => (int)$request->getBodyParam('statusCode', 301),
            'enabled' => (bool)$request->getBodyParam('enabled', true),
            'priority' => (int)$request->getBodyParam('priority', 0),
            'siteId' => $submittedSiteId,
        ];

        $success = false;
        $newRedirectId = null;

        if ($redirectId) {
            // Update existing redirect
            $success = RedirectManager::$plugin->redirects->updateRedirect($redirectId, $attributes);
            $newRedirectId = $redirectId;
        } else {
            // Create new redirect - returns ID on success, false on failure
            $result = RedirectManager::$plugin->redirects->createRedirect($attributes);
            $success = $result !== false;
            $newRedirectId = $result ?: null;
        }

        if ($success) {
            Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'Redirect saved.'));
            // Create object for URL token replacement
            $redirect = RedirectRecord::findOne($newRedirectId);
            return $this->redirectToPostedUrl($redirect);
        }

        // Service should have already set specific error message
        // Only set generic error if none was set
        $session = Craft::$app->getSession();
        $hasError = false;
        foreach ($session->getAllFlashes() as $key => $value) {
            if (strpos($key, 'error') !== false) {
                $hasError = true;
                break;
            }
        }

        if (!$hasError) {
            $session->setError(Craft::t('redirect-manager', 'Could not save redirect'));
        }

        // Re-render edit form with submitted data
        $matchTypes = RedirectManager::$plugin->matching->getMatchTypes();
        $statusCodes = [
            301 => Craft::t('redirect-manager', '301 - Moved Permanently'),
            302 => Craft::t('redirect-manager', '302 - Found (Temporary)'),
            303 => Craft::t('redirect-manager', '303 - See Other'),
            307 => Craft::t('redirect-manager', '307 - Temporary Redirect'),
            308 => Craft::t('redirect-manager', '308 - Permanent Redirect'),
            410 => Craft::t('redirect-manager', '410 - Gone'),
        ];

        // Get editable sites for dropdown
        $siteOptions = [
            ['label' => Craft::t('redirect-manager', 'All Sites'), 'value' => ''],
        ];
        foreach (Craft::$app->getSites()->getEditableSites() as $site) {
            $siteOptions[] = [
                'label' => $site->name,
                'value' => $site->id,
            ];
        }

        // Create RedirectRecord with submitted data (unsaved) for form repopulation
        if ($redirectId) {
            $redirect = RedirectRecord::findOne($redirectId);
            if ($redirect) {
                $redirect->setAttributes($attributes, false);
            }
        } else {
            $redirect = new RedirectRecord();
            $redirect->setAttributes($attributes, false);
        }

        // Validate using the record's built-in validation
        if (!$redirect->validate()) {
            // Validation failed - errors are already on the record
        }

        return $this->renderTemplate('redirect-manager/redirects/edit', [
            'redirect' => $redirect,
            'matchTypes' => $matchTypes,
            'statusCodes' => $statusCodes,
            'siteOptions' => $siteOptions,
            'isNew' => !$redirectId,
            'pluginHandle' => RedirectManager::$plugin->id,
        ]);
    }

    /**
     * Delete a redirect
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:deleteRedirects');

        $redirectId = Craft::$app->getRequest()->getRequiredBodyParam('redirectId');

        $redirect = RedirectRecord::findOne($redirectId);
        if ($redirect) {
            $this->_requireEditableRedirect($redirect);
        }

        if (RedirectManager::$plugin->redirects->deleteRedirect((int)$redirectId, $redirect)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['success' => false, 'error' => 'Could not delete redirect']);
    }

    /**
     * Bulk delete redirects
     *
     * @return Response
     */
    public function actionBulkDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:deleteRedirects');

        $redirectIds = array_map('intval', (array)Craft::$app->getRequest()->getRequiredBodyParam('redirectIds'));
        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
        /** @var array<int, RedirectRecord> $redirects */
        $redirects = RedirectRecord::find()
            ->where(['id' => $redirectIds])
            ->all();

        $deleted = 0;
        foreach ($redirects as $redirect) {
            if ($redirect->siteId !== null && !in_array((int)$redirect->siteId, $editableSiteIds, true)) {
                continue; // Skip redirects for sites user can't access
            }
            if (RedirectManager::$plugin->redirects->deleteRedirect((int)$redirect->id, $redirect)) {
                $deleted++;
            }
        }

        return $this->asJson([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }

    /**
     * Toggle redirect enabled status
     *
     * @return Response
     */
    public function actionToggleEnabled(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:editRedirects');

        $redirectId = Craft::$app->getRequest()->getRequiredBodyParam('redirectId');
        $enabled = (bool)Craft::$app->getRequest()->getRequiredBodyParam('enabled');

        $redirect = RedirectRecord::findOne($redirectId);
        if ($redirect) {
            $this->_requireEditableRedirect($redirect);
        }

        if (RedirectManager::$plugin->redirects->updateRedirect((int)$redirectId, ['enabled' => $enabled], $redirect)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['success' => false, 'error' => 'Could not update redirect']);
    }

    /**
     * Bulk enable redirects
     *
     * @return Response
     */
    public function actionBulkEnable(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:editRedirects');

        $redirectIds = array_map('intval', (array)Craft::$app->getRequest()->getRequiredBodyParam('redirectIds'));
        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
        /** @var array<int, RedirectRecord> $redirects */
        $redirects = RedirectRecord::find()
            ->where(['id' => $redirectIds])
            ->all();

        $updated = 0;
        foreach ($redirects as $redirect) {
            if ($redirect->siteId !== null && !in_array((int)$redirect->siteId, $editableSiteIds, true)) {
                continue;
            }
            if (RedirectManager::$plugin->redirects->updateRedirect((int)$redirect->id, ['enabled' => true], $redirect)) {
                $updated++;
            }
        }

        return $this->asJson([
            'success' => true,
            'updated' => $updated,
        ]);
    }

    /**
     * Bulk disable redirects
     *
     * @return Response
     */
    public function actionBulkDisable(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:editRedirects');

        $redirectIds = array_map('intval', (array)Craft::$app->getRequest()->getRequiredBodyParam('redirectIds'));
        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
        /** @var array<int, RedirectRecord> $redirects */
        $redirects = RedirectRecord::find()
            ->where(['id' => $redirectIds])
            ->all();

        $updated = 0;
        foreach ($redirects as $redirect) {
            if ($redirect->siteId !== null && !in_array((int)$redirect->siteId, $editableSiteIds, true)) {
                continue;
            }
            if (RedirectManager::$plugin->redirects->updateRedirect((int)$redirect->id, ['enabled' => false], $redirect)) {
                $updated++;
            }
        }

        return $this->asJson([
            'success' => true,
            'updated' => $updated,
        ]);
    }

    /**
     * Test a redirect
     *
     * @return Response
     */
    public function actionTest(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('redirectManager:manageRedirects');

        $testUrl = Craft::$app->getRequest()->getRequiredBodyParam('testUrl');
        $matchType = Craft::$app->getRequest()->getBodyParam('matchType', 'exact');
        $pattern = Craft::$app->getRequest()->getRequiredBodyParam('pattern');

        $matches = RedirectManager::$plugin->matching->matches($matchType, $pattern, $testUrl);

        return $this->asJson([
            'success' => true,
            'matches' => $matches,
        ]);
    }
}

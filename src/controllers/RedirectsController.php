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
        $this->setLoggingHandle('redirect-manager');
    }

    /**
     * List all redirects
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('redirectManager:viewRedirects');

        $request = Craft::$app->getRequest();
        $settings = RedirectManager::$plugin->getSettings();

        // Get filter parameters
        $search = $request->getQueryParam('search', '');
        $statusFilter = $request->getQueryParam('status', 'all');
        $matchTypeFilter = $request->getQueryParam('matchType', 'all');
        $creationTypeFilter = $request->getQueryParam('creationType', 'all');
        $sort = $request->getQueryParam('sort', 'dateCreated');
        $dir = $request->getQueryParam('dir', 'desc');
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $limit = $settings->itemsPerPage ?? 100;
        $offset = ($page - 1) * $limit;

        // Build query
        $query = (new \craft\db\Query())
            ->from(\lindemannrock\redirectmanager\records\RedirectRecord::tableName());

        // Apply status filter
        if ($statusFilter === 'enabled') {
            $query->andWhere(['enabled' => true]);
        } elseif ($statusFilter === 'disabled') {
            $query->andWhere(['enabled' => false]);
        }

        // Apply match type filter
        if ($matchTypeFilter !== 'all') {
            $query->andWhere(['matchType' => $matchTypeFilter]);
        }

        // Apply creation type filter
        if ($creationTypeFilter !== 'all') {
            $query->andWhere(['creationType' => $creationTypeFilter]);
        }

        // Apply search
        if (!empty($search)) {
            $query->andWhere([
                'or',
                ['like', 'sourceUrl', $search],
                ['like', 'destinationUrl', $search],
            ]);
        }

        // Apply sorting
        $orderBy = match ($sort) {
            'sourceUrl' => "sourceUrl $dir",
            'statusCode' => "statusCode $dir",
            'hitCount' => "hitCount $dir",
            default => "dateCreated $dir",
        };
        $query->orderBy($orderBy);

        // Get total count for pagination
        $totalCount = $query->count();
        $totalPages = $totalCount > 0 ? (int)ceil($totalCount / $limit) : 1;

        // Apply pagination
        $query->limit($limit)->offset($offset);

        // Get redirects
        $redirects = $query->all();

        return $this->renderTemplate('redirect-manager/redirects/index', [
            'redirects' => $redirects,
            'settings' => $settings,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
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
                throw new \yii\web\NotFoundHttpException('Redirect not found');
            }
        }

        $matchTypes = RedirectManager::$plugin->matching->getMatchTypes();
        $statusCodes = [
            301 => '301 - Moved Permanently',
            302 => '302 - Found (Temporary)',
            303 => '303 - See Other',
            307 => '307 - Temporary Redirect',
            308 => '308 - Permanent Redirect',
            410 => '410 - Gone',
        ];

        return $this->renderTemplate('redirect-manager/redirects/edit', [
            'redirect' => $redirect,
            'matchTypes' => $matchTypes,
            'statusCodes' => $statusCodes,
            'isNew' => $redirect === null,
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

        $attributes = [
            'sourceUrl' => $request->getBodyParam('sourceUrl'),
            'destinationUrl' => $request->getBodyParam('destinationUrl'),
            'redirectSrcMatch' => $request->getBodyParam('redirectSrcMatch', 'pathonly'),
            'matchType' => $request->getBodyParam('matchType', 'exact'),
            'statusCode' => (int)$request->getBodyParam('statusCode', 301),
            'enabled' => (bool)$request->getBodyParam('enabled', true),
            'priority' => (int)$request->getBodyParam('priority', 0),
            'siteId' => $request->getBodyParam('siteId') ? (int)$request->getBodyParam('siteId') : null,
        ];

        $success = false;

        if ($redirectId) {
            // Update existing redirect
            $success = RedirectManager::$plugin->redirects->updateRedirect($redirectId, $attributes);
        } else {
            // Create new redirect
            $success = RedirectManager::$plugin->redirects->createRedirect($attributes);
        }

        if ($success) {
            Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'Redirect saved successfully'));
            return $this->redirectToPostedUrl();
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
            301 => '301 - Moved Permanently',
            302 => '302 - Found (Temporary)',
            303 => '303 - See Other',
            307 => '307 - Temporary Redirect',
            308 => '308 - Permanent Redirect',
            410 => '410 - Gone',
        ];

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
            'isNew' => !$redirectId,
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
        $this->requirePermission('redirectManager:deleteRedirects');

        $redirectId = Craft::$app->getRequest()->getRequiredBodyParam('redirectId');

        if (RedirectManager::$plugin->redirects->deleteRedirect($redirectId)) {
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
        $this->requirePermission('redirectManager:deleteRedirects');

        $redirectIds = Craft::$app->getRequest()->getRequiredBodyParam('redirectIds');

        $deleted = 0;
        foreach ($redirectIds as $redirectId) {
            if (RedirectManager::$plugin->redirects->deleteRedirect($redirectId)) {
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
        $this->requirePermission('redirectManager:editRedirects');

        $redirectId = Craft::$app->getRequest()->getRequiredBodyParam('redirectId');
        $enabled = (bool)Craft::$app->getRequest()->getRequiredBodyParam('enabled');

        if (RedirectManager::$plugin->redirects->updateRedirect($redirectId, ['enabled' => $enabled])) {
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
        $this->requirePermission('redirectManager:editRedirects');

        $redirectIds = Craft::$app->getRequest()->getRequiredBodyParam('redirectIds');

        $updated = 0;
        foreach ($redirectIds as $redirectId) {
            if (RedirectManager::$plugin->redirects->updateRedirect($redirectId, ['enabled' => true])) {
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
        $this->requirePermission('redirectManager:editRedirects');

        $redirectIds = Craft::$app->getRequest()->getRequiredBodyParam('redirectIds');

        $updated = 0;
        foreach ($redirectIds as $redirectId) {
            if (RedirectManager::$plugin->redirects->updateRedirect($redirectId, ['enabled' => false])) {
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
        $this->requirePermission('redirectManager:viewRedirects');

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

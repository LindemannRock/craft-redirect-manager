<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\redirectmanager\RedirectManager;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

/**
 * Read-only JSON API controller.
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.33.0
 */
class ApiController extends Controller
{
    /**
     * Header accepted for token-protected JSON API requests.
     */
    public const TOKEN_HEADER = 'X-Redirect-Manager-Key';

    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = [
        'get-redirects',
    ];

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $settings = RedirectManager::$plugin->getSettings();

        if (!$settings->apiEndpointEnabled) {
            throw new NotFoundHttpException('API endpoint not found.');
        }

        $token = trim((string)($settings->apiEndpointToken ?? ''));
        if ($token === '') {
            throw new UnauthorizedHttpException('API token is not configured.');
        }

        if (!$this->hasValidToken($token)) {
            throw new UnauthorizedHttpException('Invalid or missing API token.');
        }

        return parent::beforeAction($action);
    }

    /**
     * Return enabled redirects as JSON.
     */
    public function actionGetRedirects(): Response
    {
        $siteId = $this->requestedSiteId();
        if ($siteId === false) {
            return $this->asJson([]);
        }

        return $this->asJson(
            RedirectManager::$plugin->redirects->getEnabledRedirects($siteId),
        );
    }

    /**
     * Validate bearer/header token input.
     */
    private function hasValidToken(string $expectedToken): bool
    {
        $headers = Craft::$app->getRequest()->getHeaders();
        $providedToken = $headers->get(self::TOKEN_HEADER);

        if (!is_string($providedToken) || trim($providedToken) === '') {
            $authorization = $headers->get('Authorization');
            if (is_string($authorization) && preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches) === 1) {
                $providedToken = $matches[1];
            }
        }

        return is_string($providedToken)
            && hash_equals($expectedToken, trim($providedToken));
    }

    /**
     * Resolve optional site/siteId query params.
     *
     * @return int|false|null false means an explicit invalid site was requested.
     */
    private function requestedSiteId(): int|false|null
    {
        $request = Craft::$app->getRequest();
        $siteHandle = $request->getParam('site');
        if (is_string($siteHandle) && trim($siteHandle) !== '') {
            $site = Craft::$app->getSites()->getSiteByHandle(trim($siteHandle));

            return $site?->id ?? false;
        }

        $siteId = $request->getParam('siteId');
        if ($siteId !== null && $siteId !== '') {
            if (!is_numeric($siteId)) {
                return false;
            }

            $site = Craft::$app->getSites()->getSiteById((int)$siteId);

            return $site?->id ?? false;
        }

        return null;
    }
}

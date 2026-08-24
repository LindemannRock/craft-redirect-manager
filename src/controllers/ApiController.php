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
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\TooManyRequestsHttpException;
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
     * Cache key prefix for token-scoped JSON API rate-limit counters.
     */
    private const RATE_LIMIT_CACHE_PREFIX = 'redirectmanager:api-rate-limit:';

    /**
     * Fixed rate-limit window duration, in seconds.
     */
    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    /**
     * Maximum time to wait for one token rate-limit decision.
     */
    private const RATE_LIMIT_MUTEX_TIMEOUT = 5;

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

        $this->requireAcceptsJson();

        $token = trim((string)($settings->apiEndpointToken ?? ''));
        if ($token === '') {
            throw new UnauthorizedHttpException('API token is not configured.');
        }

        if (!$this->hasValidToken($token)) {
            throw new UnauthorizedHttpException('Invalid or missing API token.');
        }

        $this->enforceRateLimit($token, $settings->apiEndpointRateLimit);

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
     * Enforce the configured fixed-window request limit for the configured API token.
     */
    private function enforceRateLimit(string $token, int $limit): void
    {
        if ($limit <= 0) {
            return;
        }

        $tokenKey = self::RATE_LIMIT_CACHE_PREFIX . hash('sha256', $token);
        $lockName = $tokenKey . ':lock';
        $cache = Craft::$app->getCache();
        $mutex = Craft::$app->getMutex();
        $headers = Craft::$app->getResponse()->getHeaders();
        $headers->remove('X-RateLimit-Remaining');
        $headers->remove('Retry-After');

        try {
            $mutexAcquired = $mutex->acquire($lockName, self::RATE_LIMIT_MUTEX_TIMEOUT);
        } catch (\Throwable $exception) {
            throw new HttpException(503, previous: $exception);
        }
        if (!$mutexAcquired) {
            throw new HttpException(503);
        }

        $decisionFailure = null;
        $releaseFailure = null;
        try {
            $now = $this->currentTimestamp();
            $window = intdiv($now, self::RATE_LIMIT_WINDOW_SECONDS);
            $retryAfter = self::RATE_LIMIT_WINDOW_SECONDS - ($now % self::RATE_LIMIT_WINDOW_SECONDS);
            $cacheKey = $tokenKey . ':' . $window;
            $headers->set('X-RateLimit-Limit', (string) $limit);
            $headers->set('X-RateLimit-Reset', (string) ($now + $retryAfter));

            try {
                $cachedCount = $cache->get($cacheKey);
            } catch (\Throwable $exception) {
                throw new HttpException(503, previous: $exception);
            }

            if ($cachedCount === false) {
                $count = 0;
            } elseif (is_int($cachedCount) && $cachedCount >= 0) {
                $count = $cachedCount;
            } else {
                throw new HttpException(503);
            }

            if ($count >= $limit) {
                $headers->set('X-RateLimit-Remaining', '0');
                $headers->set('Retry-After', (string) $retryAfter);

                throw new TooManyRequestsHttpException('API rate limit exceeded. Try again in a moment.');
            }

            try {
                $counterWritten = $cache->set($cacheKey, $count + 1, $retryAfter);
            } catch (\Throwable $exception) {
                throw new HttpException(503, previous: $exception);
            }
            if (!$counterWritten) {
                throw new HttpException(503);
            }

            $headers->set('X-RateLimit-Remaining', (string) ($limit - $count - 1));
        } catch (\Throwable $exception) {
            $decisionFailure = $exception;
        } finally {
            try {
                if (!$mutex->release($lockName)) {
                    $releaseFailure = new HttpException(503);
                }
            } catch (\Throwable $exception) {
                $releaseFailure = new HttpException(503, previous: $exception);
            }
        }
        if ($releaseFailure !== null) {
            throw $releaseFailure;
        }
        if ($decisionFailure !== null) {
            throw $decisionFailure;
        }
    }

    /**
     * Return the timestamp used for one rate-limit decision.
     */
    protected function currentTimestamp(): int
    {
        return time();
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

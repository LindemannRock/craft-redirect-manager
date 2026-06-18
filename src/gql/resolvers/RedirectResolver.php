<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\gql\resolvers;

use Craft;
use craft\db\Query;
use craft\gql\base\Resolver;
use craft\helpers\UrlHelper;
use GraphQL\Type\Definition\ResolveInfo;
use lindemannrock\base\helpers\GqlHelper;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;

/**
 * GraphQL resolver for Redirect Manager redirects.
 *
 * @since 5.33.0
 */
class RedirectResolver extends Resolver
{
    /**
     * Resolve a URI through Redirect Manager.
     *
     * @inheritdoc
     */
    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $siteId = self::resolveRequestedSiteId($arguments);
        if ($siteId === null) {
            return null;
        }

        $uri = trim((string)($arguments['uri'] ?? ''));
        if ($uri === '') {
            return null;
        }

        [$fullUrl, $pathOnly] = self::normalizeUri($uri, $siteId);
        $settings = RedirectManager::$plugin->getSettings();

        if ($settings->stripQueryString) {
            $fullUrlForMatching = self::stripQueryString($fullUrl);
            $pathOnlyForMatching = self::stripQueryString($pathOnly);
        } else {
            $fullUrlForMatching = $fullUrl;
            $pathOnlyForMatching = $pathOnly;
        }

        $pathOnlyStripped = self::stripSiteBasePath($pathOnlyForMatching, $siteId);
        $redirect = RedirectManager::$plugin->redirects->findRedirectForSite(
            $fullUrlForMatching,
            $pathOnlyStripped,
            $siteId,
        );

        if ($redirect === null && $pathOnlyStripped !== $pathOnlyForMatching) {
            $redirect = RedirectManager::$plugin->redirects->findRedirectForSite(
                $fullUrlForMatching,
                $pathOnlyForMatching,
                $siteId,
            );
        }

        RedirectManager::$plugin->analytics->record404($pathOnly, $redirect !== null, [
            'redirectId' => $redirect['id'] ?? null,
            'source' => 'graphql',
            'siteId' => $siteId,
        ]);

        if ($redirect === null) {
            return null;
        }

        $redirect = self::reloadRedirectForResponse($redirect);

        if (!empty($redirect['_captures'])) {
            $redirect['destinationUrl'] = RedirectManager::$plugin->matching->applyCaptures(
                $redirect['destinationUrl'],
                $redirect['_captures'],
            );
        }

        return $redirect;
    }

    /**
     * Reload the matched row after `findRedirectForSite()` increments hit stats.
     *
     * @param array<string, mixed> $redirect
     * @return array<string, mixed>
     */
    private static function reloadRedirectForResponse(array $redirect): array
    {
        $id = $redirect['id'] ?? null;
        if (!is_numeric($id)) {
            return $redirect;
        }

        $captures = $redirect['_captures'] ?? null;
        $fresh = (new Query())
            ->from(RedirectRecord::tableName())
            ->where(['id' => (int)$id])
            ->one();

        if (!is_array($fresh)) {
            return $redirect;
        }

        if (!empty($captures)) {
            $fresh['_captures'] = $captures;
        }

        return $fresh;
    }

    /**
     * List enabled redirects for the requested site.
     *
     * @inheritdoc
     */
    public static function resolveAll(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $siteId = self::resolveRequestedSiteId($arguments);
        if ($siteId === null) {
            return [];
        }

        return RedirectManager::$plugin->redirects->getEnabledRedirects($siteId);
    }

    /**
     * Resolve site arguments using the current site as fallback.
     *
     * @param array<string, mixed> $arguments
     * @return int|null
     */
    private static function resolveRequestedSiteId(array $arguments): ?int
    {
        return GqlHelper::resolveSiteId(
            $arguments,
            Craft::$app->getSites()->getCurrentSite()->id,
        );
    }

    /**
     * Normalize a GraphQL URI argument into full URL and path-only forms.
     *
     * @param string $uri
     * @param int $siteId
     * @return array{0: string, 1: string}
     */
    private static function normalizeUri(string $uri, int $siteId): array
    {
        if (UrlHelper::isAbsoluteUrl($uri)) {
            $fullUrl = $uri;
            $path = parse_url($uri, PHP_URL_PATH) ?: '/';
            $query = parse_url($uri, PHP_URL_QUERY);

            return [
                $fullUrl,
                $query === null || $query === '' ? $path : $path . '?' . $query,
            ];
        }

        $pathOnly = '/' . ltrim($uri, '/');

        return [
            UrlHelper::siteUrl(ltrim($pathOnly, '/'), null, null, $siteId),
            $pathOnly,
        ];
    }

    /**
     * Strip a query string from a URL or path.
     *
     * @param string $url
     * @return string
     */
    private static function stripQueryString(string $url): string
    {
        $position = strpos($url, '?');

        return $position === false ? $url : substr($url, 0, $position);
    }

    /**
     * Strip the requested site's base path from a path-only URL.
     *
     * @param string $pathOnly
     * @param int $siteId
     * @return string
     */
    private static function stripSiteBasePath(string $pathOnly, int $siteId): string
    {
        $site = Craft::$app->getSites()->getSiteById($siteId);
        if ($site === null) {
            return $pathOnly;
        }

        $siteBasePath = parse_url($site->getBaseUrl(), PHP_URL_PATH) ?: '';
        $siteBasePath = '/' . trim($siteBasePath, '/');

        if ($siteBasePath === '/' || !str_starts_with($pathOnly, $siteBasePath . '/')) {
            return $pathOnly;
        }

        return substr($pathOnly, strlen($siteBasePath));
    }
}

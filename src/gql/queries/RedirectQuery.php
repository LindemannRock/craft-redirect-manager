<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\gql\queries;

use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;
use lindemannrock\base\helpers\GqlHelper;
use lindemannrock\redirectmanager\gql\resolvers\RedirectResolver;
use lindemannrock\redirectmanager\gql\types\RedirectType;

/**
 * GraphQL queries for Redirect Manager.
 *
 * @since 5.33.0
 */
class RedirectQuery extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQuery('redirectManager.all')) {
            return [];
        }

        return [
            'redirectManagerResolveRedirect' => [
                'type' => RedirectType::getType(),
                'args' => [
                    'uri' => [
                        'name' => 'uri',
                        'type' => Type::nonNull(Type::string()),
                        'description' => 'The URI or absolute URL to resolve.',
                    ],
                    'site' => [
                        'name' => 'site',
                        'type' => Type::string(),
                        'description' => 'The site handle to resolve against.',
                    ],
                    'siteId' => [
                        'name' => 'siteId',
                        'type' => Type::int(),
                        'description' => 'The site ID to resolve against.',
                    ],
                ],
                'resolve' => RedirectResolver::class . '::resolve',
                'description' => 'Resolves a URI through Redirect Manager and records analytics like a real 404 lookup.',
            ],
            'redirectManagerRedirects' => [
                'type' => Type::listOf(RedirectType::getType()),
                'args' => [
                    'site' => [
                        'name' => 'site',
                        'type' => Type::string(),
                        'description' => 'The site handle to list redirects for.',
                    ],
                    'siteId' => [
                        'name' => 'siteId',
                        'type' => Type::int(),
                        'description' => 'The site ID to list redirects for.',
                    ],
                ],
                'resolve' => RedirectResolver::class . '::resolveAll',
                'description' => 'Lists enabled redirects for a site. This query is read-only.',
            ],
        ];
    }
}

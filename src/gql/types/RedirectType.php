<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\gql\types;

use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use lindemannrock\base\helpers\GqlHelper;

/**
 * GraphQL object type for redirect rows.
 *
 * @since 5.33.0
 */
class RedirectType extends ObjectType
{
    /**
     * Return the registered GraphQL type.
     *
     * @return Type
     */
    public static function getType(): Type
    {
        $typeName = self::getName();
        if ($type = GqlEntityRegistry::getEntity($typeName)) {
            return $type;
        }

        return GqlEntityRegistry::createEntity($typeName, new self([
            'name' => $typeName,
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'A Redirect Manager redirect.',
        ]));
    }

    /**
     * Return the GraphQL type name.
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'RedirectManagerRedirect';
    }

    /**
     * Return field definitions for redirect rows.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getFieldDefinitions(): array
    {
        return [
            'id' => [
                'name' => 'id',
                'type' => Type::int(),
                'description' => 'The redirect ID.',
            ],
            'site' => [
                'name' => 'site',
                'type' => Type::string(),
                'description' => 'The site handle, or null for global redirects.',
            ],
            'siteId' => [
                'name' => 'siteId',
                'type' => Type::int(),
                'description' => 'The site ID, or null for global redirects.',
            ],
            'sourceUrl' => [
                'name' => 'sourceUrl',
                'type' => Type::string(),
                'description' => 'The source URL pattern.',
            ],
            'sourceUrlParsed' => [
                'name' => 'sourceUrlParsed',
                'type' => Type::string(),
                'description' => 'The parsed source URL pattern used for matching.',
            ],
            'destinationUrl' => [
                'name' => 'destinationUrl',
                'type' => Type::string(),
                'description' => 'The redirect destination URL.',
            ],
            'redirectSrcMatch' => [
                'name' => 'redirectSrcMatch',
                'type' => Type::string(),
                'description' => 'Whether matching uses the path or full URL.',
            ],
            'matchType' => [
                'name' => 'matchType',
                'type' => Type::string(),
                'description' => 'The redirect match type.',
            ],
            'statusCode' => [
                'name' => 'statusCode',
                'type' => Type::int(),
                'description' => 'The HTTP status code.',
            ],
            'enabled' => [
                'name' => 'enabled',
                'type' => Type::boolean(),
                'description' => 'Whether the redirect is enabled.',
            ],
            'priority' => [
                'name' => 'priority',
                'type' => Type::int(),
                'description' => 'The redirect priority.',
            ],
            'creationType' => [
                'name' => 'creationType',
                'type' => Type::string(),
                'description' => 'How the redirect was created.',
            ],
            'sourcePlugin' => [
                'name' => 'sourcePlugin',
                'type' => Type::string(),
                'description' => 'The plugin that created the redirect.',
            ],
            'elementId' => [
                'name' => 'elementId',
                'type' => Type::int(),
                'description' => 'The related element ID, if any.',
            ],
            'hitCount' => [
                'name' => 'hitCount',
                'type' => Type::int(),
                'description' => 'The number of times this redirect has been hit.',
            ],
            'lastHit' => [
                'name' => 'lastHit',
                'type' => Type::string(),
                'description' => 'The last hit datetime.',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $fieldName = $resolveInfo->fieldName;

        if ($fieldName === 'site') {
            return GqlHelper::siteHandle(isset($source['siteId']) ? (int)$source['siteId'] : null);
        }

        if (is_array($source)) {
            return GqlHelper::nullIfEmptyString($source[$fieldName] ?? null);
        }

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }
}

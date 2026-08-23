<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Support;

use craft\console\Request;

/**
 * Isolated controller request with explicit body/query/method semantics.
 */
final class StubConsoleRequest extends Request
{
    /**
     * @param array<string, mixed> $bodyParams
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        private readonly array $bodyParams = [],
        private readonly array $queryParams = [],
        private readonly bool $post = true,
        private readonly bool $acceptsJson = true,
    ) {
        parent::__construct();
    }

    public function getBodyParam($name, $defaultValue = null): mixed
    {
        return $this->bodyParams[$name] ?? $defaultValue;
    }

    public function getQueryParam($name, $defaultValue = null): mixed
    {
        return $this->queryParams[$name] ?? $defaultValue;
    }

    public function getIsPost(): bool
    {
        return $this->post;
    }

    public function getAcceptsJson(): bool
    {
        return $this->acceptsJson;
    }

    public function getIsOptions(): bool
    {
        return false;
    }

    public function hasValidSiteToken(): bool
    {
        return false;
    }
}

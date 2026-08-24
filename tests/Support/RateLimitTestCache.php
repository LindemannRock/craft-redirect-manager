<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Support;

use Fiber;
use yii\caching\ArrayCache;

/**
 * Controllable cache boundary for rate-limit behavior tests.
 *
 * @since 5.41.0
 */
final class RateLimitTestCache extends ArrayCache
{
    public bool $throwOnRead = false;

    public bool $throwOnWrite = false;

    public bool $returnFalseOnWrite = false;

    /** @var list<string> */
    private array $readKeys = [];

    /** @var list<string> */
    private array $writeKeys = [];

    public function __construct(private int $suspendingReads = 0, array $config = [])
    {
        parent::__construct($config);
    }

    /** @return list<string> */
    public function readKeys(): array
    {
        return $this->readKeys;
    }

    /** @return list<string> */
    public function writeKeys(): array
    {
        return $this->writeKeys;
    }

    protected function getValue($key): mixed
    {
        $this->readKeys[] = (string)$key;
        if ($this->throwOnRead) {
            throw new \RuntimeException('Synthetic cache read failure.');
        }

        $value = parent::getValue($key);
        if ($this->suspendingReads > 0) {
            $this->suspendingReads--;
            Fiber::suspend();
        }

        return $value;
    }

    protected function setValue($key, $value, $duration): bool
    {
        $this->writeKeys[] = (string)$key;
        if ($this->throwOnWrite) {
            throw new \RuntimeException('Synthetic cache write failure.');
        }
        if ($this->returnFalseOnWrite) {
            return false;
        }

        return parent::setValue($key, $value, $duration);
    }
}

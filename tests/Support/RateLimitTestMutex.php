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
use yii\mutex\Mutex;

/**
 * Controllable mutex boundary for rate-limit behavior tests.
 *
 * @since 5.41.0
 */
final class RateLimitTestMutex extends Mutex
{
    public bool $throwOnAcquire = false;

    public bool $returnFalseOnAcquire = false;

    public bool $throwOnRelease = false;

    public bool $returnFalseOnRelease = false;

    public bool $waitForHeldLock = false;

    public ?\Closure $beforeAcquireCompletes = null;

    /** @var list<string> */
    private array $acquiredNames = [];

    /** @var list<string> */
    private array $releasedNames = [];

    private ?string $heldName = null;

    /** @var list<Fiber> */
    private array $waiters = [];

    /** @return list<string> */
    public function acquiredNames(): array
    {
        return $this->acquiredNames;
    }

    /** @return list<string> */
    public function releasedNames(): array
    {
        return $this->releasedNames;
    }

    public function isHeld(): bool
    {
        return $this->heldName !== null;
    }

    public function acquire($name, $timeout = 0): bool
    {
        $lockName = (string)$name;
        $this->acquiredNames[] = $lockName;
        if ($this->throwOnAcquire) {
            throw new \RuntimeException('Synthetic mutex acquisition failure.');
        }
        if ($this->returnFalseOnAcquire) {
            return false;
        }
        if ($this->beforeAcquireCompletes !== null) {
            ($this->beforeAcquireCompletes)();
        }
        if ($this->heldName === null) {
            $this->heldName = $lockName;

            return true;
        }

        $fiber = Fiber::getCurrent();
        if (!$this->waitForHeldLock || !$fiber instanceof Fiber || $this->heldName !== $lockName) {
            return false;
        }
        $this->waiters[] = $fiber;
        Fiber::suspend();

        return true;
    }

    public function release($name): bool
    {
        $lockName = (string)$name;
        $this->releasedNames[] = $lockName;
        if ($this->throwOnRelease) {
            throw new \RuntimeException('Synthetic mutex release failure.');
        }
        if ($this->returnFalseOnRelease || $this->heldName !== $lockName) {
            return false;
        }

        $waiter = array_shift($this->waiters);
        if ($waiter instanceof Fiber) {
            $waiter->resume();
        } else {
            $this->heldName = null;
        }

        return true;
    }

    protected function acquireLock($name, $timeout = 0): bool
    {
        return true;
    }

    protected function releaseLock($name): bool
    {
        return true;
    }
}

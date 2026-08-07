<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Support;

use yii\redis\ConnectionInterface;

/**
 * Exact in-memory Redis command boundary for cache integration tests.
 *
 * @since 5.41.0
 */
final class InMemoryRedisConnection implements ConnectionInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, int> */
    private array $expiresAt = [];

    /** @var array<string, array<string, true>> */
    private array $sets = [];

    /** @var array<string, int> */
    private array $commandCounts = [];

    /** @var array<int, array{name: string, params: array<int, mixed>}> */
    private array $commandLog = [];

    /** @var array<string, int> */
    private array $failOnFutureOccurrence = [];

    private int $inspectedMembers = 0;

    private bool $active = false;

    private bool $failCommands = false;

    public function open(): void
    {
        $this->active = true;
    }

    public function close(): void
    {
        $this->active = false;
    }

    public function getIsActive(): bool
    {
        return $this->active;
    }

    public function setFailCommands(bool $fail): void
    {
        $this->failCommands = $fail;
    }

    public function failOnFutureCommand(string $command, int $occurrence = 1): void
    {
        $this->failOnFutureOccurrence[strtoupper($command)] = max(1, $occurrence);
    }

    public function resetCommandAccounting(): void
    {
        $this->commandCounts = [];
        $this->commandLog = [];
        $this->inspectedMembers = 0;
    }

    public function totalCommandCount(): int
    {
        return count($this->commandLog);
    }

    public function commandCount(string $command): int
    {
        return $this->commandCounts[strtoupper($command)] ?? 0;
    }

    public function inspectedMemberCount(): int
    {
        return $this->inspectedMembers;
    }

    /** @return array<int, array{name: string, params: array<int, mixed>}> */
    public function commandLog(): array
    {
        return $this->commandLog;
    }

    /** @return array<int, string> */
    public function actualValueKeys(): array
    {
        foreach (array_keys($this->values) as $key) {
            $this->expireIfNeeded($key);
        }
        $keys = array_keys($this->values);
        sort($keys);

        return $keys;
    }

    public function actualValueCount(): int
    {
        return count($this->actualValueKeys());
    }

    public function deleteActualKey(string $key): void
    {
        unset($this->values[$key], $this->expiresAt[$key]);
    }

    public function expireActualKey(string $key): void
    {
        if ($this->hasActualKey($key)) {
            $this->expiresAt[$key] = time() - 1;
        }
    }

    /** @return array<int, string> */
    public function setMembers(string $key): array
    {
        $this->expireIfNeeded($key);
        $members = array_keys($this->sets[$key] ?? []);
        sort($members);

        return $members;
    }

    public function hasActualKey(string $key): bool
    {
        $this->expireIfNeeded($key);

        return array_key_exists($key, $this->values) || isset($this->sets[$key]);
    }

    public function executeCommand(string $name, array $params = []): mixed
    {
        $command = strtoupper($name);
        $this->commandCounts[$command] = ($this->commandCounts[$command] ?? 0) + 1;
        $this->commandLog[] = ['name' => $command, 'params' => $params];

        if ($this->failCommands) {
            throw new \RuntimeException('Synthetic Redis backend failure.');
        }

        if (isset($this->failOnFutureOccurrence[$command])) {
            $remaining = $this->failOnFutureOccurrence[$command] - 1;
            if ($remaining === 0) {
                unset($this->failOnFutureOccurrence[$command]);

                throw new \RuntimeException("Synthetic {$command} command failure.");
            }
            $this->failOnFutureOccurrence[$command] = $remaining;
        }

        return match ($command) {
            'GET' => $this->get((string)$params[0]),
            'SET' => $this->set($params),
            'EXISTS' => $this->hasActualKey((string)$params[0]) ? 1 : 0,
            'DEL' => $this->delete($params),
            'SADD' => $this->addSetMember((string)$params[0], (string)$params[1]),
            'SREM' => $this->removeSetMember((string)$params[0], (string)$params[1]),
            'SCARD' => count($this->setMembers((string)$params[0])),
            'SISMEMBER' => $this->isSetMember((string)$params[0], (string)$params[1]),
            'SRANDMEMBER' => $this->randomSetMember((string)$params[0]),
            'SSCAN' => $this->scanSet($params),
            'EXPIRE' => $this->expire((string)$params[0], (int)$params[1]),
            default => throw new \RuntimeException("Unsupported in-memory Redis command: {$command}"),
        };
    }

    private function get(string $key): mixed
    {
        $this->expireIfNeeded($key);

        return $this->values[$key] ?? null;
    }

    /** @param array<int, mixed> $params */
    private function set(array $params): string
    {
        $key = (string)$params[0];
        $this->values[$key] = $params[1] ?? null;
        if (($params[2] ?? null) === 'PX') {
            $this->expiresAt[$key] = time() + max(1, (int)ceil(((int)$params[3]) / 1000));
        } else {
            unset($this->expiresAt[$key]);
        }

        return 'OK';
    }

    /** @param array<int, mixed> $keys */
    private function delete(array $keys): int
    {
        $deleted = 0;
        foreach ($keys as $key) {
            $key = (string)$key;
            if ($this->hasActualKey($key)) {
                $deleted++;
            }
            unset($this->values[$key], $this->sets[$key], $this->expiresAt[$key]);
        }

        return $deleted;
    }

    private function addSetMember(string $key, string $member): int
    {
        $this->expireIfNeeded($key);
        $added = isset($this->sets[$key][$member]) ? 0 : 1;
        $this->sets[$key][$member] = true;

        return $added;
    }

    private function removeSetMember(string $key, string $member): int
    {
        $this->expireIfNeeded($key);
        $removed = isset($this->sets[$key][$member]) ? 1 : 0;
        unset($this->sets[$key][$member]);

        return $removed;
    }

    private function isSetMember(string $key, string $member): int
    {
        $this->expireIfNeeded($key);
        $this->inspectedMembers++;

        return isset($this->sets[$key][$member]) ? 1 : 0;
    }

    private function randomSetMember(string $key): string|false
    {
        $this->expireIfNeeded($key);
        $member = array_key_first($this->sets[$key] ?? []);
        if (!is_string($member)) {
            return false;
        }
        $this->inspectedMembers++;

        return $member;
    }

    /** @param array<int, mixed> $params */
    private function scanSet(array $params): array
    {
        $key = (string)$params[0];
        $cursor = max(0, (int)($params[1] ?? 0));
        $countIndex = array_search('COUNT', $params, true);
        $count = $countIndex === false ? 10 : max(1, (int)($params[$countIndex + 1] ?? 10));
        $this->expireIfNeeded($key);
        $members = array_keys($this->sets[$key] ?? []);
        $batch = array_slice($members, $cursor, $count);
        $nextCursor = $cursor + count($batch);
        if ($nextCursor >= count($members)) {
            $nextCursor = 0;
        }
        $this->inspectedMembers += count($batch);

        return [(string)$nextCursor, $batch];
    }

    private function expire(string $key, int $duration): int
    {
        if (!$this->hasActualKey($key)) {
            return 0;
        }
        $this->expiresAt[$key] = time() + max(1, $duration);

        return 1;
    }

    private function expireIfNeeded(string $key): void
    {
        if (isset($this->expiresAt[$key]) && $this->expiresAt[$key] <= time()) {
            unset($this->values[$key], $this->sets[$key], $this->expiresAt[$key]);
        }
    }
}

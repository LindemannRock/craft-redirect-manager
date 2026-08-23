<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Support;

use DOMDocument;
use DOMElement;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Loads and enforces the package's explicitly accepted test-suite inventory.
 *
 * @since 5.41.0
 */
final class AcceptedSuiteAuthority
{
    /** @return array{schemaVersion: int, declared: array{integrationClasses: int, testMethods: int}, executed: array{tests: int, assertions: int, errors: int, failures: int, skipped: int, incomplete: int}} */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Accepted suite authority is missing: {$path}");
        }
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read accepted suite authority: {$path}");
        }
        try {
            $baseline = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Accepted suite authority contains malformed JSON: ' . $exception->getMessage(), previous: $exception);
        }
        if (!is_array($baseline)
            || array_keys($baseline) !== ['schemaVersion', 'declared', 'executed']
            || $baseline['schemaVersion'] !== 1
            || !is_array($baseline['declared'] ?? null)
            || array_keys($baseline['declared']) !== ['integrationClasses', 'testMethods']
            || !is_array($baseline['executed'] ?? null)
            || array_keys($baseline['executed']) !== ['tests', 'assertions', 'errors', 'failures', 'skipped', 'incomplete']) {
            throw new RuntimeException('Accepted suite authority must use the complete schemaVersion 1 contract.');
        }
        foreach ([...array_values($baseline['declared']), ...array_values($baseline['executed'])] as $value) {
            if (!is_int($value) || $value < 0) {
                throw new RuntimeException('Accepted suite authority counts must be non-negative integers.');
            }
        }

        /** @var array{schemaVersion: int, declared: array{integrationClasses: int, testMethods: int}, executed: array{tests: int, assertions: int, errors: int, failures: int, skipped: int, incomplete: int}} $baseline */
        return $baseline;
    }

    /** @return array{integrationClasses: int, testMethods: int, violations: list<string>} */
    public static function inspectDeclared(string $testRoot, string $packageRoot): array
    {
        if (!is_dir($testRoot)) {
            throw new RuntimeException("Test root is missing: {$testRoot}");
        }
        $violations = [];
        $testMethods = 0;
        $integrationClasses = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            if (!is_string($source)) {
                throw new RuntimeException('Unable to read ' . $file->getPathname());
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($packageRoot) + 1));
            if (str_starts_with($relative, 'tests/Integration/') && str_ends_with($relative, 'Test.php')) {
                $integrationClasses++;
            }
            $tokens = token_get_all($source);
            foreach ($tokens as $index => $token) {
                if (!is_array($token) || !in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_FUNCTION], true)) {
                    continue;
                }
                for ($candidate = $index + 1, $count = count($tokens); $candidate < $count; $candidate++) {
                    $next = $tokens[$candidate];
                    if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    if (is_string($next) && $next === '&') {
                        continue;
                    }
                    if (!is_array($next) || $next[0] !== T_STRING) {
                        break;
                    }
                    $identifier = $next[1];
                    if ($token[0] === T_FUNCTION && str_starts_with($identifier, 'test')) {
                        $testMethods++;
                    }
                    $segments = strtolower((string)preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', $identifier));
                    if (preg_match('/(?:^|-)(?:audit|debt|amendment|smoke|batch|post\d+|pr\d+)(?:-|$)/', $segments) === 1) {
                        $violations[] = "{$relative}: {$identifier}";
                    }
                    break;
                }
            }
        }

        return ['integrationClasses' => $integrationClasses, 'testMethods' => $testMethods, 'violations' => $violations];
    }

    /**
     * @param array{integrationClasses: int, testMethods: int} $accepted
     * @param array{integrationClasses: int, testMethods: int, violations?: list<string>} $actual
     */
    public static function assertDeclared(array $accepted, array $actual): void
    {
        if (($actual['violations'] ?? []) !== []) {
            throw new RuntimeException("Test identifiers must describe behavior, not work history:\n" . implode("\n", $actual['violations']));
        }
        $observed = ['integrationClasses' => $actual['integrationClasses'], 'testMethods' => $actual['testMethods']];
        if ($observed !== $accepted) {
            throw new RuntimeException('Declared suite differs from the accepted authority: expected '
                . json_encode($accepted, JSON_THROW_ON_ERROR) . ', observed ' . json_encode($observed, JSON_THROW_ON_ERROR) . '.');
        }
    }

    /**
     * @param array{tests: int, assertions: int, errors: int, failures: int, skipped: int, incomplete: int} $accepted
     * @param array{tests: int, assertions: int, errors: int, failures: int, skipped: int, incomplete: int} $actual
     */
    public static function assertExecuted(array $accepted, array $actual): void
    {
        if ($actual !== $accepted) {
            throw new RuntimeException('Executed suite differs from the accepted authority: expected '
                . json_encode($accepted, JSON_THROW_ON_ERROR) . ', observed ' . json_encode($actual, JSON_THROW_ON_ERROR) . '.');
        }
    }

    /** @return array{tests: int, assertions: int, errors: int, failures: int, skipped: int, incomplete: int} */
    public static function readJUnitSummary(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("PHPUnit result authority is missing: {$path}");
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$document->load($path, LIBXML_NONET)) {
                throw new RuntimeException('PHPUnit result authority contains malformed XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $document->documentElement;
        if (!$root instanceof DOMElement || $root->tagName !== 'testsuites') {
            throw new RuntimeException('PHPUnit result authority is missing the testsuites summary.');
        }
        $suites = array_values(array_filter(
            iterator_to_array($root->childNodes),
            static fn(\DOMNode $node): bool => $node instanceof DOMElement && $node->tagName === 'testsuite',
        ));
        if ($suites === []) {
            throw new RuntimeException('PHPUnit result authority contains no testsuite summaries.');
        }
        $summary = [];
        foreach (['tests', 'assertions', 'errors', 'failures', 'skipped'] as $name) {
            $value = $root->getAttribute($name);
            if ($value === '') {
                $count = 0;
                foreach ($suites as $suite) {
                    $suiteValue = $suite->getAttribute($name);
                    if (preg_match('/^\d+$/', $suiteValue) !== 1) {
                        throw new RuntimeException("PHPUnit result authority has an invalid {$name} count.");
                    }
                    $count += (int)$suiteValue;
                }
                $summary[$name] = $count;
                continue;
            }
            if (preg_match('/^\d+$/', $value) !== 1) {
                throw new RuntimeException("PHPUnit result authority has an invalid {$name} count.");
            }
            $summary[$name] = (int)$value;
        }
        $summary['incomplete'] = 0;

        /** @var array{tests: int, assertions: int, errors: int, failures: int, skipped: int, incomplete: int} $summary */
        return $summary;
    }
}

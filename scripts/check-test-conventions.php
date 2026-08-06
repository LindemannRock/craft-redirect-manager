<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

$testRoot = dirname(__DIR__) . '/tests';
$violations = [];
$testMethods = 0;
$integrationClasses = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    if (!is_string($source)) {
        throw new RuntimeException('Unable to read ' . $file->getPathname());
    }
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(dirname(__DIR__)) + 1));
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

if ($integrationClasses < 30 || $testMethods < 130) {
    fwrite(STDERR, "Behavior coverage shrank below the accepted 30-class/130-test floor: {$integrationClasses} classes, {$testMethods} tests.\n");
    exit(1);
}
if ($violations !== []) {
    fwrite(STDERR, "Test identifiers must describe behavior, not work history:\n" . implode("\n", $violations) . "\n");
    exit(1);
}

fwrite(STDOUT, "Test conventions passed: {$integrationClasses} integration classes, {$testMethods} test methods.\n");

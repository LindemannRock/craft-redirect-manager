<?php
/**
 * PHPUnit bootstrap for Redirect Manager's disposable Craft project.
 *
 * @since 5.41.0
 */

declare(strict_types=1);

use lindemannrock\redirectmanager\tests\Support\TestProjectBoundary;

$autoloadCandidates = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
];
foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}
if (!class_exists(TestProjectBoundary::class)) {
    fwrite(STDERR, "Redirect Manager test autoload is unavailable.\n");
    exit(1);
}

$boundary = TestProjectBoundary::resolve();
require_once $boundary->baseBootstrap();
\lindemannrock\base\testing\bootstrap($boundary->projectRoot);

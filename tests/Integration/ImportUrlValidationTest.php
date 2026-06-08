<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\redirectmanager\controllers\ImportExportController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Import destination validation must reject executable schemes on top of its
 * protocol allowlist, while still accepting the contact/app protocols and regex
 * capture groups redirects legitimately use.
 *
 * @since 5.32.2
 */
#[CoversClass(ImportExportController::class)]
final class ImportUrlValidationTest extends TestCase
{
    private function isValidDestinationFormat(string $value): bool
    {
        $controller = new ImportExportController('import-export', RedirectManager::$plugin);
        $method = new \ReflectionMethod($controller, 'isValidDestinationFormat');

        return (bool) $method->invoke($controller, $value);
    }

    public function testRejectsExecutableSchemes(): void
    {
        self::assertFalse($this->isValidDestinationFormat('javascript:alert(1)'));
        self::assertFalse($this->isValidDestinationFormat('javascript://%0aalert(1)'));
        self::assertFalse($this->isValidDestinationFormat('data:text/html,<script>alert(1)</script>'));
        self::assertFalse($this->isValidDestinationFormat('vbscript:msgbox(1)'));
        self::assertFalse($this->isValidDestinationFormat('file:///etc/passwd'));
        self::assertFalse($this->isValidDestinationFormat("java\tscript:alert(1)"));
    }

    public function testAcceptsSupportedDestinations(): void
    {
        self::assertTrue($this->isValidDestinationFormat('https://example.com'));
        self::assertTrue($this->isValidDestinationFormat('/relative/path'));
        self::assertTrue($this->isValidDestinationFormat('mailto:x@y.com'));
        self::assertTrue($this->isValidDestinationFormat('tel:+15551234567'));
        self::assertTrue($this->isValidDestinationFormat('whatsapp:+15551234567'));
        // Regex capture group used by regex/wildcard redirects.
        self::assertTrue($this->isValidDestinationFormat('$1'));
    }

    public function testRejectsUnsupportedSchemes(): void
    {
        self::assertFalse($this->isValidDestinationFormat('ftp://example.com'));
        self::assertFalse($this->isValidDestinationFormat('not a url'));
        self::assertFalse($this->isValidDestinationFormat(''));
    }
}

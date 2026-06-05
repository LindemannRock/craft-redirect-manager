<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use craft\services\Elements;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionClass;
use ReflectionMethod;
use yii\base\Event;

/**
 * Pins plugin-level event registration switches.
 *
 * @since 5.32.0
 */
final class PluginEventRegistrationTest extends TestCase
{
    private bool $savedAutoCreateRedirects = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedAutoCreateRedirects = $this->settings()->autoCreateRedirects;
    }

    protected function tearDown(): void
    {
        $this->settings()->autoCreateRedirects = $this->savedAutoCreateRedirects;

        parent::tearDown();
    }

    public function testInstallEventListenersDoesNotRegisterEntryUriHandlersWhenAutoCreateRedirectsDisabled(): void
    {
        $this->settings()->autoCreateRedirects = false;

        $beforeSaveCount = $this->countClassEventHandlers(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT);
        $afterSaveCount = $this->countClassEventHandlers(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT);

        $reflection = new ReflectionMethod(RedirectManager::getInstance(), 'installEventListeners');
        $reflection->invoke(RedirectManager::getInstance());

        $this->assertSame(
            $beforeSaveCount,
            $this->countClassEventHandlers(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT),
        );
        $this->assertSame(
            $afterSaveCount,
            $this->countClassEventHandlers(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT),
        );
    }

    private function countClassEventHandlers(string $class, string $eventName): int
    {
        $reflection = new ReflectionClass(Event::class);
        $events = $reflection->getStaticProperties()['_events'] ?? [];

        return count($events[$eventName][$class] ?? []);
    }
}

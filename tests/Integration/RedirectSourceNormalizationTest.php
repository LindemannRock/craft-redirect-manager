<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use Craft;
use craft\errors\MissingComponentException;
use lindemannrock\redirectmanager\controllers\RedirectsController;
use lindemannrock\redirectmanager\events\RedirectEvent;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\Support\StubConsoleRequest;
use lindemannrock\redirectmanager\tests\Support\StubCpUser;
use lindemannrock\redirectmanager\tests\Support\StubEditableSites;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins source identity normalization across supported redirect producers.
 */
final class RedirectSourceNormalizationTest extends TestCase
{
    #[DataProvider('nonPatternMatchTypeProvider')]
    public function testControllerConvertsPathOnlyAbsoluteSourceBeforeSaving(string $matchType): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $token = bin2hex(random_bytes(4));
        $absoluteSource = "https://source.example.test/base/" . self::MARKER . "{$token}?campaign=summer#details";
        $expectedSource = "/base/" . self::MARKER . $token;

        $realSites = Craft::$app->getSites();
        Craft::$app->set('sites', new StubEditableSites($realSites, [$siteId]));
        Craft::$app->set('user', new StubCpUser(['redirectManager:createRedirects']));
        Craft::$app->set('request', new StubConsoleRequest([
            'sourceUrl' => $absoluteSource,
            'destinationUrl' => '/destination-' . $token,
            'redirectSrcMatch' => 'pathonly',
            'matchType' => $matchType,
            'statusCode' => 301,
            'enabled' => true,
            'priority' => 0,
            'siteId' => $siteId,
        ]));

        try {
            (new RedirectsController('redirects', RedirectManager::$plugin))->actionSave();
        } catch (MissingComponentException) {
            // The console fixture has no session. A successful controller save
            // reaches that production-only response boundary after persistence.
        }

        $record = RedirectRecord::find()
            ->where(['sourceUrl' => $expectedSource, 'siteId' => $siteId])
            ->one();
        self::assertInstanceOf(RedirectRecord::class, $record);
        self::assertSame($expectedSource, $record->sourceUrl);
        self::assertSame(strtolower($expectedSource), $record->sourceUrlParsed);
        self::assertSame($matchType, $record->matchType);
    }

    /** @return iterable<string, array{string}> */
    public static function nonPatternMatchTypeProvider(): iterable
    {
        yield 'exact source' => ['exact'];
        yield 'prefix source' => ['prefix'];
    }

    #[DataProvider('stableSourceProvider')]
    public function testServicePreservesPathAndPatternText(
        string $sourceUrl,
        string $matchType,
        string $expectedParsed,
    ): void {
        $id = $this->redirects->createRedirect([
            'sourceUrl' => $sourceUrl,
            'destinationUrl' => '/stable-destination-' . bin2hex(random_bytes(4)),
            'redirectSrcMatch' => 'pathonly',
            'matchType' => $matchType,
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
        ]);

        self::assertIsInt($id);
        $record = RedirectRecord::findOne($id);
        self::assertInstanceOf(RedirectRecord::class, $record);
        self::assertSame($sourceUrl, $record->sourceUrl);
        self::assertSame($expectedParsed, $record->sourceUrlParsed);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function stableSourceProvider(): iterable
    {
        yield 'existing path' => ['/Existing//Path', 'exact', '/existing/path'];
        yield 'regex pattern' => ['^/Products//([A-Z]+)$', 'regex', '^/Products//([A-Z]+)$'];
        yield 'wildcard pattern' => ['/Products//*', 'wildcard', '/Products//*'];
    }

    public function testFullUrlModeRetainsTheCompleteSource(): void
    {
        $sourceUrl = 'https://full.example.test/Base/Path?campaign=summer#details';
        $id = $this->redirects->createRedirect([
            'sourceUrl' => $sourceUrl,
            'sourceUrlParsed' => '/caller-supplied-identity',
            'destinationUrl' => '/full-destination',
            'redirectSrcMatch' => 'fullurl',
            'matchType' => 'exact',
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
        ]);

        self::assertIsInt($id);
        $record = RedirectRecord::findOne($id);
        self::assertInstanceOf(RedirectRecord::class, $record);
        self::assertSame($sourceUrl, $record->sourceUrl);
        self::assertSame(strtolower($sourceUrl), $record->sourceUrlParsed);
    }

    public function testUpdateNormalizesAbsoluteSourceBeforeValidationAndPersistence(): void
    {
        $record = $this->seedRedirect();
        $sourceUrl = 'https://update.example.test/base/' . self::MARKER . 'updated?query=discarded#fragment';

        self::assertTrue($this->redirects->updateRedirect((int)$record->id, [
            'sourceUrl' => $sourceUrl,
            'redirectSrcMatch' => 'pathonly',
            'matchType' => 'prefix',
        ], $record));

        $persisted = RedirectRecord::findOne((int)$record->id);
        self::assertInstanceOf(RedirectRecord::class, $persisted);
        self::assertSame('/base/' . self::MARKER . 'updated', $persisted->sourceUrl);
        self::assertSame('/base/' . self::MARKER . 'updated', $persisted->sourceUrlParsed);
        self::assertSame('prefix', $persisted->matchType);
    }

    public function testValidationFailureRetainsNormalizedSourceForFormRepopulation(): void
    {
        $record = new RedirectRecord();
        $record->sourceUrl = 'https://form.example.test/base/old?drop=yes#drop';
        $record->destinationUrl = 'javascript:alert(1)';
        $record->redirectSrcMatch = 'pathonly';
        $record->matchType = 'exact';

        self::assertFalse($record->validate());
        self::assertSame('/base/old', $record->sourceUrl);
        self::assertSame('/base/old', $record->sourceUrlParsed);
        self::assertTrue($record->hasErrors('destinationUrl'));
        self::assertFalse($record->hasErrors('sourceUrl'));
    }

    public function testDuplicateCheckUsesNormalizedPathIdentity(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $path = '/' . self::MARKER . 'duplicate-' . bin2hex(random_bytes(4));
        $this->seedRedirect(['sourceUrl' => $path, 'sourceUrlParsed' => $path, 'siteId' => $siteId]);
        $service = new SourceNotificationRecordingRedirectsService();
        $beforeSource = null;
        $handler = static function(RedirectEvent $event) use (&$beforeSource): void {
            $redirect = $event->redirect;
            $beforeSource = $redirect['sourceUrl'];
        };
        $service->on($service::EVENT_BEFORE_SAVE_REDIRECT, $handler);

        try {
            $result = $service->createRedirect([
                'sourceUrl' => 'https://duplicate.example.test' . $path . '?drop=yes',
                'destinationUrl' => '/duplicate-destination',
                'redirectSrcMatch' => 'pathonly',
                'matchType' => 'exact',
                'siteId' => $siteId,
            ]);
        } finally {
            $service->off($service::EVENT_BEFORE_SAVE_REDIRECT, $handler);
        }

        self::assertFalse($result);
        self::assertSame($path, $beforeSource);
        self::assertCount(1, $service->notices);
        self::assertSame(1, $this->countRows(RedirectRecord::tableName(), ['sourceUrlParsed' => strtolower($path)]));
    }

    public function testLoopCheckUsesNormalizedPathIdentityBeforeEvents(): void
    {
        $path = '/' . self::MARKER . 'loop-' . bin2hex(random_bytes(4));
        $beforeEvents = 0;
        $handler = static function() use (&$beforeEvents): void {
            $beforeEvents++;
        };
        $service = new SourceNotificationRecordingRedirectsService();
        $service->on($service::EVENT_BEFORE_SAVE_REDIRECT, $handler);

        $result = $service->createRedirect([
            'sourceUrl' => 'https://loop.example.test' . $path . '?drop=yes#drop',
            'destinationUrl' => $path,
            'redirectSrcMatch' => 'pathonly',
            'matchType' => 'exact',
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
        ]);

        self::assertFalse($result);
        self::assertSame(0, $beforeEvents);
        self::assertCount(1, $service->errors);
        self::assertSame(0, $this->countRows(RedirectRecord::tableName(), ['sourceUrlParsed' => strtolower($path)]));
    }
}

/** Records service notifications without requiring a web session. */
final class SourceNotificationRecordingRedirectsService extends \lindemannrock\redirectmanager\services\RedirectsService
{
    /** @var list<string> */
    public array $notices = [];
    /** @var list<string> */
    public array $errors = [];

    protected function notifyUser(string $type, string $message): void
    {
        if ($type === 'error') {
            $this->errors[] = $message;
            return;
        }

        $this->notices[] = $message;
    }
}

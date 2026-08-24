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
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\services\Elements;
use lindemannrock\redirectmanager\events\RedirectEvent;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\services\LocalCacheService;
use lindemannrock\redirectmanager\services\RedirectsService;
use lindemannrock\redirectmanager\tests\TestCase;
use ReflectionProperty;

/**
 * Pins automatic redirect creation to the changed element's site and URL mode.
 */
final class AutomaticElementRedirectTest extends TestCase
{
    public function testFullUrlModeUsesExplicitElementSiteUrls(): void
    {
        $site = Craft::$app->getSites()->getAllSites()[1];
        $siteId = (int)$site->id;
        $elementId = random_int(100000, 999999);
        $token = bin2hex(random_bytes(4));
        $oldUri = 'catalog/' . self::MARKER . $token . '-old';
        $newUri = 'catalog/' . self::MARKER . $token . '-new';
        $oldUrl = 'https://explicit-site.example.test/base/' . $oldUri;
        $newUrl = 'https://explicit-site.example.test/base/' . $newUri;
        $oldElement = $this->element($elementId, $siteId, $oldUri, $oldUrl);
        $newElement = $this->element($elementId, $siteId, $newUri, $newUrl);

        $elements = $this->createMock(Elements::class);
        $elements->expects(self::once())
            ->method('getElementById')
            ->with($elementId, $newElement::class, $siteId)
            ->willReturn($oldElement);
        Craft::$app->set('elements', $elements);

        $this->settings()->autoCreateRedirects = true;
        $this->settings()->redirectSrcMatch = 'fullurl';
        $service = new NotificationRecordingRedirectsService();
        $cache = new class() extends LocalCacheService {
            public int $invalidations = 0;

            public function invalidateRedirectMutation(?int $oldSiteId, ?int $newSiteId): bool
            {
                $this->invalidations++;
                return true;
            }
        };
        $this->replacePluginComponent('localCache', $cache);
        $beforeEvents = 0;
        $afterEvents = 0;
        $service->on($service::EVENT_BEFORE_SAVE_REDIRECT, static function() use (&$beforeEvents): void {
            $beforeEvents++;
        });
        $service->on($service::EVENT_AFTER_SAVE_REDIRECT, static function() use (&$afterEvents): void {
            $afterEvents++;
        });

        $service->stashElementUri($newElement);
        $service->handleElementUriChange($newElement);

        $record = RedirectRecord::find()
            ->where([
                'elementId' => $elementId,
                'siteId' => $siteId,
                'creationType' => 'entry-change',
            ])
            ->one();
        self::assertInstanceOf(RedirectRecord::class, $record);
        self::assertSame($oldUrl, $record->sourceUrl);
        self::assertSame(strtolower($oldUrl), $record->sourceUrlParsed);
        self::assertSame($newUrl, $record->destinationUrl);
        self::assertSame('fullurl', $record->redirectSrcMatch);
        self::assertTrue($service->notificationRequested);
        self::assertSame(1, $beforeEvents);
        self::assertSame(1, $afterEvents);
        self::assertSame(1, $cache->invalidations);
        self::assertCount(1, $service->notices);
        self::assertSame(0, $this->stashedCount($service));
    }

    public function testPathOnlyModeUsesUriShapedValues(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $elementId = random_int(100000, 999999);
        $old = $this->element($elementId, $siteId, 'old/path', 'https://ignored.example/base/old/path');
        $new = $this->element($elementId, $siteId, 'new/path', 'https://ignored.example/base/new/path');
        $this->replaceElements($new, $old);
        $this->settings()->redirectSrcMatch = 'pathonly';
        $service = new NotificationRecordingRedirectsService();

        $service->stashElementUri($new);
        $service->handleElementUriChange($new);

        $record = RedirectRecord::find()->where(['elementId' => $elementId, 'siteId' => $siteId])->one();
        self::assertInstanceOf(RedirectRecord::class, $record);
        self::assertSame('/old/path', $record->sourceUrl);
        self::assertSame('/new/path', $record->destinationUrl);
        self::assertSame('pathonly', $record->redirectSrcMatch);
        self::assertSame(0, $this->stashedCount($service));
    }

    public function testMultipleSitesKeepSeparateUrlOwnership(): void
    {
        $sites = array_slice(Craft::$app->getSites()->getAllSites(), 0, 2);
        $elementId = random_int(100000, 999999);
        $service = new NotificationRecordingRedirectsService();
        $this->settings()->redirectSrcMatch = 'fullurl';
        $oldBySite = [];
        $newBySite = [];
        foreach ($sites as $index => $site) {
            $siteId = (int)$site->id;
            $oldBySite[$siteId] = $this->element($elementId, $siteId, "old-{$index}", "https://site-{$index}.example.test/base/old-{$index}");
            $newBySite[$siteId] = $this->element($elementId, $siteId, "new-{$index}", "https://site-{$index}.example.test/base/new-{$index}");
        }
        $elements = $this->createMock(Elements::class);
        $elements->method('getElementById')
            ->willReturnCallback(static fn(int $id, ?string $type, int $siteId): ElementInterface => $oldBySite[$siteId]);
        Craft::$app->set('elements', $elements);

        foreach ($newBySite as $newElement) {
            $service->stashElementUri($newElement);
            $service->handleElementUriChange($newElement);
        }

        foreach ($sites as $index => $site) {
            $record = RedirectRecord::find()->where(['elementId' => $elementId, 'siteId' => (int)$site->id])->one();
            self::assertInstanceOf(RedirectRecord::class, $record);
            self::assertSame("https://site-{$index}.example.test/base/old-{$index}", $record->sourceUrl);
            self::assertSame("https://site-{$index}.example.test/base/new-{$index}", $record->destinationUrl);
        }
        self::assertSame(0, $this->stashedCount($service));
    }

    public function testForwardProgressionRetainsEarlierRedirects(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $elementId = random_int(100000, 999999);
        $service = new NotificationRecordingRedirectsService();
        $this->settings()->redirectSrcMatch = 'pathonly';
        $oldA = $this->element($elementId, $siteId, 'a', 'https://site.example/a');
        $newB = $this->element($elementId, $siteId, 'b', 'https://site.example/b');
        $oldB = $this->element($elementId, $siteId, 'b', 'https://site.example/b');
        $newC = $this->element($elementId, $siteId, 'c', 'https://site.example/c');
        $elements = $this->createMock(Elements::class);
        $elements->expects(self::exactly(2))->method('getElementById')->willReturnOnConsecutiveCalls($oldA, $oldB);
        Craft::$app->set('elements', $elements);

        $service->stashElementUri($newB);
        $service->handleElementUriChange($newB);
        $service->stashElementUri($newC);
        $service->handleElementUriChange($newC);

        $rows = RedirectRecord::find()->where(['elementId' => $elementId, 'siteId' => $siteId])->orderBy(['id' => SORT_ASC])->all();
        self::assertCount(2, $rows);
        self::assertSame(['/a', '/b'], array_column($rows, 'sourceUrl'));
        self::assertSame(['/b', '/c'], array_column($rows, 'destinationUrl'));
        self::assertSame(0, $this->stashedCount($service));
    }

    public function testImmediateUndoRemovesReverseRedirectWithoutReplacement(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $elementId = random_int(100000, 999999);
        $this->seedRedirect([
            'sourceUrl' => '/a',
            'sourceUrlParsed' => '/a',
            'destinationUrl' => '/b',
            'siteId' => $siteId,
            'creationType' => 'entry-change',
            'elementId' => $elementId,
        ]);
        $old = $this->element($elementId, $siteId, 'b', 'https://site.example/b');
        $new = $this->element($elementId, $siteId, 'a', 'https://site.example/a');
        $this->replaceElements($new, $old);
        $service = new NotificationRecordingRedirectsService();
        $this->settings()->redirectSrcMatch = 'pathonly';

        $service->stashElementUri($new);
        $service->handleElementUriChange($new);

        self::assertSame(0, $this->countRows(RedirectRecord::tableName(), ['elementId' => $elementId, 'siteId' => $siteId]));
        self::assertCount(1, $service->notices);
        self::assertSame(0, $this->stashedCount($service));
    }

    public function testBackwardProgressionReplacesTheOwnedChain(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $elementId = random_int(100000, 999999);
        foreach ([['/a', '/b'], ['/b', '/c']] as [$source, $destination]) {
            $this->seedRedirect([
                'sourceUrl' => $source,
                'sourceUrlParsed' => $source,
                'destinationUrl' => $destination,
                'siteId' => $siteId,
                'creationType' => 'entry-change',
                'elementId' => $elementId,
            ]);
        }
        $old = $this->element($elementId, $siteId, 'c', 'https://site.example/c');
        $new = $this->element($elementId, $siteId, 'a', 'https://site.example/a');
        $this->replaceElements($new, $old);
        $service = new NotificationRecordingRedirectsService();
        $this->settings()->redirectSrcMatch = 'pathonly';

        $service->stashElementUri($new);
        $service->handleElementUriChange($new);

        /** @var list<RedirectRecord> $rows */
        $rows = RedirectRecord::find()->where(['elementId' => $elementId, 'siteId' => $siteId])->all();
        self::assertCount(1, $rows);
        self::assertSame('/c', $rows[0]->sourceUrl);
        self::assertSame('/a', $rows[0]->destinationUrl);
        self::assertCount(2, $service->notices);
        self::assertSame(0, $this->stashedCount($service));
    }

    public function testFailedCreationHasNoCommittedMutationSideEffects(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $elementId = random_int(100000, 999999);
        $old = $this->element($elementId, $siteId, 'old', 'https://site.example/old');
        $new = $this->element($elementId, $siteId, 'new', 'https://site.example/new');
        $this->replaceElements($new, $old);
        $cache = new class() extends LocalCacheService {
            public int $invalidations = 0;

            public function invalidateRedirectMutation(?int $oldSiteId, ?int $newSiteId): bool
            {
                $this->invalidations++;
                return true;
            }
        };
        $this->replacePluginComponent('localCache', $cache);
        $service = new NotificationRecordingRedirectsService();
        $afterEvents = 0;
        $service->on($service::EVENT_BEFORE_SAVE_REDIRECT, static function(RedirectEvent $event): void {
            $event->isValid = false;
        });
        $service->on($service::EVENT_AFTER_SAVE_REDIRECT, static function() use (&$afterEvents): void {
            $afterEvents++;
        });

        $service->stashElementUri($new);
        $service->handleElementUriChange($new);

        self::assertSame(0, $this->countRows(RedirectRecord::tableName(), ['elementId' => $elementId, 'siteId' => $siteId]));
        self::assertSame(0, $cache->invalidations);
        self::assertSame(0, $afterEvents);
        self::assertCount(0, $service->notices);
        self::assertSame(0, $this->stashedCount($service));
    }

    public function testUnchangedAndNonRoutableChangesLeaveNoStashedState(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $elementId = random_int(100000, 999999);
        $service = new NotificationRecordingRedirectsService();
        $this->settings()->redirectSrcMatch = 'fullurl';
        $old = $this->element($elementId, $siteId, 'same', 'https://site.example/same');
        $unchanged = $this->element($elementId, $siteId, 'same', 'https://site.example/same');
        $nonRoutable = $this->element($elementId, $siteId, 'changed', null);
        $elements = $this->createMock(Elements::class);
        $elements->expects(self::exactly(2))->method('getElementById')->willReturn($old);
        Craft::$app->set('elements', $elements);

        $service->stashElementUri($unchanged);
        $service->handleElementUriChange($unchanged);
        $service->stashElementUri($nonRoutable);
        $service->handleElementUriChange($nonRoutable);

        self::assertSame(0, $this->countRows(RedirectRecord::tableName(), ['elementId' => $elementId]));
        self::assertSame(0, $this->stashedCount($service));
    }

    public function testMissingDraftAndRevisionElementsAreIgnored(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $elementId = random_int(100000, 999999);
        $service = new NotificationRecordingRedirectsService();
        $missing = $this->element($elementId, $siteId, 'missing', 'https://site.example/missing');
        $draft = $this->element($elementId + 1, $siteId, 'draft', 'https://site.example/draft');
        $draft->draftId = 1;
        $revision = $this->element($elementId + 2, $siteId, 'revision', 'https://site.example/revision');
        $revision->revisionId = 1;
        $elements = $this->createMock(Elements::class);
        $elements->expects(self::once())->method('getElementById')->willReturn(null);
        Craft::$app->set('elements', $elements);

        $service->stashElementUri($missing);
        $service->handleElementUriChange($missing);
        $service->stashElementUri($draft);
        $service->handleElementUriChange($draft);
        $service->stashElementUri($revision);
        $service->handleElementUriChange($revision);

        self::assertSame(0, $this->stashedCount($service));
        self::assertSame(0, $this->countRows(RedirectRecord::tableName(), ['elementId' => [$elementId, $elementId + 1, $elementId + 2]]));
    }

    public function testDuplicateAutomaticSourceIsRejectedWithoutCommittedSideEffects(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $elementId = random_int(100000, 999999);
        $this->seedRedirect([
            'sourceUrl' => '/old',
            'sourceUrlParsed' => '/old',
            'destinationUrl' => '/existing-destination',
            'siteId' => $siteId,
        ]);
        $old = $this->element($elementId, $siteId, 'old', 'https://site.example/old');
        $new = $this->element($elementId, $siteId, 'new', 'https://site.example/new');
        $this->replaceElements($new, $old);
        $cache = new class() extends LocalCacheService {
            public int $invalidations = 0;

            public function invalidateRedirectMutation(?int $oldSiteId, ?int $newSiteId): bool
            {
                $this->invalidations++;
                return true;
            }
        };
        $this->replacePluginComponent('localCache', $cache);
        $service = new NotificationRecordingRedirectsService();
        $afterEvents = 0;
        $service->on($service::EVENT_AFTER_SAVE_REDIRECT, static function() use (&$afterEvents): void {
            $afterEvents++;
        });

        $service->stashElementUri($new);
        $service->handleElementUriChange($new);

        self::assertSame(1, $this->countRows(RedirectRecord::tableName(), ['sourceUrlParsed' => '/old', 'siteId' => $siteId]));
        self::assertSame(0, $this->countRows(RedirectRecord::tableName(), ['elementId' => $elementId]));
        self::assertSame(0, $cache->invalidations);
        self::assertSame(0, $afterEvents);
        self::assertCount(1, $service->notices);
        self::assertSame(0, $this->stashedCount($service));
    }

    public function testAutomaticLoopIsRejectedWithoutCreatingARow(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $elementId = random_int(100000, 999999);
        $this->seedRedirect([
            'sourceUrl' => '/new',
            'sourceUrlParsed' => '/new',
            'destinationUrl' => '/old',
            'siteId' => $siteId,
            'creationType' => 'manual',
        ]);
        $old = $this->element($elementId, $siteId, 'old', 'https://site.example/old');
        $new = $this->element($elementId, $siteId, 'new', 'https://site.example/new');
        $this->replaceElements($new, $old);
        $service = new NotificationRecordingRedirectsService();

        $service->stashElementUri($new);
        $service->handleElementUriChange($new);

        self::assertSame(0, $this->countRows(RedirectRecord::tableName(), ['elementId' => $elementId]));
        self::assertCount(1, $service->errors);
        self::assertCount(0, $service->notices);
        self::assertSame(0, $this->stashedCount($service));
    }

    private function element(int $id, int $siteId, string $uri, ?string $url): Entry
    {
        $element = new class($url) extends Entry {
            public function __construct(private readonly ?string $explicitUrl)
            {
                parent::__construct();
            }

            public function getUrl(): ?string
            {
                return $this->explicitUrl;
            }
        };
        $element->id = $id;
        $element->siteId = $siteId;
        $element->uri = $uri;

        return $element;
    }

    private function replaceElements(ElementInterface $current, ElementInterface $old): void
    {
        $elements = $this->createMock(Elements::class);
        $elements->expects(self::once())
            ->method('getElementById')
            ->with($current->id, $current::class, $current->siteId)
            ->willReturn($old);
        Craft::$app->set('elements', $elements);
    }

    private function stashedCount(RedirectsService $service): int
    {
        $property = new ReflectionProperty(RedirectsService::class, '_stashedUris');
        /** @var array<string, mixed> $stashed */
        $stashed = $property->getValue($service);

        return count($stashed);
    }
}

/**
 * Keeps the production notification request observable without requiring a web
 * session in the disposable console application.
 */
final class NotificationRecordingRedirectsService extends RedirectsService
{
    public bool $notificationRequested = false;
    /** @var list<string> */
    public array $notices = [];
    /** @var list<string> */
    public array $errors = [];

    public function createRedirect(array $attributes, bool $showNotification = false): int|false
    {
        $this->notificationRequested = $showNotification;

        return parent::createRedirect($attributes, $showNotification);
    }

    protected function notifyUser(string $type, string $message): void
    {
        if ($type === 'error') {
            $this->errors[] = $message;
            return;
        }

        $this->notices[] = $message;
    }
}

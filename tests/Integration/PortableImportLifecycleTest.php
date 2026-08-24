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
use craft\db\Query;
use craft\web\Response;
use lindemannrock\redirectmanager\controllers\ImportExportController;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\RedirectsService;
use lindemannrock\redirectmanager\tests\Support\StubConsoleRequest;
use lindemannrock\redirectmanager\tests\Support\StubCpUser;
use lindemannrock\redirectmanager\tests\Support\StubEditableSites;
use lindemannrock\redirectmanager\tests\TestCase;

/**
 * Pins portable CSV preview and import behavior to persisted redirect identity.
 *
 * @since 5.41.0
 */
final class PortableImportLifecycleTest extends TestCase
{
    public function testPreviewSeparatesGlobalAndSiteSpecificIdentityWhileRejectingSameFileCollisions(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        self::assertGreaterThanOrEqual(2, count($sites));
        $firstSiteId = (int)$sites[0]->id;
        $secondSiteId = (int)$sites[1]->id;
        $source = '/' . self::MARKER . 'portable-scope-' . bin2hex(random_bytes(4));

        $validated = $this->preview([
            [$source, '/global', '', 'exact', 'pathonly'],
            [$source, '/site-one', (string)$firstSiteId, 'prefix', 'pathonly'],
            [$source, '/site-two', (string)$secondSiteId, 'exact', 'pathonly'],
            ['https://portable.example.test' . strtoupper($source) . '?drop=yes#drop', '/duplicate', '', 'prefix', 'pathonly'],
        ], [
            0 => 'sourceUrl',
            1 => 'destinationUrl',
            2 => 'siteId',
            3 => 'matchType',
            4 => 'redirectSrcMatch',
        ]);

        self::assertCount(3, $validated['validRows']);
        self::assertCount(1, $validated['duplicateRows']);
        self::assertCount(0, $validated['errorRows']);
    }

    public function testPreviewUsesCanonicalDatabaseIdentityAcrossLabelsAndSites(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        self::assertGreaterThanOrEqual(2, count($sites));
        $firstSiteId = (int)$sites[0]->id;
        $secondSiteId = (int)$sites[1]->id;
        $source = '/' . self::MARKER . 'portable-existing-' . bin2hex(random_bytes(4));
        $this->seedRedirect([
            'sourceUrl' => $source,
            'siteId' => $firstSiteId,
            'matchType' => 'exact',
            'redirectSrcMatch' => 'pathonly',
        ]);

        $validated = $this->preview([
            ['https://portable.example.test' . strtoupper($source), '/same-site', (string)$firstSiteId, 'prefix', 'pathonly'],
            [$source, '/other-site', (string)$secondSiteId, 'exact', 'pathonly'],
        ], [
            0 => 'sourceUrl',
            1 => 'destinationUrl',
            2 => 'siteId',
            3 => 'matchType',
            4 => 'redirectSrcMatch',
        ]);

        self::assertCount(1, $validated['validRows']);
        self::assertSame($secondSiteId, $validated['validRows'][0]['siteId']);
        self::assertCount(1, $validated['duplicateRows']);
    }

    public function testUnknownBehavioralFieldsAndInvalidPrioritiesAreRowErrors(): void
    {
        $prefix = '/' . self::MARKER . 'portable-fields-' . bin2hex(random_bytes(4));
        $validated = $this->preview([
            [$prefix . '-alias', '/valid', 'ReGeXp', 'FULL URL', '9'],
            [$prefix . '-canonical', '/valid-zero', 'Prefix Match', 'Path Only', '0'],
            [$prefix . '-blank', '/blank', '', '', ''],
            [$prefix . '-unknown-match', '/invalid', 'similar', 'pathonly', '0'],
            [$prefix . '-unknown-mode', '/invalid', 'exact', 'domain', '0'],
            [$prefix . '-negative', '/invalid', 'exact', 'pathonly', '-1'],
            [$prefix . '-too-high', '/invalid', 'exact', 'pathonly', '10'],
            [$prefix . '-text', '/invalid', 'exact', 'pathonly', 'urgent'],
        ], [
            0 => 'sourceUrl',
            1 => 'destinationUrl',
            2 => 'matchType',
            3 => 'redirectSrcMatch',
            4 => 'priority',
        ]);

        self::assertCount(3, $validated['validRows']);
        self::assertCount(5, $validated['errorRows']);
        self::assertSame(['regex', 'prefix', 'exact'], array_column($validated['validRows'], 'matchType'));
        self::assertSame(['fullurl', 'pathonly', 'pathonly'], array_column($validated['validRows'], 'redirectSrcMatch'));
        self::assertSame([9, 0, 0], array_column($validated['validRows'], 'priority'));
        self::assertSame([5, 6, 7, 8, 9], array_column($validated['errorRows'], 'rowNumber'));
    }

    public function testOmittedBehavioralFieldsUseDocumentedDefaults(): void
    {
        $validated = $this->preview([
            ['/' . self::MARKER . 'portable-defaults-' . bin2hex(random_bytes(4)), '/destination'],
        ], [
            0 => 'sourceUrl',
            1 => 'destinationUrl',
        ]);

        self::assertCount(1, $validated['validRows']);
        self::assertSame('exact', $validated['validRows'][0]['matchType']);
        self::assertSame('pathonly', $validated['validRows'][0]['redirectSrcMatch']);
        self::assertSame(0, $validated['validRows'][0]['priority']);
    }

    public function testFinalImportUsesPreviewIdentityAndOwnsEveryPortableRow(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $token = bin2hex(random_bytes(4));
        $rows = [
            ["https://portable.example.test/MiXeD/{$token}?drop=yes#drop", '/exact', (string)$siteId, 'exact', 'pathonly', 'manual', 'redirect-manager', ''],
            ["/PREFIX/{$token}", '/prefix', (string)$siteId, 'prefix', 'pathonly', 'entry-change', 'smartlink-manager', '456'],
            ["^/Regex/{$token}/([A-Z]+)$", '/regex/$1', (string)$siteId, 'regex', 'pathonly', 'integration', 'shortlink-manager', '789'],
            ["/Wildcard/{$token}/*", '/wildcard/$1', (string)$siteId, 'wildcard', 'pathonly', 'integration', 'smartlink-manager', '999'],
        ];
        $mapping = [
            0 => 'sourceUrl',
            1 => 'destinationUrl',
            2 => 'siteId',
            3 => 'matchType',
            4 => 'redirectSrcMatch',
            5 => 'creationType',
            6 => 'sourcePlugin',
            7 => 'elementId',
        ];
        [$controller, $session, $validated] = $this->previewLifecycle($rows, $mapping);
        self::assertCount(4, $validated['validRows']);
        self::assertSame(['manual', 'manual', 'manual', 'manual'], array_column($validated['validRows'], 'creationType'));
        self::assertSame(['redirect-manager', 'redirect-manager', 'redirect-manager', 'redirect-manager'], array_column($validated['validRows'], 'sourcePlugin'));
        self::assertSame([null, null, null, null], array_column($validated['validRows'], 'elementId'));

        $redirects = new ImportCacheRecordingRedirectsService();
        $this->replacePluginComponent('redirects', $redirects);
        $controller->actionImport();

        $persisted = (new Query())
            ->from(RedirectRecord::tableName())
            ->where(['siteId' => $siteId])
            ->orderBy(['id' => SORT_ASC])
            ->all();
        self::assertCount(4, $persisted);
        self::assertSame("/mixed/{$token}", $persisted[0]['sourceUrlParsed']);
        self::assertSame("/prefix/{$token}", $persisted[1]['sourceUrlParsed']);
        self::assertSame("^/Regex/{$token}/([A-Z]+)$", $persisted[2]['sourceUrlParsed']);
        self::assertSame("/Wildcard/{$token}/*", $persisted[3]['sourceUrlParsed']);
        self::assertSame(['manual', 'manual', 'manual', 'manual'], array_column($persisted, 'creationType'));
        self::assertSame(['redirect-manager', 'redirect-manager', 'redirect-manager', 'redirect-manager'], array_column($persisted, 'sourcePlugin'));
        self::assertSame([null, null, null, null], array_column($persisted, 'elementId'));
        self::assertSame(1, $redirects->invalidateCalls);
        self::assertNull($session->get('redirect-import'));
        self::assertNull($session->get('redirect-import-validated'));
        self::assertStringContainsString('Successfully imported 4', $session->notice);
    }

    public function testRejectedPreviewRowsHaveNoPersistenceOrCacheEffects(): void
    {
        $token = bin2hex(random_bytes(4));
        [$controller, $session, $validated] = $this->previewLifecycle([
            ['/' . self::MARKER . $token . '-unknown', '/destination', 'unknown', 'pathonly', '0'],
            ['/' . self::MARKER . $token . '-priority', '/destination', 'exact', 'pathonly', '10'],
        ], [
            0 => 'sourceUrl',
            1 => 'destinationUrl',
            2 => 'matchType',
            3 => 'redirectSrcMatch',
            4 => 'priority',
        ]);
        self::assertCount(0, $validated['validRows']);
        self::assertCount(2, $validated['errorRows']);

        $redirects = new ImportCacheRecordingRedirectsService();
        $this->replacePluginComponent('redirects', $redirects);
        $controller->actionImport();

        self::assertSame(0, $this->countRows(RedirectRecord::tableName()));
        self::assertSame(0, $redirects->invalidateCalls);
        self::assertStringContainsString('Successfully imported 0', $session->notice);
    }

    public function testPartialImportCountsRejectedRowsWithoutPersistingThem(): void
    {
        $token = bin2hex(random_bytes(4));
        [$controller, $session, $validated] = $this->previewLifecycle([
            ['/' . self::MARKER . $token . '-valid', '/destination', 'exact', 'pathonly', '0'],
            ['https://portable.example.test/' . strtoupper(self::MARKER . $token) . '-VALID', '/duplicate', 'prefix', 'pathonly', '0'],
            ['/' . self::MARKER . $token . '-invalid', '/destination', 'exact', 'pathonly', 'urgent'],
        ], [
            0 => 'sourceUrl',
            1 => 'destinationUrl',
            2 => 'matchType',
            3 => 'redirectSrcMatch',
            4 => 'priority',
        ]);
        self::assertCount(1, $validated['validRows']);
        self::assertCount(1, $validated['duplicateRows']);
        self::assertCount(1, $validated['errorRows']);

        $redirects = new ImportCacheRecordingRedirectsService();
        $this->replacePluginComponent('redirects', $redirects);
        $controller->actionImport();

        self::assertSame(1, $this->countRows(RedirectRecord::tableName()));
        self::assertSame(1, $redirects->invalidateCalls);
        self::assertStringContainsString('Successfully imported 1', $session->notice);
        self::assertStringContainsString('2 failed', $session->notice);
    }

    public function testMapperDoesNotOfferPortableLifecycleOwnershipFields(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/src/templates/import-export/map.twig');
        self::assertIsString($template);
        self::assertStringNotContainsString("{value: 'creationType'", $template);
        self::assertStringNotContainsString("{value: 'sourcePlugin'", $template);
        self::assertStringNotContainsString("{value: 'elementId'", $template);
    }

    /**
     * @param list<list<string>> $rows
     * @param array<int, string> $mapping
     * @return array{validRows: array<int, array<string, mixed>>, duplicateRows: array<int, array<string, mixed>>, errorRows: array<int, array<string, mixed>>, createBackup: bool}
     */
    private function preview(array $rows, array $mapping): array
    {
        [, , $validated] = $this->previewLifecycle($rows, $mapping);
        return $validated;
    }

    /**
     * @param list<list<string>> $rows
     * @param array<int, string> $mapping
     * @return array{PortableImportController, ArrayImportSession, array{validRows: array<int, array<string, mixed>>, duplicateRows: array<int, array<string, mixed>>, errorRows: array<int, array<string, mixed>>, createBackup: bool}}
     */
    private function previewLifecycle(array $rows, array $mapping): array
    {
        $sites = Craft::$app->getSites();
        $editableSiteIds = array_map(
            static fn($site): int => (int)$site->id,
            $sites->getAllSites(),
        );
        Craft::$app->set('sites', new StubEditableSites($sites, $editableSiteIds));
        Craft::$app->set('user', new StubCpUser(admin: true));
        Craft::$app->set('request', new StubConsoleRequest(['mapping' => $mapping]));
        Craft::$app->set('response', new Response());
        $session = new ArrayImportSession();
        $session->set('redirect-import', [
            'allRows' => $rows,
            'createBackup' => false,
            'filename' => 'portable.csv',
            'filesize' => 1,
        ]);

        $controller = new PortableImportController('import-export', RedirectManager::$plugin, $session);
        $controller->actionPreview();

        $validated = $session->get('redirect-import-validated');
        self::assertIsArray($validated);
        return [$controller, $session, $validated];
    }
}

/** In-memory session boundary for the console integration fixture. */
final class ArrayImportSession
{
    public ?string $notice = null;
    public ?string $error = null;
    /** @var array<string, mixed> */
    private array $values = [];

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function setNotice(string $message): void
    {
        $this->notice = $message;
    }

    public function setError(string $message): void
    {
        $this->error = $message;
    }
}

/** Records cache invalidation after one or more portable rows commit. */
final class ImportCacheRecordingRedirectsService extends RedirectsService
{
    public int $invalidateCalls = 0;

    public function invalidateCaches(): void
    {
        $this->invalidateCalls++;
    }
}

/** Controller seam retaining the production preview action with isolated session storage. */
final class PortableImportController extends ImportExportController
{
    public function __construct(string $id, $module, private readonly ArrayImportSession $session)
    {
        parent::__construct($id, $module);
    }

    protected function importSession(): object
    {
        return $this->session;
    }

    public function redirect($url, $statusCode = 302): Response
    {
        return new Response(['statusCode' => $statusCode]);
    }
}

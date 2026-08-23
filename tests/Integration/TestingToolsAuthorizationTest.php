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
use craft\web\Response;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as PsrResponse;
use lindemannrock\redirectmanager\controllers\SettingsController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\Support\StubConsoleRequest;
use lindemannrock\redirectmanager\tests\Support\StubCpUser;
use lindemannrock\redirectmanager\tests\Support\StubEditableSites;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;
use yii\web\ForbiddenHttpException;

/**
 * Pins testing-tool permission, site-scope, and token-disclosure boundaries.
 */
final class TestingToolsAuthorizationTest extends TestCase
{
    #[DataProvider('roleProvider')]
    public function testTesterPageAndActionsRequireSettingsAndRedirectViewAuthority(
        array $permissions,
        bool $admin,
        bool $allowed,
    ): void {
        $this->settings()->apiEndpointEnabled = false;
        Craft::$app->set('user', new StubCpUser($permissions, $admin));

        foreach (['page', 'url', 'api'] as $surface) {
            Craft::$app->set('request', new StubConsoleRequest());
            Craft::$app->set('response', new Response());
            $exception = null;
            $response = null;
            try {
                $response = match ($surface) {
                    'page' => $this->controller()->actionTest(),
                    'url' => $this->controller()->actionTestUrl(),
                    'api' => $this->controller()->actionRunApiTest(),
                };
            } catch (Throwable $caught) {
                $exception = $caught;
            }

            if ($allowed) {
                self::assertNull($exception, "{$surface} should be available to this role.");
                self::assertInstanceOf(Response::class, $response);
            } else {
                self::assertInstanceOf(ForbiddenHttpException::class, $exception, "{$surface} must reject this role.");
            }
        }
    }

    /** @return iterable<string, array{list<string>, bool, bool}> */
    public static function roleProvider(): iterable
    {
        yield 'settings only' => [['redirectManager:manageSettings'], false, false];
        yield 'redirects only' => [['redirectManager:manageRedirects'], false, false];
        yield 'both permissions' => [[
            'redirectManager:manageSettings',
            'redirectManager:manageRedirects',
        ], false, true];
        yield 'administrator' => [[], true, true];
    }

    public function testApiDiagnosticRejectsNonEditableSitesAndKeepsTokenServerSide(): void
    {
        $sites = Craft::$app->getSites()->getAllSites();
        self::assertGreaterThanOrEqual(2, count($sites));
        $editableSite = $sites[0];
        $inaccessibleSite = $sites[1];
        $realSites = Craft::$app->getSites();
        Craft::$app->set('sites', new StubEditableSites($realSites, [(int)$editableSite->id]));
        Craft::$app->set('user', new StubCpUser([
            'redirectManager:manageSettings',
            'redirectManager:manageRedirects',
        ]));
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'server-only-test-token';

        Craft::$app->set('request', new StubConsoleRequest([
            'testSite' => $inaccessibleSite->handle,
        ]));
        Craft::$app->set('response', new Response());
        $denied = null;
        try {
            $this->controller()->actionRunApiTest();
        } catch (Throwable $exception) {
            $denied = $exception;
        }
        self::assertInstanceOf(ForbiddenHttpException::class, $denied);

        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new PsrResponse(200, ['X-Test-Scope' => 'editable'], '[{"sourceUrl":"/visible"}]'),
        ]));
        $handler->push(Middleware::history($history));
        $client = new Client(['handler' => $handler]);
        Craft::$app->set('request', new StubConsoleRequest([
            'testSite' => $editableSite->handle,
        ]));

        $response = $this->controller($client)->actionRunApiTest();
        self::assertIsArray($response->data);
        self::assertSame(200, $response->data['status']);
        self::assertStringContainsString('/visible', $response->data['body']);
        self::assertStringNotContainsString('server-only-test-token', json_encode($response->data, JSON_THROW_ON_ERROR));
        self::assertStringContainsString('$REDIRECT_MANAGER_API_TOKEN', $response->data['curl']);
        self::assertCount(1, $history);
        self::assertStringContainsString('site=' . $editableSite->handle, $history[0]['request']->getUri()->getQuery());
        self::assertSame('server-only-test-token', $history[0]['request']->getHeaderLine('X-Redirect-Manager-Key'));
    }

    public function testTestNavigationAndPostmanPolicyRemainExplicit(): void
    {
        $settingsLayout = file_get_contents(dirname(__DIR__, 2) . '/src/templates/_layouts/settings.twig');
        self::assertIsString($settingsLayout);
        self::assertMatchesRegularExpression(
            "/{% if currentUser\.can\('redirectManager:manageRedirects'\) %}.*?url\('redirect-manager\/settings\/test'\).*?{% endif %}/s",
            $settingsLayout,
        );

        $controller = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/SettingsController.php');
        self::assertIsString($controller);
        self::assertMatchesRegularExpression(
            '/actionDownloadPostmanCollection\(\).*?requirePermission\(\'redirectManager:manageSettings\'\)/s',
            $controller,
        );

        foreach (['Redirect-Manager.postman_collection.json', 'Redirect-Manager.postman_environment.json'] as $file) {
            $contents = file_get_contents(dirname(__DIR__, 2) . '/postman/' . $file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('server-only-test-token', $contents);
        }
    }

    private function controller(?ClientInterface $client = null): SettingsController
    {
        return new class('settings', RedirectManager::$plugin, $client) extends SettingsController {
            public function __construct($id, $module, private readonly ?ClientInterface $client)
            {
                parent::__construct($id, $module);
            }

            public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): Response
            {
                return new Response(['content' => $template]);
            }

            protected function createApiTestClient(): ClientInterface
            {
                return $this->client ?? new Client([
                    'handler' => new MockHandler([new PsrResponse(500)]),
                ]);
            }
        };
    }
}

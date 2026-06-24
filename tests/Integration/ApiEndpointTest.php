<?php
/**
 * LindemannRock Redirect Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use Craft;
use lindemannrock\redirectmanager\controllers\ApiController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use yii\base\Action;
use yii\web\BadRequestHttpException;
use yii\web\HeaderCollection;
use yii\web\NotFoundHttpException;
use yii\web\TooManyRequestsHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Covers the read-only JSON redirects API endpoint.
 *
 * @since 5.33.0
 */
final class ApiEndpointTest extends TestCase
{
    private bool $savedApiEndpointEnabled = false;

    private ?string $savedApiEndpointToken = null;

    private int $savedApiEndpointRateLimit = 60;

    private ?object $savedRequest = null;

    private ?object $savedResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = $this->settings();
        $this->savedApiEndpointEnabled = $settings->apiEndpointEnabled;
        $this->savedApiEndpointToken = $settings->apiEndpointToken;
        $this->savedApiEndpointRateLimit = $settings->apiEndpointRateLimit;
        $this->savedRequest = Craft::$app->getRequest();
        $this->savedResponse = Craft::$app->getResponse();

        $settings->apiEndpointEnabled = false;
        $settings->apiEndpointToken = null;
        $settings->apiEndpointRateLimit = 60;

        Craft::$app->set('response', new \craft\web\Response());
    }

    protected function tearDown(): void
    {
        $settings = $this->settings();
        $settings->apiEndpointEnabled = $this->savedApiEndpointEnabled;
        $settings->apiEndpointToken = $this->savedApiEndpointToken;
        $settings->apiEndpointRateLimit = $this->savedApiEndpointRateLimit;

        if ($this->savedRequest !== null) {
            Craft::$app->set('request', $this->savedRequest);
        }
        if ($this->savedResponse !== null) {
            Craft::$app->set('response', $this->savedResponse);
        }

        parent::tearDown();
    }

    public function testDisabledEndpointThrows404(): void
    {
        $this->installRequest();

        $this->expectException(NotFoundHttpException::class);
        $this->runApiBeforeAction();
    }

    public function testEnabledEndpointRejectsMissingConfiguredToken(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->installRequest();

        $this->expectException(UnauthorizedHttpException::class);
        $this->runApiBeforeAction();
    }

    public function testTokenProtectedEndpointRejectsMissingToken(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest();

        $this->expectException(UnauthorizedHttpException::class);
        $this->runApiBeforeAction();
    }

    public function testTokenProtectedEndpointAcceptsHeaderToken(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => 'test-token']);

        self::assertTrue($this->runApiBeforeAction());
    }

    public function testTokenProtectedEndpointAcceptsBearerToken(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(headers: ['Authorization' => 'Bearer test-token']);

        self::assertTrue($this->runApiBeforeAction());
    }

    public function testListEndpointRequiresJsonAcceptHeader(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token-accept';
        $this->installRequest(
            headers: [ApiController::TOKEN_HEADER => 'test-token-accept'],
            acceptJson: false,
        );

        $this->runApiBeforeAction();

        $this->expectException(BadRequestHttpException::class);
        $this->apiController()->actionGetRedirects();
    }

    public function testRateLimitRejectsAfterConfiguredLimit(): void
    {
        $token = 'test-token-limit-' . uniqid('', true);
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = $token;
        $this->settings()->apiEndpointRateLimit = 1;
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);

        self::assertTrue($this->runApiBeforeAction());

        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);

        $this->expectException(TooManyRequestsHttpException::class);
        $this->runApiBeforeAction();
    }

    public function testRateLimitCanBeDisabled(): void
    {
        $token = 'test-token-unlimited-' . uniqid('', true);
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = $token;
        $this->settings()->apiEndpointRateLimit = 0;

        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);
        self::assertTrue($this->runApiBeforeAction());

        $this->installRequest(headers: [ApiController::TOKEN_HEADER => $token]);
        self::assertTrue($this->runApiBeforeAction());
    }

    public function testGetRedirectsReturnsEnabledRedirectsOnly(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(headers: [ApiController::TOKEN_HEADER => 'test-token']);

        $enabled = $this->seedRedirect();
        $disabled = $this->seedRedirect(['enabled' => false]);

        $this->runApiBeforeAction();
        $ids = $this->responseRedirectIds();

        self::assertContains($enabled->id, $ids);
        self::assertNotContains($disabled->id, $ids);
    }

    public function testSiteIdFilterIncludesGlobalRedirects(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(['siteId' => (string)$site->id], [ApiController::TOKEN_HEADER => 'test-token']);

        $siteRedirect = $this->seedRedirect(['siteId' => $site->id]);
        $globalRedirect = $this->seedRedirect(['siteId' => null]);

        $this->runApiBeforeAction();
        $ids = $this->responseRedirectIds();

        self::assertContains($siteRedirect->id, $ids);
        self::assertContains($globalRedirect->id, $ids);
    }

    public function testInvalidExplicitSiteReturnsEmptyList(): void
    {
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(['site' => '__missing_site__'], [ApiController::TOKEN_HEADER => 'test-token']);
        $this->seedRedirect();

        $this->runApiBeforeAction();

        self::assertSame([], $this->apiResponseData());
    }

    public function testListEndpointDoesNotRecordHitsOrAnalytics(): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $this->settings()->apiEndpointEnabled = true;
        $this->settings()->apiEndpointToken = 'test-token';
        $this->installRequest(['siteId' => (string)$site->id], [ApiController::TOKEN_HEADER => 'test-token']);

        $redirect = $this->seedRedirect(['siteId' => $site->id]);

        $this->runApiBeforeAction();
        $this->apiResponseData();

        self::assertSame(0, $this->fetchHitCountFromDb($redirect->id));
        self::assertNull($this->fetchRow('{{%redirectmanager_analytics}}', [
            'urlParsed' => $redirect->sourceUrlParsed,
            'siteId' => $site->id,
        ]));
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $headers
     */
    private function installRequest(array $params = [], array $headers = [], bool $acceptJson = true): void
    {
        if ($acceptJson && !isset($headers['Accept'])) {
            $headers['Accept'] = 'application/json';
        }

        Craft::$app->set('request', new class($params, $headers, $acceptJson) extends \craft\console\Request {
            private HeaderCollection $headers;

            /**
             * @param array<string, string> $params
             * @param array<string, string> $headers
             */
            public function __construct(private readonly array $params, array $headers, private readonly bool $acceptJson)
            {
                parent::__construct();
                $this->headers = new HeaderCollection();
                foreach ($headers as $name => $value) {
                    $this->headers->set($name, $value);
                }
            }

            public function getHeaders(): HeaderCollection
            {
                return $this->headers;
            }

            public function getAcceptsJson(): bool
            {
                return $this->acceptJson;
            }

            public function getIsOptions(): bool
            {
                return false;
            }

            public function getParam($name, $defaultValue = null): mixed
            {
                return $this->params[$name] ?? $defaultValue;
            }

            public function validateCsrfToken($clientSuppliedToken = null): bool
            {
                return true;
            }

            public function hasValidSiteToken(): bool
            {
                return false;
            }
        });
    }

    private function runApiBeforeAction(): bool
    {
        $controller = $this->apiController();

        return $controller->beforeAction(new Action('get-redirects', $controller));
    }

    /**
     * @return list<int>
     */
    private function responseRedirectIds(): array
    {
        return array_map(
            static fn(array $row): int => (int)$row['id'],
            $this->apiResponseData(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function apiResponseData(): array
    {
        $response = $this->apiController()->actionGetRedirects();

        self::assertIsArray($response->data);

        return $response->data;
    }

    private function apiController(): ApiController
    {
        return new ApiController('api', RedirectManager::$plugin);
    }
}

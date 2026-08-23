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
use craft\web\Response;
use lindemannrock\redirectmanager\controllers\ImportExportController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\Support\StubConsoleRequest;
use lindemannrock\redirectmanager\tests\Support\StubCpUser;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;
use yii\web\ForbiddenHttpException;

/**
 * Pins each destructive backup action to its exact child permission.
 */
final class BackupOperationPermissionTest extends TestCase
{
    #[DataProvider('operationRoleProvider')]
    public function testDirectBackupActionsRequireTheirExactChildPermission(
        string $action,
        bool $post,
        array $permissions,
        bool $admin,
        bool $allowed,
    ): void {
        $this->settings()->backupEnabled = false;
        Craft::$app->set('user', new StubCpUser($permissions, $admin));
        Craft::$app->set('request', new StubConsoleRequest(
            bodyParams: ['dirname' => 'manual/2026-01-01_00-00-00'],
            queryParams: ['dirname' => 'manual/2026-01-01_00-00-00'],
            post: $post,
            acceptsJson: true,
        ));
        Craft::$app->set('response', new Response());
        $redirectCount = $this->countRows('{{%redirectmanager_redirects}}');
        $analyticsCount = $this->countRows('{{%redirectmanager_analytics}}');

        $exception = null;
        try {
            (new ImportExportController('import-export', RedirectManager::$plugin))->{$action}();
        } catch (Throwable $caught) {
            $exception = $caught;
        }

        if ($allowed) {
            self::assertNotInstanceOf(ForbiddenHttpException::class, $exception);
            if ($exception !== null) {
                self::assertInstanceOf(
                    MissingComponentException::class,
                    $exception,
                    'The console-only session boundary is the only accepted post-authorization stop.',
                );
            }
        } else {
            self::assertInstanceOf(ForbiddenHttpException::class, $exception);
        }
        self::assertSame($redirectCount, $this->countRows('{{%redirectmanager_redirects}}'));
        self::assertSame($analyticsCount, $this->countRows('{{%redirectmanager_analytics}}'));
    }

    public function testBackupPresentationUsesExactChildPermissions(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/src/templates/backups/index.twig');
        self::assertIsString($template);
        self::assertStringContainsString("currentUser.can('redirectManager:createBackups')", $template);

        $controller = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/ImportExportController.php');
        self::assertIsString($controller);
        foreach (['downloadBackups', 'restoreBackups', 'deleteBackups'] as $permission) {
            self::assertStringContainsString("'permission' => 'redirectManager:{$permission}'", $controller);
        }
    }

    /** @return iterable<string, array{string, bool, list<string>, bool, bool}> */
    public static function operationRoleProvider(): iterable
    {
        $operations = [
            'create' => ['actionCreateBackup', true, 'redirectManager:createBackups'],
            'download' => ['actionDownloadBackup', false, 'redirectManager:downloadBackups'],
            'restore' => ['actionRestoreBackup', true, 'redirectManager:restoreBackups'],
            'delete' => ['actionDeleteBackup', true, 'redirectManager:deleteBackups'],
        ];
        $children = [
            'redirectManager:createBackups',
            'redirectManager:downloadBackups',
            'redirectManager:restoreBackups',
            'redirectManager:deleteBackups',
        ];

        foreach ($operations as $operation => [$action, $post, $required]) {
            yield "{$operation}: parent only" => [$action, $post, ['redirectManager:manageBackups'], false, false];
            foreach ($children as $child) {
                yield "{$operation}: {$child}" => [$action, $post, [$child], false, $child === $required];
            }
            yield "{$operation}: parent plus child" => [$action, $post, ['redirectManager:manageBackups', $required], false, true];
            yield "{$operation}: administrator" => [$action, $post, [], true, true];
        }
    }
}

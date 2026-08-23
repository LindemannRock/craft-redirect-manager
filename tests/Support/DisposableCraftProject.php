<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Support;

use PDO;

/**
 * Owns one disposable MySQL Craft project and its exact cleanup lifecycle.
 *
 * @since 5.41.0
 */
final class DisposableCraftProject
{
    public const SOURCE_VENDOR_ENV = 'REDIRECT_MANAGER_FIXTURE_SOURCE_VENDOR_ROOT';
    public const FAILURE_STAGE_ENV = 'REDIRECT_MANAGER_FIXTURE_FAIL_STAGE';

    private const DATABASE_PREFIX = 'rm_post2d_';
    private const PLUGIN_HANDLES = ['logging-library', 'redirect-manager'];

    private string $runId;
    private string $projectRoot;
    private string $databaseName;
    private string $vendorRoot;
    private string $securityKey;
    private bool $databaseCreated = false;
    private bool $grantCreated = false;
    private bool $projectCreated = false;
    private bool $cleanupComplete = false;
    /** @var resource|null */
    private $activeProcess = null;
    /** @var list<array{command: list<string>, exitCode: int, stdout: string, stderr: string}> */
    private array $commands = [];

    public function __construct(private readonly string $packageRoot)
    {
        $this->runId = bin2hex(random_bytes(8));
        $this->projectRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/redirect-manager-fixture-' . $this->runId;
        $this->databaseName = self::DATABASE_PREFIX . $this->runId;
        $this->vendorRoot = $this->resolveVendorRoot();
        $this->securityKey = bin2hex(random_bytes(32));
    }

    /** @param list<string> $phpunitArguments @return array<string, mixed> */
    public function run(array $phpunitArguments = []): array
    {
        $this->installCleanupGuards();
        $failure = null;
        $phpunit = null;
        try {
            $this->createDatabase();
            $this->createProject();
            $this->installCraft();
            $this->installPlugins();
            $this->seedSites();
            $phpunit = $this->runPhpunit($phpunitArguments);
        } catch (\Throwable $exception) {
            $failure = $exception;
        }

        $cleanup = $this->cleanupWithFailure($failure);
        if ($failure !== null) {
            throw new \RuntimeException(
                $failure->getMessage() . "\nDisposable command evidence:\n"
                . json_encode($this->commands, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                . "\nDisposable cleanup evidence:\n"
                . json_encode($cleanup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                previous: $failure,
            );
        }

        return [
            'runId' => $this->runId,
            'projectRoot' => $this->projectRoot,
            'databaseName' => $this->databaseName,
            'phpunit' => $phpunit,
            'commands' => $this->commands,
            'cleanup' => $cleanup,
            'ownerBoundary' => [
                'ownerDatabaseNameRejected' => 'db',
                'ownerProjectRead' => false,
                'sourceVendorRoot' => $this->vendorRoot,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function runFailureProbe(): array
    {
        $this->installCleanupGuards();
        $failure = null;
        try {
            $this->createDatabase();
            $this->createProject();
            throw new \RuntimeException('Synthetic disposable runner failure.');
        } catch (\Throwable $exception) {
            $failure = $exception;
        }
        $cleanup = $this->cleanupWithFailure(null);

        return ['failure' => $failure->getMessage(), 'cleanup' => $cleanup];
    }

    public function waitForInterruption(string $readyPath): never
    {
        $this->installCleanupGuards();
        $this->createDatabase();
        $this->createProject();
        $payload = json_encode([
            'databaseName' => $this->databaseName,
            'projectRoot' => $this->projectRoot,
        ], JSON_THROW_ON_ERROR);
        if (file_put_contents($readyPath, $payload) === false) {
            throw new \RuntimeException('Unable to write interruption readiness evidence.');
        }
        for (;;) {
            usleep(100000);
        }
    }

    /** @return array{projectRemoved: bool, databaseRemoved: bool, grantRemoved: bool} */
    public function cleanup(): array
    {
        if ($this->cleanupComplete) {
            return ['projectRemoved' => true, 'databaseRemoved' => true, 'grantRemoved' => true];
        }
        $errors = [];
        if (is_resource($this->activeProcess)) {
            @proc_terminate($this->activeProcess, 15);
            @proc_close($this->activeProcess);
            $this->activeProcess = null;
        }
        if ($this->grantCreated) {
            try {
                $this->adminPdo()->exec("REVOKE ALL PRIVILEGES ON `{$this->databaseName}`.* FROM 'db'@'%'");
                $this->grantCreated = false;
            } catch (\Throwable $exception) {
                $errors[] = 'grant: ' . $exception->getMessage();
            }
        }
        if ($this->databaseCreated) {
            try {
                $this->adminPdo()->exec('DROP DATABASE `' . $this->databaseName . '`');
                $this->databaseCreated = false;
            } catch (\Throwable $exception) {
                $errors[] = 'database: ' . $exception->getMessage();
            }
        }
        if ($this->projectCreated || is_dir($this->projectRoot)) {
            try {
                $this->removeOwnedProjectRoot();
                $this->projectCreated = false;
            } catch (\Throwable $exception) {
                $errors[] = 'filesystem: ' . $exception->getMessage();
            }
        }
        $result = [
            'projectRemoved' => !file_exists($this->projectRoot),
            'databaseRemoved' => !$this->databaseExists(),
            'grantRemoved' => !$this->grantExists(),
        ];
        foreach ($result as $label => $removed) {
            if (!$removed) {
                $errors[] = "{$label}: exact run-owned resource remains";
            }
        }
        if ($errors !== []) {
            throw new \RuntimeException('Disposable cleanup failed: ' . implode('; ', $errors));
        }
        $this->cleanupComplete = true;

        return $result;
    }

    private function cleanupWithFailure(?\Throwable $original): array
    {
        try {
            return $this->cleanup();
        } catch (\Throwable $cleanupFailure) {
            if ($original !== null) {
                throw new \RuntimeException(
                    'Disposable project failed and cleanup also failed: ' . $original->getMessage()
                    . '; cleanup: ' . $cleanupFailure->getMessage(),
                    previous: $cleanupFailure,
                );
            }
            throw $cleanupFailure;
        }
    }

    private function installCleanupGuards(): void
    {
        register_shutdown_function(function(): void {
            try {
                $this->cleanup();
            } catch (\Throwable $exception) {
                fwrite(STDERR, 'Disposable shutdown cleanup failed: ' . $exception->getMessage() . PHP_EOL);
            }
        });
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            foreach ([SIGINT => 130, SIGTERM => 143, SIGHUP => 129] as $signal => $status) {
                pcntl_signal($signal, function() use ($status): never {
                    try {
                        $this->cleanup();
                    } catch (\Throwable $exception) {
                        fwrite(STDERR, 'Disposable signal cleanup failed: ' . $exception->getMessage() . PHP_EOL);
                        exit(1);
                    }
                    exit($status);
                });
            }
        }
    }

    private function createDatabase(): void
    {
        $this->injectFailure('database');
        if (!preg_match('/^' . self::DATABASE_PREFIX . '[a-f0-9]{16}$/', $this->databaseName)
            || $this->databaseName === 'db' || $this->databaseExists() || $this->grantExists()) {
            throw new \RuntimeException('Disposable database boundary is invalid or not fresh.');
        }
        $admin = $this->adminPdo();
        $admin->exec('CREATE DATABASE `' . $this->databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->databaseCreated = true;
        $admin->exec("GRANT ALL PRIVILEGES ON `{$this->databaseName}`.* TO 'db'@'%'");
        $this->grantCreated = true;
    }

    private function createProject(): void
    {
        $this->injectFailure('project');
        if (file_exists($this->projectRoot)) {
            throw new \RuntimeException('Disposable project root already exists.');
        }
        foreach (['config', 'storage', 'templates', 'web'] as $relative) {
            $path = $this->projectRoot . '/' . $relative;
            if (!mkdir($path, 0700, true) && !is_dir($path)) {
                throw new \RuntimeException("Unable to create {$path}");
            }
        }
        $this->projectCreated = true;
        if (!symlink($this->vendorRoot, $this->projectRoot . '/vendor')) {
            throw new \RuntimeException('Unable to link the explicit fixture vendor root.');
        }
        $this->writeOwnedFile('bootstrap.php', <<<'PHP'
<?php
define('CRAFT_BASE_PATH', __DIR__);
define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');
require_once CRAFT_VENDOR_PATH . '/autoload.php';
if (class_exists(Dotenv\Dotenv::class)) {
    Dotenv\Dotenv::createUnsafeMutable(CRAFT_BASE_PATH)->safeLoad();
}
PHP);
        $this->writeOwnedFile('craft', <<<'PHP'
#!/usr/bin/env php
<?php
require __DIR__ . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
exit($app->run());
PHP);
        chmod($this->projectRoot . '/craft', 0700);
        $this->writeOwnedFile('config/general.php', <<<'PHP'
<?php
use craft\config\GeneralConfig;
return GeneralConfig::create()->allowAdminChanges(true)->devMode(false)->omitScriptNameInUrls();
PHP);
        $this->writeOwnedFile('config/app.php', "<?php\nreturn [\n    'id' => 'redirect-manager-fixture-{$this->runId}',\n    'aliases' => [\n        '@root' => dirname(__DIR__),\n        '@webroot' => dirname(__DIR__) . '/web',\n        '@web' => '/',\n    ],\n];\n");
        $this->writeOwnedFile('config/db.php', <<<'PHP'
<?php
use craft\helpers\App;
return [
    'dsn' => App::env('CRAFT_DB_DSN'),
    'user' => App::env('CRAFT_DB_USER'),
    'password' => App::env('CRAFT_DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'schema' => App::env('CRAFT_DB_SCHEMA'),
    'tablePrefix' => App::env('CRAFT_DB_TABLE_PREFIX'),
];
PHP);
        $this->writeOwnedFile('.env', implode("\n", [
            'CRAFT_APP_ID=redirect-manager-fixture-' . $this->runId,
            'CRAFT_ENVIRONMENT=test',
            'CRAFT_EDITION=pro',
            'CRAFT_SECURITY_KEY=' . $this->securityKey,
            'CRAFT_DB_DSN=' . $this->fixtureDsn(),
            'CRAFT_DB_USER=db',
            'CRAFT_DB_PASSWORD=db',
            'CRAFT_DB_SCHEMA=',
            'CRAFT_DB_TABLE_PREFIX=',
            'PRIMARY_SITE_URL=https://redirect-primary.example.test',
            '',
        ]));
    }

    private function installCraft(): void
    {
        $this->injectFailure('install');
        $this->runCommand([
            PHP_BINARY, $this->projectRoot . '/craft', 'install', '--interactive=0', '--silent-exit-on-exception=0',
            '--site-name=Redirect Manager Fixture', '--site-url=https://redirect-primary.example.test', '--language=en-US',
            '--username=fixture-admin', '--email=fixture-admin@example.test', '--password=Redirect-Fixture-Password-2026!',
        ], $this->projectRoot);
    }

    private function installPlugins(): void
    {
        $this->injectFailure('plugins');
        foreach (self::PLUGIN_HANDLES as $handle) {
            $this->runCommand([PHP_BINARY, $this->projectRoot . '/craft', 'plugin/install', $handle, '--interactive=0', '--silent-exit-on-exception=0'], $this->projectRoot);
        }
    }

    private function seedSites(): void
    {
        $this->injectFailure('seed');
        $this->runCommand([PHP_BINARY, $this->packageRoot . '/tests/Fixtures/Project/seed-sites.php'], $this->packageRoot);
    }

    /** @param list<string> $arguments @return array<string, mixed> */
    private function runPhpunit(array $arguments): array
    {
        $this->injectFailure('phpunit');
        $reportPath = $this->projectRoot . '/phpunit-result.xml';
        $result = $this->runCommand([
            PHP_BINARY, $this->vendorRoot . '/bin/phpunit', '--configuration', $this->packageRoot . '/phpunit.xml.dist',
            '--colors=never', '--log-junit', $reportPath, ...$arguments,
        ], $this->packageRoot);
        if (!$this->isCompleteSuiteInvocation($arguments)) {
            $result['acceptedSuite'] = null;
            return $result;
        }

        $baseline = AcceptedSuiteAuthority::load($this->packageRoot . '/tests/accepted-suite.json');
        $actual = AcceptedSuiteAuthority::readJUnitSummary($reportPath);
        AcceptedSuiteAuthority::assertExecuted($baseline['executed'], $actual);
        $result['acceptedSuite'] = $actual;

        return $result;
    }

    /** @param list<string> $arguments */
    private function isCompleteSuiteInvocation(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, '-')
                || preg_match('/^--(?:filter|testsuite|exclude-testsuite|group|exclude-group|list-tests|list-tests-xml|test-suffix)(?:=|$)/', $argument) === 1) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $command @return array{command: list<string>, exitCode: int, stdout: string, stderr: string} */
    private function runCommand(array $command, string $workingDirectory): array
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workingDirectory, $this->subprocessEnvironment());
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start disposable command.');
        }
        $this->activeProcess = $process;
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $this->activeProcess = null;
        $result = ['command' => $command, 'exitCode' => $status, 'stdout' => is_string($stdout) ? $stdout : '', 'stderr' => is_string($stderr) ? $stderr : ''];
        $this->commands[] = $result;
        if ($status !== 0) {
            throw new \RuntimeException('Disposable command failed (' . $status . '): ' . implode(' ', $command) . "\n{$result['stdout']}\n{$result['stderr']}");
        }

        return $result;
    }

    /** @return array<string, string> */
    private function subprocessEnvironment(): array
    {
        $environment = [
            'PATH' => is_string($_SERVER['PATH'] ?? null) ? $_SERVER['PATH'] : '/usr/local/bin:/usr/bin:/bin',
            'LANG' => is_string($_SERVER['LANG'] ?? null) && $_SERVER['LANG'] !== '' ? $_SERVER['LANG'] : 'C.UTF-8',
            'CRAFT_APP_ID' => 'redirect-manager-fixture-' . $this->runId,
            'CRAFT_ALLOW_SUPERUSER' => '1',
            'CRAFT_EDITION' => 'pro',
            'CRAFT_ENVIRONMENT' => 'test',
            'CRAFT_SECURITY_KEY' => $this->securityKey,
            'CRAFT_DB_DSN' => $this->fixtureDsn(),
            'CRAFT_DB_USER' => 'db',
            'CRAFT_DB_PASSWORD' => 'db',
            'CRAFT_DB_SCHEMA' => '',
            'CRAFT_DB_TABLE_PREFIX' => '',
            'PRIMARY_SITE_URL' => 'https://redirect-primary.example.test',
            TestProjectBoundary::PROJECT_ROOT_ENV => $this->projectRoot,
            TestProjectBoundary::DISPOSABLE_ENV => '1',
            self::SOURCE_VENDOR_ENV => $this->vendorRoot,
            'REDIRECT_MANAGER_TEST_RUN_ID' => $this->runId,
        ];
        foreach (['XDEBUG_MODE', 'PHP_IDE_CONFIG', 'REDIRECT_MANAGER_TEST_FORCE_FAILURE'] as $name) {
            $value = \craft\helpers\App::env($name);
            if (is_scalar($value) && $value !== '') {
                $environment[$name] = (string)$value;
            }
        }

        return $environment;
    }

    private function resolveVendorRoot(): string
    {
        $configured = $_SERVER[self::SOURCE_VENDOR_ENV] ?? null;
        $candidates = is_string($configured) && $configured !== ''
            ? [$configured]
            : [$this->packageRoot . '/vendor', dirname($this->packageRoot, 2) . '/vendor'];
        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved !== false && is_file($resolved . '/autoload.php') && is_file($resolved . '/bin/phpunit')) {
                return rtrim($resolved, DIRECTORY_SEPARATOR);
            }
        }
        throw new \RuntimeException(self::SOURCE_VENDOR_ENV . ' must resolve to a Composer vendor root with PHPUnit.');
    }

    private function fixtureDsn(): string
    {
        return 'mysql:host=db;port=3306;dbname=' . $this->databaseName;
    }

    private function adminPdo(): PDO
    {
        return new PDO('mysql:host=db;port=3306;charset=utf8mb4', 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function databaseExists(): bool
    {
        $statement = $this->adminPdo()->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = :name');
        $statement->execute(['name' => $this->databaseName]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function grantExists(): bool
    {
        $statement = $this->adminPdo()->prepare("SELECT COUNT(*) FROM mysql.db WHERE Host = '%' AND Db = :name AND User = 'db'");
        $statement->execute(['name' => $this->databaseName]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function writeOwnedFile(string $relativePath, string $contents): void
    {
        $path = $this->projectRoot . '/' . $relativePath;
        if (!str_starts_with($path, $this->projectRoot . '/')) {
            throw new \LogicException('Refusing to write outside the disposable project.');
        }
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("Unable to write {$path}");
        }
    }

    private function removeOwnedProjectRoot(): void
    {
        $expected = '#^' . preg_quote(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR), '#') . '/redirect-manager-fixture-[a-f0-9]{16}$#';
        if (preg_match($expected, $this->projectRoot) !== 1) {
            throw new \LogicException('Refusing cleanup outside the disposable project boundary.');
        }
        if (!file_exists($this->projectRoot)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->projectRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                if (!unlink($item->getPathname())) {
                    throw new \RuntimeException('Unable to remove ' . $item->getPathname());
                }
            } elseif (!rmdir($item->getPathname())) {
                throw new \RuntimeException('Unable to remove ' . $item->getPathname());
            }
        }
        if (!rmdir($this->projectRoot)) {
            throw new \RuntimeException('Unable to remove disposable project root.');
        }
    }

    private function injectFailure(string $stage): void
    {
        if (($_SERVER[self::FAILURE_STAGE_ENV] ?? null) === $stage) {
            throw new \RuntimeException("Synthetic disposable fixture {$stage} failure.");
        }
    }
}

<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\redirectmanager\tests\Integration;

use lindemannrock\redirectmanager\console\controllers\SecurityController;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use yii\console\ExitCode;

/**
 * Covers complete, atomic project environment updates for generated secrets.
 *
 * @since 5.41.0
 */
final class SecurityEnvironmentUpdateTest extends TestCase
{
    #[DataProvider('secretTypeProvider')]
    public function testMissingEnvironmentPrintsTheGeneratedManualAssignmentWithoutCreatingFiles(string $secretType): void
    {
        $controller = new RecordingEnvironmentSecurityController('security', RedirectManager::getInstance());
        $controller->files = [RecordingEnvironmentSecurityController::NEIGHBOR_PATH => "neighbor\n"];
        $controller->modes = [RecordingEnvironmentSecurityController::NEIGHBOR_PATH => 0100644];

        $exitCode = $this->runSecretAction($controller, $secretType);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertStringContainsString('Warning: .env file not found', $controller->output);
        $this->assertManualAssignment($controller->output, $secretType);
        self::assertSame([RecordingEnvironmentSecurityController::NEIGHBOR_PATH => "neighbor\n"], $controller->files);
        self::assertSame([], $controller->temporaryFiles());
        self::assertNotContains('create-temp', $controller->calls);
        self::assertFalse($controller->successReported);
    }

    #[DataProvider('appendProvider')]
    public function testAppendPreservesFormattingCommentsModeAndNeighboringFiles(string $secretType, bool $trailingNewline): void
    {
        $original = 'APP_ENV=production' . ($trailingNewline ? "\n" : '');
        $controller = $this->recordingController($original);

        $exitCode = $this->runSecretAction($controller, $secretType);
        $updated = $controller->files[RecordingEnvironmentSecurityController::ENV_PATH];
        $expectedPrefix = $original . ($trailingNewline ? "\n" : "\n\n") . '# Redirect Manager ';

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertStringStartsWith($expectedPrefix, $updated);
        $this->assertGeneratedAssignment($updated, $secretType);
        $this->assertSuccessfulAtomicReplacement($controller, $secretType, 0640);
    }

    #[DataProvider('secretTypeProvider')]
    public function testExistingAssignmentIsReplacedWithoutChangingOtherBytesOrMode(string $secretType): void
    {
        $environmentVariable = $this->environmentVariable($secretType);
        $original = "APP_ENV=production\n{$environmentVariable}=\"old\"\nOTHER=value\n";
        $controller = $this->recordingController($original);

        $exitCode = $this->runSecretAction($controller, $secretType);
        $updated = $controller->files[RecordingEnvironmentSecurityController::ENV_PATH];

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertStringStartsWith("APP_ENV=production\n", $updated);
        self::assertStringEndsWith("\nOTHER=value\n", $updated);
        self::assertStringNotContainsString($environmentVariable . '="old"', $updated);
        self::assertSame(1, substr_count($updated, $environmentVariable . '='));
        $this->assertGeneratedAssignment($updated, $secretType);
        $this->assertSuccessfulAtomicReplacement($controller, $secretType, 0640);
    }

    #[DataProvider('cancellationProvider')]
    public function testCancellationPreservesTheEnvironmentWithoutStartingAWrite(string $secretType, string $stage): void
    {
        $environmentVariable = $this->environmentVariable($secretType);
        $original = $stage === 'initial'
            ? "APP_ENV=production\n"
            : "APP_ENV=production\n{$environmentVariable}=\"old\"\n";
        $controller = $this->recordingController($original);
        $controller->confirmationResults = $stage === 'replacement' && $secretType === 'token'
            ? [true, false]
            : [false];

        $exitCode = $this->runSecretAction($controller, $secretType);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertSame($original, $controller->files[RecordingEnvironmentSecurityController::ENV_PATH]);
        self::assertSame([], $controller->temporaryFiles());
        self::assertNotContains('create-temp', $controller->calls);
        self::assertFalse($controller->successReported);
        self::assertStringContainsString('Operation cancelled.', $controller->output);
        if ($stage === 'initial') {
            $this->assertManualAssignment($controller->output, $secretType);
        }
    }

    #[DataProvider('preWriteFailureProvider')]
    public function testReadAndAssignmentFailuresPreserveOriginalStateAndPrintManualFallback(string $secretType, string $failure): void
    {
        $environmentVariable = $this->environmentVariable($secretType);
        $original = "APP_ENV=production\n{$environmentVariable}=\"old\"\n";
        $controller = $this->recordingController($original);
        $controller->failure = $failure;

        $exitCode = $this->runSecretAction($controller, $secretType);

        self::assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);
        $this->assertOriginalStatePreserved($controller, $original, 0640);
        self::assertSame([], $controller->temporaryFiles());
        self::assertNotContains('create-temp', $controller->calls);
        self::assertFalse($controller->successReported);
        $this->assertManualAssignment($controller->output, $secretType);
    }

    #[DataProvider('atomicFailureProvider')]
    public function testAtomicFailurePreservesOriginalAndNeighborStateAndCleansOnlyItsTemporaryFile(
        string $secretType,
        string $failure,
    ): void {
        $original = "APP_ENV=production\n";
        $controller = $this->recordingController($original);
        $controller->failure = $failure;

        $exitCode = $this->runSecretAction($controller, $secretType);

        self::assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);
        $this->assertOriginalStatePreserved($controller, $original, 0640);
        self::assertSame([], $controller->temporaryFiles());
        self::assertSame([], $controller->writesToEnvironmentPath);
        self::assertFalse($controller->successReported);
        self::assertNotContains(RecordingEnvironmentSecurityController::NEIGHBOR_PATH, $controller->deletedPaths);
        if ($failure === 'close') {
            self::assertSame(1, count(array_filter(
                $controller->calls,
                static fn(string $call): bool => $call === 'close',
            )));
        }
        $this->assertManualAssignment($controller->output, $secretType);
    }

    #[DataProvider('secretTypeProvider')]
    public function testNativeFilesystemReplacementPreservesModeAndLeavesNoTemporaryOrNeighborChanges(string $secretType): void
    {
        $directory = $this->createTrackedTempDirectory('redirect-security-environment-');
        $envPath = $directory . DIRECTORY_SEPARATOR . '.env';
        $neighborPath = $directory . DIRECTORY_SEPARATOR . 'neighbor.txt';
        $original = "APP_ENV=production\n";
        $neighbor = "neighbor-bytes\n";
        self::assertSame(strlen($original), file_put_contents($envPath, $original));
        self::assertSame(strlen($neighbor), file_put_contents($neighborPath, $neighbor));
        self::assertTrue(chmod($envPath, 0640));
        self::assertTrue(chmod($neighborPath, 0604));
        $controller = new NativeEnvironmentSecurityController('security', RedirectManager::getInstance(), $envPath);

        $exitCode = $this->runSecretAction($controller, $secretType);
        clearstatcache(true, $envPath);
        clearstatcache(true, $neighborPath);
        $updated = file_get_contents($envPath);

        self::assertSame(ExitCode::OK, $exitCode);
        self::assertIsString($updated);
        $this->assertGeneratedAssignment($updated, $secretType);
        self::assertSame(0640, fileperms($envPath) & 0777);
        self::assertSame($neighbor, file_get_contents($neighborPath));
        self::assertSame(0604, fileperms($neighborPath) & 0777);
        self::assertSame([], glob($directory . DIRECTORY_SEPARATOR . '.*.tmp-*') ?: []);
        self::assertStringContainsString('✓ Added ' . $this->environmentVariable($secretType), $controller->output);
    }

    #[DataProvider('secretTypeProvider')]
    public function testRepeatedSuccessfulExecutionKeepsOneAssignmentAndNoTemporaryResidue(string $secretType): void
    {
        $controller = $this->recordingController("APP_ENV=production\n");

        self::assertSame(ExitCode::OK, $this->runSecretAction($controller, $secretType));
        self::assertSame(ExitCode::OK, $this->runSecretAction($controller, $secretType));

        $updated = $controller->files[RecordingEnvironmentSecurityController::ENV_PATH];
        self::assertSame(1, substr_count($updated, $this->environmentVariable($secretType) . '='));
        self::assertSame(1, substr_count($updated, '# Redirect Manager '));
        $this->assertGeneratedAssignment($updated, $secretType);
        self::assertSame([], $controller->temporaryFiles());
        self::assertSame(0640, $controller->modes[RecordingEnvironmentSecurityController::ENV_PATH] & 0777);
        self::assertSame("neighbor-bytes\n", $controller->files[RecordingEnvironmentSecurityController::NEIGHBOR_PATH]);
        self::assertSame(0604, $controller->modes[RecordingEnvironmentSecurityController::NEIGHBOR_PATH] & 0777);
    }

    /** @return iterable<string, array{string}> */
    public static function secretTypeProvider(): iterable
    {
        yield 'API token' => ['token'];
        yield 'IP salt' => ['salt'];
    }

    /** @return iterable<string, array{string, bool}> */
    public static function appendProvider(): iterable
    {
        foreach (['token', 'salt'] as $secretType) {
            yield $secretType . ' without trailing newline' => [$secretType, false];
            yield $secretType . ' with trailing newline' => [$secretType, true];
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function cancellationProvider(): iterable
    {
        yield 'API token initial write prompt' => ['token', 'initial'];
        yield 'API token replacement prompt' => ['token', 'replacement'];
        yield 'IP salt replacement prompt' => ['salt', 'replacement'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function preWriteFailureProvider(): iterable
    {
        foreach (['token', 'salt'] as $secretType) {
            yield $secretType . ' read failure' => [$secretType, 'read'];
            yield $secretType . ' assignment match failure' => [$secretType, 'match'];
            yield $secretType . ' assignment replacement failure' => [$secretType, 'replace'];
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function atomicFailureProvider(): iterable
    {
        foreach (['token', 'salt'] as $secretType) {
            foreach ([
                'create',
                'wrong-directory',
                'open',
                'write',
                'short-write',
                'flush',
                'close',
                'verify-read',
                'verify-mismatch',
                'mode-read',
                'chmod',
                'mode-verify',
                'rename',
            ] as $failure) {
                yield $secretType . ' ' . $failure => [$secretType, $failure];
            }
        }
    }

    private function recordingController(string $content): RecordingEnvironmentSecurityController
    {
        $controller = new RecordingEnvironmentSecurityController('security', RedirectManager::getInstance());
        $controller->files = [
            RecordingEnvironmentSecurityController::ENV_PATH => $content,
            RecordingEnvironmentSecurityController::NEIGHBOR_PATH => "neighbor-bytes\n",
        ];
        $controller->modes = [
            RecordingEnvironmentSecurityController::ENV_PATH => 0100640,
            RecordingEnvironmentSecurityController::NEIGHBOR_PATH => 0100604,
        ];

        return $controller;
    }

    private function runSecretAction(SecurityController $controller, string $secretType): int
    {
        return $secretType === 'token'
            ? $controller->actionGenerateApiToken()
            : $controller->actionGenerateSalt();
    }

    private function environmentVariable(string $secretType): string
    {
        return $secretType === 'token' ? 'REDIRECT_MANAGER_API_TOKEN' : 'REDIRECT_MANAGER_IP_SALT';
    }

    private function assertGeneratedAssignment(string $content, string $secretType): void
    {
        $pattern = $secretType === 'token'
            ? '/REDIRECT_MANAGER_API_TOKEN="[A-Za-z0-9_-]{43}"/'
            : '/REDIRECT_MANAGER_IP_SALT="[0-9a-f]{64}"/';
        self::assertMatchesRegularExpression($pattern, $content);
    }

    private function assertManualAssignment(string $output, string $secretType): void
    {
        self::assertMatchesRegularExpression(
            $secretType === 'token'
                ? '/REDIRECT_MANAGER_API_TOKEN="[A-Za-z0-9_-]{43}"/'
                : '/REDIRECT_MANAGER_IP_SALT="[0-9a-f]{64}"/',
            $output,
        );
    }

    private function assertSuccessfulAtomicReplacement(
        RecordingEnvironmentSecurityController $controller,
        string $secretType,
        int $expectedMode,
    ): void {
        $renameIndex = array_search('rename', $controller->calls, true);
        self::assertIsInt($renameIndex);
        self::assertTrue($controller->successReported);
        self::assertGreaterThan($renameIndex, $controller->successCallIndex);
        self::assertSame([], $controller->temporaryFiles());
        self::assertSame([], $controller->writesToEnvironmentPath);
        self::assertSame($expectedMode, $controller->modes[RecordingEnvironmentSecurityController::ENV_PATH] & 0777);
        self::assertSame("neighbor-bytes\n", $controller->files[RecordingEnvironmentSecurityController::NEIGHBOR_PATH]);
        self::assertSame(0604, $controller->modes[RecordingEnvironmentSecurityController::NEIGHBOR_PATH] & 0777);
        self::assertStringContainsString('✓ ', $controller->output);
        self::assertStringContainsString($this->environmentVariable($secretType), $controller->output);
    }

    private function assertOriginalStatePreserved(
        RecordingEnvironmentSecurityController $controller,
        string $original,
        int $originalMode,
    ): void {
        self::assertSame($original, $controller->files[RecordingEnvironmentSecurityController::ENV_PATH]);
        self::assertSame($originalMode, $controller->modes[RecordingEnvironmentSecurityController::ENV_PATH] & 0777);
        self::assertSame("neighbor-bytes\n", $controller->files[RecordingEnvironmentSecurityController::NEIGHBOR_PATH]);
        self::assertSame(0604, $controller->modes[RecordingEnvironmentSecurityController::NEIGHBOR_PATH] & 0777);
    }
}

/**
 * Runs the native filesystem boundary against an isolated fixture `.env`.
 *
 * @since 5.41.0
 */
final class NativeEnvironmentSecurityController extends SecurityController
{
    public string $output = '';

    public function __construct(
        string $id,
        \yii\base\Module $module,
        private readonly string $environmentPath,
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function confirm($message, $default = false)
    {
        return true;
    }

    public function stdout($string)
    {
        $this->output .= (string)$string;
        return strlen((string)$string);
    }

    protected function envPath(): string
    {
        return $this->environmentPath;
    }
}

/**
 * In-memory handle for deterministic filesystem failure injection.
 *
 * @since 5.41.0
 */
final class RecordingEnvironmentFileHandle
{
    public string $content = '';
    public bool $closed = false;

    public function __construct(public readonly string $path)
    {
    }
}

/**
 * Records the complete environment replacement protocol without project access.
 *
 * @since 5.41.0
 */
final class RecordingEnvironmentSecurityController extends SecurityController
{
    public const ENV_PATH = '/run-owned-project/.env';
    public const NEIGHBOR_PATH = '/run-owned-project/neighbor.txt';

    public string $output = '';
    /** @var list<bool> */
    public array $confirmationResults = [];
    public ?string $failure = null;
    public bool $successReported = false;
    public int $successCallIndex = -1;
    /** @var array<string, string> */
    public array $files = [];
    /** @var array<string, int> */
    public array $modes = [];
    /** @var list<string> */
    public array $calls = [];
    /** @var list<string> */
    public array $writesToEnvironmentPath = [];
    /** @var list<string> */
    public array $deletedPaths = [];

    public function confirm($message, $default = false)
    {
        $this->calls[] = 'confirm';
        return $this->confirmationResults === [] ? true : array_shift($this->confirmationResults);
    }

    public function stdout($string)
    {
        $string = (string)$string;
        if (str_contains($string, '✓ ')) {
            $this->successReported = true;
            $this->successCallIndex = count($this->calls);
        }
        $this->output .= $string;
        return strlen($string);
    }

    /** @return list<string> */
    public function temporaryFiles(): array
    {
        return array_values(array_filter(
            array_keys($this->files),
            static fn(string $path): bool => !in_array($path, [self::ENV_PATH, self::NEIGHBOR_PATH], true),
        ));
    }

    protected function envPath(): string
    {
        return self::ENV_PATH;
    }

    protected function fileExists(string $path): bool
    {
        return array_key_exists($path, $this->files);
    }

    protected function readFile(string $path): string|false
    {
        $this->calls[] = 'read:' . $path;
        if ($path === self::ENV_PATH && $this->failure === 'read') {
            return false;
        }
        if ($path !== self::ENV_PATH && $this->failure === 'verify-read') {
            return false;
        }

        $content = $this->files[$path] ?? false;
        if ($path !== self::ENV_PATH && $this->failure === 'verify-mismatch' && is_string($content)) {
            return $content . 'mismatch';
        }

        return $content;
    }

    protected function matchEnvironmentAssignment(string $content, string $environmentVariable): int|false
    {
        $this->calls[] = 'match';
        return $this->failure === 'match' ? false : parent::matchEnvironmentAssignment($content, $environmentVariable);
    }

    protected function replaceEnvironmentAssignment(
        string $content,
        string $environmentVariable,
        string $secret,
    ): ?string {
        $this->calls[] = 'replace';
        return $this->failure === 'replace'
            ? null
            : parent::replaceEnvironmentAssignment($content, $environmentVariable, $secret);
    }

    protected function createTemporaryFile(string $directory, string $prefix): string|false
    {
        $this->calls[] = 'create-temp';
        if ($this->failure === 'create') {
            return false;
        }

        $path = ($this->failure === 'wrong-directory' ? '/other-owned-directory' : $directory)
            . DIRECTORY_SEPARATOR
            . $prefix
            . 'fixture';
        $this->files[$path] = '';
        $this->modes[$path] = 0100600;
        return $path;
    }

    protected function openTemporaryFile(string $path): mixed
    {
        $this->calls[] = 'open';
        return $this->failure === 'open' ? false : new RecordingEnvironmentFileHandle($path);
    }

    protected function writeTemporaryFile(mixed $handle, string $content): int|false
    {
        $handle = self::recordingHandle($handle);
        $this->calls[] = 'write';
        if ($handle->path === self::ENV_PATH) {
            $this->writesToEnvironmentPath[] = $content;
        }
        if ($this->failure === 'write') {
            return false;
        }
        if ($this->failure === 'short-write') {
            $written = max(1, strlen($content) - 1);
            $handle->content = substr($content, 0, $written);
            return $written;
        }

        $handle->content = $content;
        return strlen($content);
    }

    protected function flushTemporaryFile(mixed $handle): bool
    {
        self::recordingHandle($handle);
        $this->calls[] = 'flush';
        return $this->failure !== 'flush';
    }

    protected function closeTemporaryFile(mixed $handle): bool
    {
        $handle = self::recordingHandle($handle);
        if ($handle->closed) {
            throw new \RuntimeException('Owned recording environment handle was closed more than once.');
        }
        $handle->closed = true;
        $this->calls[] = 'close';
        $this->files[$handle->path] = $handle->content;
        return $this->failure !== 'close';
    }

    protected function getFileMode(string $path): int|false
    {
        $this->calls[] = 'mode:' . $path;
        if ($path === self::ENV_PATH && $this->failure === 'mode-read') {
            return false;
        }
        if ($path !== self::ENV_PATH && $this->failure === 'mode-verify') {
            return 0100600;
        }

        return $this->modes[$path] ?? false;
    }

    protected function setFileMode(string $path, int $mode): bool
    {
        $this->calls[] = 'chmod';
        if ($this->failure === 'chmod') {
            return false;
        }

        $this->modes[$path] = 0100000 | $mode;
        return true;
    }

    protected function renameFile(string $from, string $to): bool
    {
        $this->calls[] = 'rename';
        if ($this->failure === 'rename') {
            return false;
        }

        $this->files[$to] = $this->files[$from];
        $this->modes[$to] = $this->modes[$from];
        unset($this->files[$from], $this->modes[$from]);
        return true;
    }

    protected function deleteFile(string $path): bool
    {
        $this->calls[] = 'delete';
        $this->deletedPaths[] = $path;
        unset($this->files[$path], $this->modes[$path]);
        return true;
    }

    private static function recordingHandle(mixed $handle): RecordingEnvironmentFileHandle
    {
        if (!$handle instanceof RecordingEnvironmentFileHandle) {
            throw new \RuntimeException('Expected an owned recording environment handle.');
        }

        return $handle;
    }
}

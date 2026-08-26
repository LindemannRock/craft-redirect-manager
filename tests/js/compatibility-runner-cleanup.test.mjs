import assert from 'node:assert/strict';
import {spawn, spawnSync} from 'node:child_process';
import {chmodSync, cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync} from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';

const packageRoot = path.resolve(import.meta.dirname, '../..');
const runnerSource = path.join(packageRoot, 'scripts/test-craft-compat');

function executable(pathname, source) {
    writeFileSync(pathname, source, {mode: 0o700});
    chmodSync(pathname, 0o700);
}

function fixture({
    install = false,
    failureMatch = '',
    cleanupExit = 0,
    waitCreate = false,
    requestedPhp = '8.3',
    actualPhp = requestedPhp,
} = {}) {
    const root = mkdtempSync(path.join(os.tmpdir(), 'redirect-manager-compat-runner-'));
    const fixturePackageRoot = path.join(root, 'package');
    const binRoot = path.join(root, 'bin');
    const tempRoot = path.join(root, 'compat-temp');
    const resourceRoot = path.join(root, 'ddev-resources');
    const logPath = path.join(root, 'commands.log');
    mkdirSync(path.join(fixturePackageRoot, 'scripts'), {recursive: true});
    mkdirSync(binRoot, {recursive: true});
    mkdirSync(tempRoot, {recursive: true});
    mkdirSync(resourceRoot, {recursive: true});
    cpSync(runnerSource, path.join(fixturePackageRoot, 'scripts/test-craft-compat'));
    writeFileSync(path.join(fixturePackageRoot, 'composer.json'), JSON.stringify({
        name: 'lindemannrock/craft-redirect-manager',
        type: 'craft-plugin',
        extra: {handle: 'redirect-manager'},
    }));
    executable(path.join(fixturePackageRoot, 'scripts/smoke-test'), '#!/bin/sh\nexit 0\n');
    writeFileSync(path.join(tempRoot, 'unrelated-sentinel.txt'), 'owner temp\n');
    writeFileSync(path.join(resourceRoot, 'unrelated-sentinel.txt'), 'owner ddev\n');

    executable(path.join(binRoot, 'composer'), `#!/bin/bash
printf 'composer:%s\\n' "$*" >> "$REDIRECT_MANAGER_COMPAT_TEST_LOG"
if [[ "$1" == "create-project" ]]; then
  project_dir="$3"
  mkdir -p "$project_dir"
  printf '{"require-dev":{"fixture":"1"}}\\n' > "$project_dir/composer.json"
  if [[ "$REDIRECT_MANAGER_COMPAT_WAIT_CREATE" == "1" ]]; then
    trap 'exit 143' TERM
    while :; do sleep 1; done
  fi
fi
if [[ -n "$REDIRECT_MANAGER_COMPAT_FAIL_MATCH" && "composer $*" == *"$REDIRECT_MANAGER_COMPAT_FAIL_MATCH"* ]]; then exit 41; fi
exit 0
`);
    executable(path.join(binRoot, 'ddev'), `#!/bin/bash
printf 'ddev:%s\\n' "$*" >> "$REDIRECT_MANAGER_COMPAT_TEST_LOG"
if [[ "$1" == "config" ]]; then
  for argument in "$@"; do
    case "$argument" in --project-name=*) project_name="\${argument#*=}" ;; esac
  done
  case "$project_name" in
    ""|-*|*-|*[!a-z0-9-]*) exit 42 ;;
  esac
  printf '%s\\n' "$project_name" > "$REDIRECT_MANAGER_DDEV_RESOURCE_ROOT/current-project"
  mkdir -p "$REDIRECT_MANAGER_DDEV_RESOURCE_ROOT/$project_name"
fi
if [[ "$1" == "delete" ]]; then
  project_name="\${@: -1}"
  if [[ "$REDIRECT_MANAGER_COMPAT_CLEANUP_EXIT" -ne 0 ]]; then exit "$REDIRECT_MANAGER_COMPAT_CLEANUP_EXIT"; fi
  rm -rf "$REDIRECT_MANAGER_DDEV_RESOURCE_ROOT/$project_name"
  rm -f "$REDIRECT_MANAGER_DDEV_RESOURCE_ROOT/current-project"
  exit 0
fi
if [[ "$1" == "exec" && "$2" == "test" ]]; then exit 0; fi
if [[ "$1" == "exec" && "$2" == "php" ]]; then
  case "$4" in
    *PHP_MAJOR_VERSION*) printf '%s' "$REDIRECT_MANAGER_COMPAT_ACTUAL_PHP_ROW" ;;
    *PHP_VERSION*) printf '%s' "$REDIRECT_MANAGER_COMPAT_ACTUAL_PHP_FULL" ;;
  esac
  exit 0
fi
if [[ -n "$REDIRECT_MANAGER_COMPAT_FAIL_MATCH" && "ddev $*" == *"$REDIRECT_MANAGER_COMPAT_FAIL_MATCH"* ]]; then exit 41; fi
if [[ "$1 $2" == "craft plugin/list" ]]; then
  printf ' redirect-manager fixture Yes Yes\\n'
fi
exit 0
`);

    const argumentsList = ['^5.10', 'dev-main'];
    if (install) {
        argumentsList.push('--install');
    }
    argumentsList.push('--php-version', requestedPhp);
    const environment = {
        ...process.env,
        PATH: `${binRoot}:${process.env.PATH}`,
        CRAFT_COMPAT_TEMP_ROOT: tempRoot,
        REDIRECT_MANAGER_COMPAT_TEST_LOG: logPath,
        REDIRECT_MANAGER_COMPAT_FAIL_MATCH: failureMatch,
        REDIRECT_MANAGER_COMPAT_CLEANUP_EXIT: String(cleanupExit),
        REDIRECT_MANAGER_COMPAT_WAIT_CREATE: waitCreate ? '1' : '0',
        REDIRECT_MANAGER_DDEV_RESOURCE_ROOT: resourceRoot,
        REDIRECT_MANAGER_COMPAT_ACTUAL_PHP_ROW: actualPhp,
        REDIRECT_MANAGER_COMPAT_ACTUAL_PHP_FULL: `${actualPhp}.30`,
    };
    const command = '/bin/bash';
    const args = [path.join(fixturePackageRoot, 'scripts/test-craft-compat'), ...argumentsList];

    return {
        root,
        tempRoot,
        resourceRoot,
        logPath,
        run() {
            return spawnSync(command, args, {cwd: fixturePackageRoot, env: environment, encoding: 'utf8'});
        },
        spawn() {
            return spawn(command, args, {
                cwd: fixturePackageRoot,
                env: environment,
                detached: true,
                stdio: ['ignore', 'pipe', 'pipe'],
            });
        },
        log() {
            return existsSync(logPath) ? readFileSync(logPath, 'utf8') : '';
        },
        assertOwnedStateRemoved() {
            assert.deepEqual(readdirSync(tempRoot), ['unrelated-sentinel.txt']);
            assert.deepEqual(readdirSync(resourceRoot), ['unrelated-sentinel.txt']);
            assert.equal(readFileSync(path.join(tempRoot, 'unrelated-sentinel.txt'), 'utf8'), 'owner temp\n');
            assert.equal(readFileSync(path.join(resourceRoot, 'unrelated-sentinel.txt'), 'utf8'), 'owner ddev\n');
        },
        cleanup() {
            rmSync(root, {recursive: true, force: true});
        },
    };
}

async function waitForLog(current, pattern) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (pattern.test(current.log())) {
            return;
        }
        await new Promise((resolve) => setTimeout(resolve, 20));
    }
    throw new Error(`Timed out waiting for compatibility runner log:\n${current.log()}`);
}

test('Composer-only success and failure remove only the generated project', async (context) => {
    for (const [name, failureMatch, expected] of [
        ['success', '', 0],
        ['dependency failure', 'composer require craftcms/cms', 41],
    ]) {
        await context.test(name, () => {
            const current = fixture({failureMatch});
            try {
                const result = current.run();
                assert.equal(result.status, expected, result.stderr);
                current.assertOwnedStateRemoved();
                assert.doesNotMatch(result.stdout, /Project left at/);
            } finally {
                current.cleanup();
            }
        });
    }
});

test('install mode preserves the Craft plugin smoke path and cleans every partial DDEV path', async (context) => {
    for (const [name, failureMatch, expected] of [
        ['success', '', 0],
        ['config failure', 'ddev config ', 41],
        ['start failure', 'ddev start', 41],
        ['Craft install failure', 'ddev craft install', 41],
        ['plugin install failure', 'ddev craft plugin/install', 41],
        ['plugin smoke failure', 'ddev exec env', 41],
    ]) {
        await context.test(name, () => {
            const current = fixture({install: true, failureMatch});
            try {
                const result = current.run();
                assert.equal(result.status, expected, `${result.stdout}\n${result.stderr}`);
                assert.match(current.log(), /ddev:delete -Oy compat-redirect-manager-/);
                if (failureMatch === '' || failureMatch === 'ddev exec env') {
                    assert.match(result.stdout, / redirect-manager fixture Yes Yes/);
                    assert.match(result.stdout, /Running package smoke test: \.craft-compat\/smoke-test/);
                    assert.match(current.log(), /ddev:exec env PLUGIN_NAME=lindemannrock\/craft-redirect-manager PLUGIN_HANDLE=redirect-manager PLUGIN_TYPE=craft-plugin bash \.craft-compat\/smoke-test/);
                }
                current.assertOwnedStateRemoved();
            } finally {
                current.cleanup();
            }
        });
    }
});

test('requested PHP row is verified against the observed DDEV runtime before mandatory smoke', async (context) => {
    await context.test('matching row reaches package smoke', () => {
        const current = fixture({install: true, requestedPhp: '8.4', actualPhp: '8.4'});
        try {
            const result = current.run();
            assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
            assert.match(result.stdout, /Actual DDEV PHP: 8\.4\.30/);
            assert.match(current.log(), /ddev:config .*--php-version=8\.4/);
            assert.match(current.log(), /ddev:exec env .*bash \.craft-compat\/smoke-test/);
            current.assertOwnedStateRemoved();
        } finally {
            current.cleanup();
        }
    });

    await context.test('mismatched row fails before package smoke', () => {
        const current = fixture({install: true, requestedPhp: '8.3', actualPhp: '8.4'});
        try {
            const result = current.run();
            assert.equal(result.status, 1, `${result.stdout}\n${result.stderr}`);
            assert.match(result.stderr, /DDEV PHP row mismatch: requested 8\.3, got 8\.4/);
            assert.doesNotMatch(current.log(), /ddev:exec env .*bash \.craft-compat\/smoke-test/);
            current.assertOwnedStateRemoved();
        } finally {
            current.cleanup();
        }
    });
});

test('signal cleanup returns signal status and removes the exact generated project', async () => {
    const current = fixture({waitCreate: true});
    try {
        const child = current.spawn();
        await waitForLog(current, /composer:create-project/);
        process.kill(-child.pid, 'SIGTERM');
        const result = await new Promise((resolve) => child.once('close', (code, signal) => resolve({code, signal})));
        assert.ok(result.code === 143 || result.signal === 'SIGTERM', JSON.stringify(result));
        current.assertOwnedStateRemoved();
    } finally {
        current.cleanup();
    }
});

test('cleanup failure never hides the primary status and fails clean success', async (context) => {
    for (const [name, failureMatch, expected] of [
        ['primary failure', 'ddev craft install', 41],
        ['cleanup-only failure', '', 88],
    ]) {
        await context.test(name, () => {
            const current = fixture({install: true, failureMatch, cleanupExit: 88});
            try {
                const result = current.run();
                assert.equal(result.status, expected);
                assert.match(result.stderr, /Failed to remove owned DDEV project .* \(exit 88\)/);
                assert.deepEqual(readdirSync(current.tempRoot), ['unrelated-sentinel.txt']);
                assert.equal(readFileSync(path.join(current.resourceRoot, 'unrelated-sentinel.txt'), 'utf8'), 'owner ddev\n');
            } finally {
                current.cleanup();
            }
        });
    }
});

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

function fixture({install = false, keep = false, failureMatch = '', cleanupExit = 0, waitMatch = ''} = {}) {
    const root = mkdtempSync(path.join(os.tmpdir(), 'redirect-manager-compat-runner-'));
    const fixturePackageRoot = path.join(root, 'package');
    const binRoot = path.join(root, 'bin');
    const tempRoot = path.join(root, 'compat temp [owned]');
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
printf 'composer:%s\n' "$*" >> "$REDIRECT_MANAGER_COMPAT_TEST_LOG"
if [[ "$1" == "create-project" ]]; then
  project_dir="$3"
  mkdir -p "$project_dir"
  printf '{"require-dev":{"fixture":"1"}}\n' > "$project_dir/composer.json"
  printf '{"packages":[]}\n' > "$project_dir/composer.lock"
fi
if [[ -n "$REDIRECT_MANAGER_COMPAT_WAIT_MATCH" && "composer $*" == *"$REDIRECT_MANAGER_COMPAT_WAIT_MATCH"* ]]; then
  trap 'exit 130' INT
  trap 'exit 143' TERM
  trap 'exit 129' HUP
  while :; do sleep 1; done
fi
if [[ -n "$REDIRECT_MANAGER_COMPAT_FAIL_MATCH" && "composer $*" == *"$REDIRECT_MANAGER_COMPAT_FAIL_MATCH"* ]]; then exit 41; fi
exit 0
`);
    executable(path.join(binRoot, 'ddev'), `#!/bin/bash
printf 'ddev:%s\n' "$*" >> "$REDIRECT_MANAGER_COMPAT_TEST_LOG"
if [[ "$1" == "delete" ]]; then
  project_name="\${@: -1}"
  if [[ "$REDIRECT_MANAGER_COMPAT_CLEANUP_EXIT" -ne 0 ]]; then exit "$REDIRECT_MANAGER_COMPAT_CLEANUP_EXIT"; fi
  rm -rf "$REDIRECT_MANAGER_DDEV_RESOURCE_ROOT/$project_name"
  exit 0
fi
if [[ "$1" == "config" ]]; then
  for argument in "$@"; do
    case "$argument" in --project-name=*) project_name="\${argument#*=}" ;; esac
  done
  case "$project_name" in ""|-*|*-|*[!a-z0-9-]*) exit 42 ;; esac
  mkdir -p "$REDIRECT_MANAGER_DDEV_RESOURCE_ROOT/$project_name"
fi
if [[ -n "$REDIRECT_MANAGER_COMPAT_WAIT_MATCH" && "ddev $*" == *"$REDIRECT_MANAGER_COMPAT_WAIT_MATCH"* ]]; then
  trap 'exit 130' INT
  trap 'exit 143' TERM
  trap 'exit 129' HUP
  while :; do sleep 1; done
fi
if [[ -n "$REDIRECT_MANAGER_COMPAT_FAIL_MATCH" && "ddev $*" == *"$REDIRECT_MANAGER_COMPAT_FAIL_MATCH"* ]]; then exit 41; fi
if [[ "$1 $2" == "craft plugin/list" ]]; then
  printf ' redirect-manager fixture Yes Yes\n'
fi
exit 0
`);

    const argumentsList = ['^5.10', 'dev-main'];
    if (install) argumentsList.push('--install');
    if (keep) argumentsList.push('--keep-project');
    const environment = {
        ...process.env,
        PATH: `${binRoot}:${process.env.PATH}`,
        CRAFT_COMPAT_TEMP_ROOT: tempRoot,
        REDIRECT_MANAGER_COMPAT_TEST_LOG: logPath,
        REDIRECT_MANAGER_COMPAT_FAIL_MATCH: failureMatch,
        REDIRECT_MANAGER_COMPAT_CLEANUP_EXIT: String(cleanupExit),
        REDIRECT_MANAGER_COMPAT_WAIT_MATCH: waitMatch,
        REDIRECT_MANAGER_DDEV_RESOURCE_ROOT: resourceRoot,
    };
    const command = '/bin/bash';
    const args = [path.join(fixturePackageRoot, 'scripts/test-craft-compat'), ...argumentsList];

    return {
        root,
        tempRoot,
        resourceRoot,
        run() {
            return spawnSync(command, args, {cwd: fixturePackageRoot, env: environment, encoding: 'utf8'});
        },
        spawn() {
            return spawn(command, args, {cwd: fixturePackageRoot, env: environment, detached: true, stdio: ['ignore', 'pipe', 'pipe']});
        },
        log() {
            return existsSync(logPath) ? readFileSync(logPath, 'utf8') : '';
        },
        projectPaths() {
            return readdirSync(tempRoot)
                .filter((entry) => entry.startsWith('craft-compat-redirect-manager-'))
                .map((entry) => path.join(tempRoot, entry));
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
    for (let attempt = 0; attempt < 150; attempt++) {
        if (pattern.test(current.log())) return;
        await new Promise((resolve) => setTimeout(resolve, 20));
    }
    throw new Error(`Timed out waiting for compatibility runner log:\n${current.log()}`);
}

test('Composer-only success and failure remove only the exact generated project', async (context) => {
    for (const [name, failureMatch, expected] of [
        ['success', '', 0],
        ['dependency failure', 'composer require craftcms/cms', 41],
    ]) {
        await context.test(name, () => {
            const current = fixture({failureMatch});
            try {
                const result = current.run();
                assert.equal(result.status, expected, `${result.stdout}\n${result.stderr}`);
                current.assertOwnedStateRemoved();
                assert.doesNotMatch(result.stdout, /Project (?:left|kept) at/);
            } finally {
                current.cleanup();
            }
        });
    }
});

test('install success and failures clean the exact partial DDEV lifecycle', async (context) => {
    for (const [name, failureMatch, expected] of [
        ['success', '', 0],
        ['configuration failure', 'ddev config ', 41],
        ['start failure', 'ddev start', 41],
        ['Craft install failure', 'ddev craft install', 41],
        ['plugin install failure', 'ddev craft plugin/install', 41],
        ['smoke failure', 'ddev exec env', 41],
    ]) {
        await context.test(name, () => {
            const current = fixture({install: true, failureMatch});
            try {
                const result = current.run();
                assert.equal(result.status, expected, `${result.stdout}\n${result.stderr}`);
                assert.match(current.log(), /ddev:delete -Oy craft-compat-redirect-manager-/);
                current.assertOwnedStateRemoved();
            } finally {
                current.cleanup();
            }
        });
    }
});

test('SIGINT, SIGTERM, and SIGHUP preserve signal exit codes and clean owned paths', async (context) => {
    for (const [signal, expected] of [['SIGINT', 130], ['SIGTERM', 143], ['SIGHUP', 129]]) {
        await context.test(signal, async () => {
            const current = fixture({waitMatch: 'composer create-project'});
            try {
                const child = current.spawn();
                await waitForLog(current, /composer:create-project/);
                process.kill(-child.pid, signal);
                const result = await new Promise((resolve) => child.once('close', (code, closedSignal) => resolve({code, signal: closedSignal})));
                assert.ok(result.code === expected || result.signal === signal, JSON.stringify(result));
                current.assertOwnedStateRemoved();
            } finally {
                current.cleanup();
            }
        });
    }
});

test('signal after DDEV configuration removes only that project and its directory', async () => {
    const current = fixture({install: true, waitMatch: 'ddev start'});
    try {
        const child = current.spawn();
        await waitForLog(current, /ddev:start/);
        process.kill(-child.pid, 'SIGTERM');
        const result = await new Promise((resolve) => child.once('close', (code, signal) => resolve({code, signal})));
        assert.ok(result.code === 143 || result.signal === 'SIGTERM', JSON.stringify(result));
        assert.match(current.log(), /ddev:delete -Oy craft-compat-redirect-manager-/);
        current.assertOwnedStateRemoved();
    } finally {
        current.cleanup();
    }
});

test('diagnostic keep is distinct and retains only the exact requested run', async (context) => {
    for (const install of [false, true]) {
        await context.test(install ? 'install' : 'Composer-only', () => {
            const current = fixture({install, keep: true});
            try {
                const result = current.run();
                assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
                assert.match(result.stdout, /Project kept at:/);
                assert.equal(current.projectPaths().length, 1);
                if (install) {
                    assert.equal(readdirSync(current.resourceRoot).filter((entry) => entry !== 'unrelated-sentinel.txt').length, 1);
                    assert.doesNotMatch(current.log(), /ddev:delete/);
                }
            } finally {
                current.cleanup();
            }
        });
    }
});

test('repeated diagnostic runs receive collision-safe project directories', () => {
    const current = fixture({keep: true});
    try {
        const first = current.run();
        const second = current.run();
        assert.equal(first.status, 0, `${first.stdout}\n${first.stderr}`);
        assert.equal(second.status, 0, `${second.stdout}\n${second.stderr}`);
        assert.equal(current.projectPaths().length, 2);
        assert.equal(new Set(current.projectPaths()).size, 2);
    } finally {
        current.cleanup();
    }
});

test('cleanup failure is reported and cannot turn retained resources into success', async (context) => {
    for (const [name, failureMatch, expected] of [
        ['primary failure remains primary', 'ddev craft install', 41],
        ['cleanup-only failure is nonzero', '', 88],
    ]) {
        await context.test(name, () => {
            const current = fixture({install: true, failureMatch, cleanupExit: 88});
            try {
                const result = current.run();
                assert.equal(result.status, expected);
                assert.match(result.stderr, /Failed to remove owned DDEV project .* \(exit 88\)/);
                assert.deepEqual(readdirSync(current.tempRoot), ['unrelated-sentinel.txt']);
                assert.equal(readFileSync(path.join(current.resourceRoot, 'unrelated-sentinel.txt'), 'utf8'), 'owner ddev\n');
                assert.equal(readdirSync(current.resourceRoot).length, 2);
            } finally {
                current.cleanup();
            }
        });
    }
});

test('help documents diagnostic retention separately from project dev dependencies', () => {
    const result = spawnSync('/bin/bash', [runnerSource, '--help'], {cwd: packageRoot, encoding: 'utf8'});
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stdout, /--keep-project\b/);
    assert.match(result.stdout, /separate from --keep-project-dev/);
});

import assert from 'node:assert/strict';
import {execFileSync, spawnSync} from 'node:child_process';
import {chmodSync, cpSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync} from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';

const pluginRoot = path.resolve(import.meta.dirname, '../..');
const hookSource = path.join(pluginRoot, '.githooks/pre-commit');

function executable(pathname, source) {
    writeFileSync(pathname, source, {mode: 0o700});
    chmodSync(pathname, 0o700);
}

function fixture({workspace = false, ddevExit = 0, platformExit = 0, qualityExit = 0, ciExit = 0} = {}) {
    const root = mkdtempSync(path.join(os.tmpdir(), 'redirect-manager-hook-'));
    const packageRoot = workspace ? path.join(root, 'plugins/redirect-manager') : path.join(root, 'redirect-manager');
    const binRoot = path.join(root, 'bin');
    const logPath = path.join(root, 'commands.log');
    mkdirSync(path.join(packageRoot, '.githooks'), {recursive: true});
    mkdirSync(binRoot, {recursive: true});
    cpSync(hookSource, path.join(packageRoot, '.githooks/pre-commit'));
    writeFileSync(path.join(packageRoot, 'sentinel.txt'), 'must remain byte-identical\n');
    if (workspace) {
        mkdirSync(path.join(root, '.ddev'), {recursive: true});
        writeFileSync(path.join(root, '.ddev/config.yaml'), 'php_version: "8.3"\n');
    }
    executable(path.join(binRoot, 'ddev'), `#!/bin/sh\nprintf 'ddev:%s\\n' "$*" >> "$REDIRECT_MANAGER_HOOK_TEST_LOG"\nexit ${ddevExit}\n`);
    executable(path.join(binRoot, 'php'), `#!/bin/sh\nprintf 'php:%s\\n' "$*" >> "$REDIRECT_MANAGER_HOOK_TEST_LOG"\nif [ "$1" = "-r" ]; then printf '8.3.30'; exit 0; fi\nif [ "$1" = "scripts/check-quality-platform.php" ]; then exit ${qualityExit}; fi\nexit 92\n`);
    executable(path.join(binRoot, 'composer'), `#!/bin/sh\nprintf 'composer:%s\\n' "$*" >> "$REDIRECT_MANAGER_HOOK_TEST_LOG"\nif [ "$1" = "check-platform-reqs" ]; then exit ${platformExit}; fi\nif [ "$1" = "ci" ]; then exit ${ciExit}; fi\nexit 91\n`);
    const environment = {...process.env, PATH: `${binRoot}:/usr/bin:/bin`, REDIRECT_MANAGER_HOOK_TEST_LOG: logPath};
    const snapshot = () => execFileSync('/usr/bin/find', [packageRoot, '-type', 'f', '-exec', '/usr/bin/shasum', '-a', '256', '{}', ';'], {encoding: 'utf8'}).trim().split('\n').sort().join('\n');
    const before = snapshot();
    return {
        root, packageRoot, before, snapshot,
        run: () => spawnSync('/bin/bash', [path.join(packageRoot, '.githooks/pre-commit')], {cwd: packageRoot, encoding: 'utf8', env: environment}),
        log: () => { try { return readFileSync(logPath, 'utf8'); } catch { return ''; } },
        cleanup: () => rmSync(root, {recursive: true, force: true}),
    };
}

function assertNoMutation(current) {
    assert.equal(current.snapshot(), current.before);
    assert.deepEqual(readdirSync(current.packageRoot).sort(), ['.githooks', 'sentinel.txt']);
}

test('workspace runs only read-only composer ci through DDEV', () => {
    const current = fixture({workspace: true});
    try {
        const result = current.run();
        assert.equal(result.status, 0, result.stderr);
        assert.match(current.log(), /^ddev:exec cd plugins\/redirect-manager .*composer ci$/m);
        assert.doesNotMatch(current.log(), /^(?:php|composer):/m);
        assert.doesNotMatch(current.log(), /fix|phpunit|npm|node|act/i);
        assertNoMutation(current);
    } finally { current.cleanup(); }
});

test('workspace failure propagates without host fallback or mutation', () => {
    const current = fixture({workspace: true, ddevExit: 37});
    try {
        assert.equal(current.run().status, 37);
        assert.doesNotMatch(current.log(), /^(?:php|composer):/m);
        assertNoMutation(current);
    } finally { current.cleanup(); }
});

test('standalone validates platform and quality-tool compatibility before composer ci', () => {
    const current = fixture();
    try {
        assert.equal(current.run().status, 0);
        assert.match(current.log(), /^composer:check-platform-reqs --no-interaction$/m);
        assert.match(current.log(), /^php:scripts\/check-quality-platform.php$/m);
        assert.match(current.log(), /^composer:ci$/m);
        assertNoMutation(current);
    } finally { current.cleanup(); }
});

for (const [label, options, status] of [
    ['platform', {platformExit: 42}, 42],
    ['quality tool', {qualityExit: 43}, 43],
    ['composer ci', {ciExit: 44}, 44],
]) {
    test(`standalone ${label} failure propagates without mutation`, () => {
        const current = fixture(options);
        try {
            assert.equal(current.run().status, status);
            assertNoMutation(current);
        } finally { current.cleanup(); }
    });
}

import assert from 'node:assert/strict';
import {spawn, spawnSync} from 'node:child_process';
import {chmodSync, cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync} from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import {pathToFileURL} from 'node:url';

import {checkBuildParity, checkPackageExport, packageRoot, validateArchiveMembers} from '../../scripts/package-boundaries.mjs';

const childCloseTimedOut = Symbol('child close timed out');

async function waitForPath(ownedPath, description, timeoutMs = 5000) {
    const deadline = Date.now() + timeoutMs;
    while (!existsSync(ownedPath)) {
        if (Date.now() >= deadline) throw new Error(`Timed out waiting for ${description}.`);
        await new Promise((resolve) => setTimeout(resolve, 20));
    }
}

async function waitForChildClose(childClosed, timeoutMs = 5000) {
    let timeout;
    try {
        return await Promise.race([
            childClosed,
            new Promise((resolve) => { timeout = setTimeout(() => resolve(childCloseTimedOut), timeoutMs); }),
        ]);
    } finally {
        clearTimeout(timeout);
    }
}

function signalDetachedChild(child, signal) {
    try {
        process.kill(-child.pid, signal);
    } catch (error) {
        if (error.code !== 'ESRCH') throw error;
    }
}

function removeExactCandidateArchive(temporaryPath) {
    const resolvedPath = path.resolve(temporaryPath);
    if (path.dirname(resolvedPath) !== path.resolve(os.tmpdir())
        || !/^redirect-manager-package-export-[A-Za-z0-9]+$/.test(path.basename(resolvedPath))) {
        throw new Error(`Refusing to remove unexpected candidate archive path: ${temporaryPath}`);
    }
    rmSync(resolvedPath, {recursive: true, force: true});
}

function packageFixture() {
    const root = mkdtempSync(path.join(os.tmpdir(), 'redirect-manager-candidate-'));
    const repository = path.join(root, 'repository');
    const clone = spawnSync('git', ['clone', '--no-hardlinks', '--quiet', packageRoot, repository], {encoding: 'utf8'});
    assert.equal(clone.status, 0, clone.stderr);
    return {root, repository};
}

function gitState(repository) {
    const git = (...argumentsList) => {
        const result = spawnSync('git', argumentsList, {
            cwd: repository,
            encoding: 'utf8',
            env: {...process.env, GIT_OPTIONAL_LOCKS: '0'},
        });
        assert.equal(result.status, 0, result.stderr);
        return result.stdout;
    };
    const indexPath = path.resolve(repository, git('rev-parse', '--git-path', 'index').trim());
    return {
        index: readFileSync(indexPath),
        status: git('status', '--porcelain=v2', '--untracked-files=all'),
        staged: git('diff', '--cached', '--binary'),
        objects: git('count-objects', '-v'),
    };
}

test('locked analytics build reproduces committed dist without mutating source', () => {
    const output = 'src/web/assets/analytics/dist/analytics.js';
    const before = readFileSync(path.join(packageRoot, output));
    assert.equal(checkBuildParity(), output);
    assert.deepEqual(readFileSync(path.join(packageRoot, output)), before);
});

test('stale analytics output fails and removes only its owned build directory', () => {
    const fixtureRoot = mkdtempSync(path.join(os.tmpdir(), 'redirect-manager-stale-build-'));
    let temporaryPath = '';
    try {
        for (const relativePath of ['package.json', 'src/web/assets/package.json', 'src/web/assets/package-lock.json', 'src/web/assets/analytics/src', 'src/web/assets/analytics/dist']) {
            const destination = path.join(fixtureRoot, relativePath);
            mkdirSync(path.dirname(destination), {recursive: true});
            cpSync(path.join(packageRoot, relativePath), destination, {recursive: true});
        }
        writeFileSync(path.join(fixtureRoot, 'src/web/assets/analytics/dist/analytics.js'), 'stale\n');
        assert.throws(() => checkBuildParity(fixtureRoot, {onTemporaryPath: (value) => { temporaryPath = value; }}), /stale/);
        assert.notEqual(temporaryPath, '');
        assert.equal(existsSync(temporaryPath), false);
        assert.equal(readFileSync(path.join(fixtureRoot, 'src/web/assets/analytics/dist/analytics.js'), 'utf8'), 'stale\n');
    } finally { rmSync(fixtureRoot, {recursive: true, force: true}); }
});

test('customer archive preserves the approved 100-file runtime boundary', () => {
    const before = gitState(packageRoot);
    let temporaryPath = '';
    const files = checkPackageExport(packageRoot, {onTemporaryPath: (value) => { temporaryPath = value; }});
    assert.equal(files.length, 100);
    assert.equal(files.includes('composer.json'), true);
    assert.equal(files.includes('tests/TestCase.php'), false);
    assert.equal(files.includes('src/presenters/StorageWarningPresentation.php'), true);
    assert.equal(files.includes('src/web/assets/analytics/dist/analytics.js'), true);
    assert.equal(files.includes('src/services/analytics/AnalyticsMaintenanceService.php'), true);
    assert.equal(files.includes('src/services/ScheduledBackupScheduler.php'), true);
    assert.notEqual(temporaryPath, '');
    assert.equal(existsSync(temporaryPath), false);
    assert.deepEqual(gitState(packageRoot), before);
});

test('archive validation rejects development leakage and missing runtime output', () => {
    assert.throws(() => validateArchiveMembers(['composer.json', 'tests/TestCase.php']), /development files/);
});

test('candidate archive contains current tracked bytes without changing Git state', () => {
    const current = packageFixture();
    let temporaryPath = '';
    try {
        const runtimePath = path.join(current.repository, 'src/RedirectManager.php');
        const marker = '// current candidate bytes';
        writeFileSync(runtimePath, `${readFileSync(runtimePath, 'utf8')}\n${marker}\n`);
        const before = gitState(current.repository);
        checkPackageExport(current.repository, {
            onTemporaryPath: (value) => { temporaryPath = value; },
            inspectArchive: (archivePath) => {
                const extracted = spawnSync('tar', ['-xOf', archivePath, 'src/RedirectManager.php'], {encoding: 'utf8'});
                assert.equal(extracted.status, 0, extracted.stderr);
                assert.match(extracted.stdout, /current candidate bytes/);
            },
        });
        assert.equal(existsSync(temporaryPath), false);
        assert.deepEqual(gitState(current.repository), before);
    } finally {
        rmSync(current.root, {recursive: true, force: true});
    }
});

test('eligible untracked runtime files cannot be hidden by committed HEAD', () => {
    const current = packageFixture();
    let temporaryPath = '';
    try {
        writeFileSync(path.join(current.repository, 'src/CurrentCandidateRuntime.php'), '<?php\n');
        assert.throws(
            () => checkPackageExport(current.repository, {onTemporaryPath: (value) => { temporaryPath = value; }}),
            /approved 100-file boundary: 101/,
        );
        assert.notEqual(temporaryPath, '');
        assert.equal(existsSync(temporaryPath), false);
    } finally {
        rmSync(current.root, {recursive: true, force: true});
    }
});

test('deleting a required candidate runtime file fails the archive boundary', () => {
    const current = packageFixture();
    try {
        rmSync(path.join(current.repository, 'src/RedirectManager.php'));
        assert.throws(
            () => checkPackageExport(current.repository),
            /missing runtime file: src\/RedirectManager\.php/,
        );
    } finally {
        rmSync(current.root, {recursive: true, force: true});
    }
});

test('export-ignored candidate development files remain outside the archive', () => {
    const current = packageFixture();
    try {
        writeFileSync(path.join(current.repository, 'tests/UntrackedDevelopmentFile.php'), '<?php\n');
        const files = checkPackageExport(current.repository);
        assert.equal(files.length, 100);
        assert.equal(files.includes('tests/UntrackedDevelopmentFile.php'), false);
    } finally {
        rmSync(current.root, {recursive: true, force: true});
    }
});

test('candidate gitattributes changes control the generated archive', () => {
    const current = packageFixture();
    try {
        const attributesPath = path.join(current.repository, '.gitattributes');
        writeFileSync(attributesPath, `${readFileSync(attributesPath, 'utf8')}\nsrc/RedirectManager.php export-ignore\n`);
        assert.throws(
            () => checkPackageExport(current.repository),
            /missing runtime file: src\/RedirectManager\.php/,
        );
    } finally {
        rmSync(current.root, {recursive: true, force: true});
    }
});

test('interruption removes the exact candidate archive resources', async () => {
    const current = packageFixture();
    const binRoot = path.join(current.root, 'bin');
    const readyPath = path.join(current.root, 'ready.txt');
    const wrapperReadyPath = path.join(current.root, 'wrapper-ready.txt');
    mkdirSync(binRoot);
    const realGit = spawnSync('sh', ['-c', 'command -v git'], {encoding: 'utf8'}).stdout.trim();
    const gitWrapper = path.join(binRoot, 'git');
    writeFileSync(gitWrapper, `#!/bin/sh
if [ "$1" = "read-tree" ]; then
  trap 'exit 143' TERM
  printf 'ready\n' > "$REDIRECT_MANAGER_WRAPPER_READY"
  while :; do sleep 1; done
fi
exec "$REDIRECT_MANAGER_REAL_GIT" "$@"
`, {mode: 0o700});
    chmodSync(gitWrapper, 0o700);
    const moduleUrl = pathToFileURL(path.join(packageRoot, 'scripts/package-boundaries.mjs')).href;
    const childSource = `import {writeFileSync} from 'node:fs'; import {checkPackageExport} from ${JSON.stringify(moduleUrl)}; checkPackageExport(${JSON.stringify(current.repository)}, {onTemporaryPath: (value) => writeFileSync(${JSON.stringify(readyPath)}, value)});`;
    const child = spawn(process.execPath, ['--input-type=module', '-e', childSource], {
        cwd: packageRoot,
        detached: true,
        env: {
            ...process.env,
            PATH: `${binRoot}:${process.env.PATH}`,
            REDIRECT_MANAGER_REAL_GIT: realGit,
            REDIRECT_MANAGER_WRAPPER_READY: wrapperReadyPath,
        },
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    const childClosed = new Promise((resolve) => child.once('close', (code, signal) => resolve({code, signal})));
    let temporaryPath = '';
    try {
        await waitForPath(readyPath, 'candidate archive child to publish its owned path');
        await waitForPath(wrapperReadyPath, 'Git wrapper to install its termination trap');
        temporaryPath = readFileSync(readyPath, 'utf8');
        assert.equal(existsSync(temporaryPath), true);
        signalDetachedChild(child, 'SIGTERM');
        const result = await waitForChildClose(childClosed);
        if (result === childCloseTimedOut) {
            signalDetachedChild(child, 'SIGKILL');
            const forcedResult = await waitForChildClose(childClosed);
            if (forcedResult === childCloseTimedOut) {
                throw new Error('Candidate archive child did not close after its exact process group received SIGKILL.');
            }
            throw new Error('Candidate archive child missed the SIGTERM deadline and required exact process-group SIGKILL cleanup.');
        }
        assert.notEqual(result.code, 0, JSON.stringify(result));
        assert.equal(existsSync(temporaryPath), false);
    } finally {
        let cleanupError;
        try {
            if (child.exitCode === null && child.signalCode === null) {
                signalDetachedChild(child, 'SIGKILL');
                const forcedResult = await waitForChildClose(childClosed);
                if (forcedResult === childCloseTimedOut) {
                    cleanupError = new Error('Candidate archive child did not close during exact fixture cleanup.');
                }
            }
        } catch (error) {
            cleanupError = error;
        }
        try {
            if (temporaryPath === '' && existsSync(readyPath)) temporaryPath = readFileSync(readyPath, 'utf8');
            if (temporaryPath !== '') removeExactCandidateArchive(temporaryPath);
        } catch (error) {
            cleanupError ??= error;
        }
        try {
            rmSync(current.root, {recursive: true, force: true});
        } catch (error) {
            cleanupError ??= error;
        }
        if (cleanupError) throw cleanupError;
    }
});

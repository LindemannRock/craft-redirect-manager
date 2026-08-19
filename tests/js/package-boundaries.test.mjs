import assert from 'node:assert/strict';
import {cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync} from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';

import {checkBuildParity, checkPackageExport, packageRoot, validateArchiveMembers} from '../../scripts/package-boundaries.mjs';

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
    const files = checkPackageExport();
    assert.equal(files.length, 100);
    assert.equal(files.includes('composer.json'), true);
    assert.equal(files.includes('tests/TestCase.php'), false);
    assert.equal(files.includes('src/presenters/StorageWarningPresentation.php'), true);
    assert.equal(files.includes('src/web/assets/analytics/dist/analytics.js'), true);
    assert.equal(files.includes('src/services/analytics/AnalyticsMaintenanceService.php'), true);
    assert.equal(files.includes('src/services/ScheduledBackupScheduler.php'), true);
});

test('archive validation rejects development leakage and missing runtime output', () => {
    assert.throws(() => validateArchiveMembers(['composer.json', 'tests/TestCase.php']), /development files/);
});

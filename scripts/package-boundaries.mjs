import {spawnSync} from 'node:child_process';
import {cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, statSync, symlinkSync} from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

export const packageRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const generatedOutput = 'src/web/assets/analytics/dist/analytics.js';
const activeTemporaryPaths = new Set();
let signalHandlersInstalled = false;

function removeTemporaryPath(temporaryPath) {
    rmSync(temporaryPath, {recursive: true, force: true});
    activeTemporaryPaths.delete(temporaryPath);
}

function installSignalHandlers() {
    if (signalHandlersInstalled) return;
    signalHandlersInstalled = true;
    for (const [signal, status] of [['SIGINT', 130], ['SIGTERM', 143], ['SIGHUP', 129]]) {
        process.once(signal, () => {
            for (const temporaryPath of activeTemporaryPaths) {
                try { removeTemporaryPath(temporaryPath); } catch (error) {
                    process.stderr.write(`Unable to clean package-boundary path ${temporaryPath}: ${error.message}\n`);
                }
            }
            process.exit(status);
        });
    }
}

function ownedTemporaryDirectory(prefix, onTemporaryPath) {
    installSignalHandlers();
    const temporaryPath = mkdtempSync(path.join(os.tmpdir(), prefix));
    activeTemporaryPaths.add(temporaryPath);
    onTemporaryPath?.(temporaryPath);
    return temporaryPath;
}

function installBuildDependencies(sourceRoot, buildRoot) {
    const sourceNodeModules = path.join(sourceRoot, 'src/web/assets/node_modules');
    const sourceTerser = path.join(sourceNodeModules, '.bin/terser');
    if (existsSync(sourceNodeModules) && statSync(sourceNodeModules).isDirectory()
        && spawnSync(sourceTerser, ['--version'], {encoding: 'utf8'}).status === 0) {
        symlinkSync(sourceNodeModules, path.join(buildRoot, 'src/web/assets/node_modules'), 'dir');
        return;
    }
    const install = spawnSync('npm', ['ci', '--prefix', 'src/web/assets'], {cwd: buildRoot, encoding: 'utf8'});
    if (install.error || install.status !== 0) {
        throw new Error(`Locked asset install failed.\n${install.stdout ?? ''}${install.stderr ?? ''}`);
    }
}

export function checkBuildParity(sourceRoot = packageRoot, {onTemporaryPath} = {}) {
    const buildRoot = ownedTemporaryDirectory('redirect-manager-build-parity-', onTemporaryPath);
    try {
        for (const relativePath of [
            'package.json',
            'src/web/assets/package.json',
            'src/web/assets/package-lock.json',
            'src/web/assets/analytics/src',
        ]) {
            const destination = path.join(buildRoot, relativePath);
            mkdirSync(path.dirname(destination), {recursive: true});
            cpSync(path.join(sourceRoot, relativePath), destination, {recursive: true});
        }
        installBuildDependencies(sourceRoot, buildRoot);
        const build = spawnSync('npm', ['run', 'build:analytics'], {cwd: buildRoot, encoding: 'utf8'});
        if (build.error || build.status !== 0) {
            throw new Error(`Analytics build failed.\n${build.stdout ?? ''}${build.stderr ?? ''}`);
        }
        const expected = path.join(sourceRoot, generatedOutput);
        const generated = path.join(buildRoot, generatedOutput);
        if (!existsSync(generated) || !readFileSync(expected).equals(readFileSync(generated))) {
            throw new Error(`Generated analytics asset is stale: ${generatedOutput}`);
        }
        return generatedOutput;
    } finally {
        removeTemporaryPath(buildRoot);
    }
}

export function validateArchiveMembers(members) {
    const files = members.filter((member) => !member.endsWith('/'));
    const forbidden = files.filter((member) => /^(?:ecs\.php|phpstan\.neon|phpunit\.xml\.dist)$/.test(member)
        || /^(?:tests|scripts|\.github|\.githooks|docs)\//.test(member)
        || /^src\/web\/assets\/(?:package(?:-lock)?\.json|node_modules\/)/.test(member)
        || /^src\/web\/assets\/analytics\/src\//.test(member));
    if (forbidden.length > 0) {
        throw new Error(`Customer archive contains development files: ${forbidden.join(', ')}`);
    }
    for (const required of [
        'composer.json',
        'src/RedirectManager.php',
        'src/presenters/StorageWarningPresentation.php',
        'src/services/analytics/AnalyticsMaintenanceService.php',
        'src/services/ScheduledBackupScheduler.php',
        generatedOutput,
    ]) {
        if (!files.includes(required)) throw new Error(`Customer archive is missing runtime file: ${required}`);
    }
    if (files.length !== 100) {
        throw new Error(`Customer archive changed from the approved 100-file boundary: ${files.length}`);
    }
    return files;
}

export function checkPackageExport(sourceRoot = packageRoot, {onTemporaryPath} = {}) {
    const archiveRoot = ownedTemporaryDirectory('redirect-manager-package-export-', onTemporaryPath);
    const archivePath = path.join(archiveRoot, 'package.tar');
    try {
        const archive = spawnSync('git', ['archive', '--worktree-attributes', `--output=${archivePath}`, 'HEAD'], {cwd: sourceRoot, encoding: 'utf8'});
        if (archive.error || archive.status !== 0) throw new Error(`Git archive failed.\n${archive.stderr ?? ''}`);
        const listing = spawnSync('tar', ['-tf', archivePath], {encoding: 'utf8'});
        if (listing.error || listing.status !== 0) throw new Error(`Archive listing failed.\n${listing.stderr ?? ''}`);
        return validateArchiveMembers(listing.stdout.trim().split('\n').filter(Boolean));
    } finally {
        removeTemporaryPath(archiveRoot);
    }
}

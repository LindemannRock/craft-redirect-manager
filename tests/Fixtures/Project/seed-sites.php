<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

use craft\models\Site;

$projectRoot = $_SERVER['REDIRECT_MANAGER_TEST_PROJECT_ROOT'] ?? null;
if (!is_string($projectRoot)
    || preg_match('#^' . preg_quote(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR), '#') . '/redirect-manager-fixture-[a-f0-9]{16}$#', $projectRoot) !== 1) {
    throw new RuntimeException('Site seeding requires the exact disposable project boundary.');
}
require $projectRoot . '/bootstrap.php';
require $projectRoot . '/vendor/craftcms/cms/bootstrap/console.php';

$sites = Craft::$app->getSites();
$primary = $sites->getPrimarySite();
foreach ([
    ['name' => 'Redirect Secondary', 'handle' => 'redirectSecondary', 'language' => 'de-DE', 'baseUrl' => 'https://redirect-secondary.example.test'],
    ['name' => 'Redirect Tertiary', 'handle' => 'redirectTertiary', 'language' => 'fr-FR', 'baseUrl' => 'https://redirect-tertiary.example.test'],
] as $definition) {
    if ($sites->getSiteByHandle($definition['handle']) !== null) {
        continue;
    }
    $site = new Site([
        ...$definition,
        'groupId' => $primary->groupId,
        'primary' => false,
        'enabled' => true,
    ]);
    if (!$sites->saveSite($site)) {
        throw new RuntimeException('Unable to save fixture site: ' . json_encode($site->getErrors()));
    }
}

if (count($sites->getAllSites()) !== 3) {
    throw new RuntimeException('Redirect Manager fixture must contain exactly three sites.');
}

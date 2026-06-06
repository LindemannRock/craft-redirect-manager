<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\console\controllers;

use lindemannrock\base\console\controllers\AbstractHelpController;

/**
 * Console help for Redirect Manager commands.
 *
 * @since 5.32.0
 */
final class HelpController extends AbstractHelpController
{
    /**
     * @inheritdoc
     */
    protected function helpManifest(): array
    {
        return [
            'title' => 'Redirect Manager',
            'pluginHandle' => 'redirect-manager',
            'commandPrefixes' => [
                'php craft',
                'ddev craft',
            ],
            'summary' => 'Use these commands to create, list, and clean redirect backups, run scheduled backup checks, and generate the IP hash salt used for privacy-safe analytics.',
            'common' => [
                'backup/create',
                'backup/list',
                'backup/scheduled',
                'security/generate-salt',
            ],
            'groups' => [
                [
                    'name' => 'backup',
                    'label' => 'Backups',
                    'description' => 'Create, list, schedule-check, and clean redirect backups.',
                    'commands' => [
                        [
                            'path' => 'backup/create',
                            'summary' => 'Create a redirect backup.',
                            'description' => 'Create a backup of all redirects using the configured backup storage. By default, old backups are cleaned afterward when retention is enabled.',
                            'usageOptions' => '[--reason=<reason>] [--clean=<0|1>]',
                            'options' => [
                                [
                                    'name' => '--reason',
                                    'description' => 'Backup reason label. Default: console.',
                                ],
                                [
                                    'name' => '--clean',
                                    'description' => 'Clean old backups after creating the backup. Default: 1.',
                                ],
                            ],
                            'examples' => [
                                'redirect-manager/backup/create',
                                'redirect-manager/backup/create --reason=deploy',
                                'redirect-manager/backup/create --reason=manual --clean=0',
                            ],
                            'notes' => [
                                'Backups must be enabled in Redirect Manager settings.',
                                'The backup includes all redirects, not only the current site.',
                            ],
                        ],
                        [
                            'path' => 'backup/scheduled',
                            'summary' => 'Run the scheduled backup check.',
                            'description' => 'Check the configured backup schedule and create a scheduled backup only when one is due. Use this command from cron or another scheduler.',
                            'examples' => [
                                'redirect-manager/backup/scheduled',
                            ],
                            'notes' => [
                                'This command exits successfully when backups are disabled, the schedule is disabled, or no backup is due yet.',
                            ],
                        ],
                        [
                            'path' => 'backup/list',
                            'summary' => 'List available redirect backups.',
                            'description' => 'Print available backups with date, reason, file size, and redirect count.',
                            'examples' => [
                                'redirect-manager/backup/list',
                            ],
                        ],
                        [
                            'path' => 'backup/clean',
                            'summary' => 'Clean old backups.',
                            'description' => 'Delete backups older than the configured backupRetentionDays value. Nothing is deleted when retention is set to keep forever.',
                            'examples' => [
                                'redirect-manager/backup/clean',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'security',
                    'label' => 'Security',
                    'description' => 'Generate privacy and analytics secrets.',
                    'commands' => [
                        [
                            'path' => 'security/generate-salt',
                            'summary' => 'Generate the IP hash salt.',
                            'description' => 'Generate a secure REDIRECT_MANAGER_IP_SALT value and add it to the project .env file when possible.',
                            'examples' => [
                                'redirect-manager/security/generate-salt',
                            ],
                            'notes' => [
                                'Use the same salt across environments if you need unique-visitor analytics continuity.',
                                'Changing an existing salt resets unique-visitor tracking because future hashes will no longer match old analytics rows.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

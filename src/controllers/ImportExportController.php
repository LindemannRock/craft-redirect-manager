<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\UploadedFile;
use lindemannrock\base\helpers\CsvImportHelper;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\base\helpers\SafeSegmentHelper;
use lindemannrock\base\helpers\UrlSafetyHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\records\ImportHistoryRecord;
use lindemannrock\redirectmanager\records\RedirectRecord;
use lindemannrock\redirectmanager\RedirectManager;
use lindemannrock\redirectmanager\services\BackupService;
use Throwable;
use yii\base\UserException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Import/Export Controller
 *
 * @author    LindemannRock
 * @package   RedirectManager
 * @since     5.0.0
 */
class ImportExportController extends Controller
{
    use LoggingTrait;

    /** @var list<string> */
    private const PORTABLE_IMPORT_FIELDS = [
        'sourceUrl',
        'destinationUrl',
        'siteId',
        'redirectSrcMatch',
        'matchType',
        'statusCode',
        'priority',
        'enabled',
        'hitCount',
        'lastHit',
    ];

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(RedirectManager::$plugin->id);
    }

    /**
     * Import/Export settings page
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('redirectManager:manageImportExport');

        $settings = RedirectManager::$plugin->getSettings();
        $importLimits = [
            'maxRows' => CsvImportHelper::DEFAULT_MAX_ROWS,
            'maxBytes' => CsvImportHelper::DEFAULT_MAX_BYTES,
        ];
        $canImport = $this->canImport();
        $canExport = $this->canExport();
        $history = ImportHistoryRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(20)
            ->all();

        $formattedHistory = [];
        /** @var ImportHistoryRecord $record */
        foreach ($history as $record) {
            $user = Craft::$app->getUsers()->getUserById($record->userId);
            $formattedHistory[] = [
                'date' => $record->dateCreated,
                'formattedDate' => DateFormatHelper::formatDatetime($record->dateCreated),
                'user' => $user?->username ?? Craft::t('redirect-manager', 'Unknown'),
                'filename' => $record->filename,
                'filesize' => $record->filesize,
                'formattedSize' => $record->filesize ? Craft::$app->getFormatter()->asShortSize($record->filesize, 2) : '-',
                'imported' => $record->imported,
                'failed' => $record->failed,
                'backupPath' => $record->backupPath,
            ];
        }

        return $this->renderTemplate('redirect-manager/import-export/index', [
            'settings' => $settings,
            'importHistory' => $formattedHistory,
            'canImport' => $canImport,
            'canExport' => $canExport,
            'canShowHistory' => true,
            'importLimits' => $importLimits,
        ]);
    }

    /**
     * Clear import history logs
     *
     * @return Response
     * @since 5.24.0
     */
    public function actionClearLogs(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireClearImportHistoryPermission();

        try {
            Db::delete(ImportHistoryRecord::tableName());
            $this->logInfo('User cleared all import logs', [
                'userId' => Craft::$app->getUser()->getId(),
            ]);

            Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'Import history cleared successfully.'));

            return $this->asJson([
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to clear import logs', ['error' => $e->getMessage()]);

            return $this->asJson([
                'success' => false,
                'error' => Craft::t('redirect-manager', 'Failed to clear import history.'),
            ]);
        }
    }

    /**
     * Backups page
     *
     * @return Response
     * @since 5.23.0
     */
    public function actionBackups(): Response
    {
        $this->requireAnyBackupPermission();

        $settings = RedirectManager::$plugin->getSettings();
        if (!$settings->backupEnabled) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Backups are disabled in settings.'));
            return $this->redirect('redirect-manager/import-export');
        }

        return $this->renderTemplate('redirect-manager/backups/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Get backups as JSON (for async loading)
     *
     * @return Response
     * @since 5.23.0
     */
    public function actionGetBackups(): Response
    {
        $this->requireAcceptsJson();
        $this->requireAnyBackupPermission();

        $settings = RedirectManager::$plugin->getSettings();
        if (!$settings->backupEnabled) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('redirect-manager', 'Backups are disabled in settings.'),
                'backups' => [],
            ]);
        }

        $view = Craft::$app->getView();
        try {
            $backups = RedirectManager::$plugin->backup->getBackups();
        } catch (Throwable $e) {
            $this->logError('Backup listing failed', ['error' => $e->getMessage()]);
            return $this->asJson([
                'success' => false,
                'message' => $this->safeBackupError($e, 'Backups could not be loaded. Check the configured backup storage and try again.'),
                'backups' => [],
            ]);
        }
        $formatted = [];

        foreach ($backups as $backup) {
            $formattedDate = null;
            $timestamp = $backup['timestamp'] ?? null;
            if (is_numeric($timestamp)) {
                $dateTime = new \DateTime('@' . (int)$timestamp);
                $dateTime->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
                $formattedDate = DateFormatHelper::formatDatetime($dateTime);
            } elseif (!empty($backup['date'])) {
                $dateTime = \DateTime::createFromFormat('Y-m-d_H-i-s', $backup['date']);
                if ($dateTime) {
                    $dateTime->setTimezone(new \DateTimeZone(Craft::$app->getTimeZone()));
                    $formattedDate = DateFormatHelper::formatDatetime($dateTime);
                }
            }

            $reason = $backup['reason'] ?? 'import';
            $isScheduled = strtolower((string)$reason) === 'scheduled';
            $reasonInfo = $this->formatBackupReason($reason);
            $badgeHtml = $view->renderTemplate('lindemannrock-base/_components/badge', [
                'label' => $reasonInfo['reasonLabel'],
                'value' => $reasonInfo['reasonValue'],
                'colorSet' => 'backupReason',
            ]);

            $downloadUrl = UrlHelper::actionUrl('redirect-manager/import-export/download-backup', [
                'dirname' => $backup['dirname'] ?? '',
            ]);

            $rowActionsHtml = $view->renderTemplate('lindemannrock-base/_components/row-actions', [
                'item' => $backup,
                'actions' => [
                    'type' => 'menu',
                    'icon' => 'settings',
                    'items' => [
                        [
                            'label' => Craft::t('redirect-manager', 'Restore'),
                            'class' => 'restore-backup',
                            'jsAction' => 'restore',
                            'permission' => 'redirectManager:restoreBackups',
                            'data' => [
                                'dirname' => $backup['dirname'] ?? '',
                                'date' => $backup['date'] ?? '',
                                'count' => $backup['redirectCount'] ?? 0,
                            ],
                        ],
                        [
                            'label' => Craft::t('redirect-manager', 'Download ZIP'),
                            'url' => $downloadUrl,
                            'permission' => 'redirectManager:downloadBackups',
                        ],
                        ['type' => 'divider'],
                        [
                            'label' => Craft::t('redirect-manager', 'Delete'),
                            'class' => 'delete-backup error',
                            'jsAction' => 'delete',
                            'permission' => 'redirectManager:deleteBackups',
                            'data' => [
                                'dirname' => $backup['dirname'] ?? '',
                            ],
                        ],
                    ],
                ],
            ]);

            $formatted[] = array_merge($backup, $reasonInfo, [
                'formattedDate' => $formattedDate ?? ($backup['date'] ?? ''),
                'user' => $isScheduled
                    ? Craft::t('redirect-manager', 'System')
                    : ($backup['user'] ?? 'system'),
                'userId' => $isScheduled
                    ? null
                    : ($backup['userId'] ?? null),
                'reasonBadgeHtml' => $badgeHtml,
                'rowActionsHtml' => $rowActionsHtml,
            ]);
        }

        return $this->asJson([
            'success' => true,
            'backups' => $formatted,
        ]);
    }

    /**
     * Create a backup on demand
     *
     * @return Response
     * @since 5.23.0
     */
    public function actionCreateBackup(): Response
    {
        $this->requirePostRequest();
        $this->requireBackupPermission('redirectManager:createBackups');

        $request = Craft::$app->getRequest();
        $settings = RedirectManager::$plugin->getSettings();

        if (!$settings->backupEnabled) {
            $message = Craft::t('redirect-manager', 'Backups are disabled in settings.');
            if ($request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'message' => $message]);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirect('redirect-manager/backups');
        }

        try {
            $backupPath = RedirectManager::$plugin->backup->createBackup('manual');
        } catch (Throwable $e) {
            $this->logError('Manual backup creation failed', ['error' => $e->getMessage()]);
            $message = $this->safeBackupError($e, 'The backup could not be completed. Check the configured backup storage and permissions, then try again.');
            Craft::$app->getSession()->setError($message);
            if ($request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'message' => $message]);
            }
            return $this->redirect('redirect-manager/backups');
        }

        if ($backupPath === null) {
            $message = Craft::t('redirect-manager', 'No redirects found to back up.');
            Craft::$app->getSession()->setNotice($message);
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => $message,
                ]);
            }
            return $this->redirect('redirect-manager/backups');
        }

        $message = Craft::t('redirect-manager', 'Backup created.');
        Craft::$app->getSession()->setNotice($message);

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'message' => $message,
            ]);
        }

        return $this->redirect('redirect-manager/backups');
    }

    /**
     * Export redirects.
     *
     * The redirects table uses the base export menu and posts an explicit
     * format. The Import/Export page posts no format and remains CSV-only
     * through the default below.
     *
     * @return Response
     */
    public function actionExport(): Response
    {
        $this->requirePostRequest();
        $this->requireExportPermission();

        $request = Craft::$app->getRequest();

        // Check if specific redirects were selected
        $redirectIdsJson = $request->getBodyParam('redirectIds');
        $redirectIds = $redirectIdsJson ? json_decode($redirectIdsJson, true) : null;
        $format = (string)$request->getBodyParam('format', 'csv');
        ExportHelper::assertFormatEnabled($format, 'redirect-manager');

        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();
        $query = $this->buildRedirectExportQuery($redirectIds, $editableSiteIds);

        // Get redirects
        $redirects = $query->all();

        // CSV headers
        $headers = [
            'sourceUrl',
            'destinationUrl',
            'siteId',
            'redirectSrcMatch',
            'matchType',
            'statusCode',
            'priority',
            'enabled',
            'hitCount',
            'lastHit',
            'creationType',
            'sourcePlugin',
        ];

        $rows = [];
        foreach ($redirects as $redirect) {
            $rows[] = [
                'sourceUrl' => $redirect['sourceUrl'],
                'destinationUrl' => $redirect['destinationUrl'],
                'siteId' => $redirect['siteId'] ?? '',
                'redirectSrcMatch' => $redirect['redirectSrcMatch'],
                'matchType' => $redirect['matchType'],
                'statusCode' => $redirect['statusCode'],
                'priority' => $redirect['priority'],
                'enabled' => $redirect['enabled'] ? '1' : '0',
                'hitCount' => $redirect['hitCount'] ?? 0,
                'lastHit' => $redirect['lastHit'] ?? '',
                'creationType' => $redirect['creationType'],
                'sourcePlugin' => $redirect['sourcePlugin'] ?? 'redirect-manager',
            ];
        }

        // Check for empty data
        if (empty($rows)) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'No redirects to export.'));
            return $this->redirect('redirect-manager/redirects');
        }

        // Send download using ExportHelper for consistent filename/format handling.
        $settings = RedirectManager::$plugin->getSettings();
        $extension = ExportHelper::extensionForFormat($format);
        $filename = ExportHelper::filename($settings, ['export'], $extension);

        return ExportHelper::dispatchTable($rows, $headers, $format, $filename, ['lastHit']);
    }

    /**
     * Build the redirect export query scoped to the current user's editable sites.
     *
     * @param array<int|string>|null $redirectIds
     * @param array<int> $editableSiteIds
     */
    private function buildRedirectExportQuery(?array $redirectIds, array $editableSiteIds): Query
    {
        $query = (new Query())
            ->from(RedirectRecord::tableName())
            ->andWhere(['or', ['siteId' => null], ['siteId' => $editableSiteIds]])
            ->orderBy(['priority' => SORT_ASC, 'dateCreated' => SORT_DESC]);

        if (!empty($redirectIds)) {
            $query->andWhere(['in', 'id', array_map('intval', $redirectIds)]);
        }

        return $query;
    }

    /**
     * Upload and parse CSV file
     *
     * @return Response
     */
    public function actionUpload(): Response
    {
        $this->requirePostRequest();
        $this->requireImportPermission();

        $file = UploadedFile::getInstanceByName('csvFile');

        if (!$file) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Please select a CSV file to upload'));
            return $this->redirect('redirect-manager/import-export');
        }

        // Get delimiter (auto-detect by default)
        $delimiter = Craft::$app->getRequest()->getBodyParam('delimiter', 'auto');
        $detectDelimiter = true;
        if ($delimiter !== 'auto') {
            if ($delimiter === "\t") {
                $delimiter = "\t"; // Handle tab character
            }
            $detectDelimiter = false;
        } else {
            $delimiter = null;
        }

        $settings = RedirectManager::$plugin->getSettings();
        $defaultCreateBackup = $settings->backupEnabled && $settings->backupOnImport;
        $createBackup = (bool)Craft::$app->getRequest()->getBodyParam('createBackup', $defaultCreateBackup);

        if (!$settings->backupEnabled || !$settings->backupOnImport) {
            $createBackup = false;
        }

        // Parse CSV and store data in session (not file path - for Servd/load-balanced hosting)
        try {
            $parsed = CsvImportHelper::parseUpload($file, [
                'maxRows' => CsvImportHelper::DEFAULT_MAX_ROWS,
                'maxBytes' => CsvImportHelper::DEFAULT_MAX_BYTES,
                'delimiter' => $delimiter,
                'detectDelimiter' => $detectDelimiter,
            ]);

            // Store parsed data in session (not file path)
            Craft::$app->getSession()->set('redirect-import', [
                'headers' => $parsed['headers'],
                'allRows' => $parsed['allRows'],
                'rowCount' => $parsed['rowCount'],
                'createBackup' => $createBackup,
                'filename' => $file->name,
                'filesize' => $file->size,
            ]);

            // Redirect to column mapping
            return $this->redirect('redirect-manager/import-export/map');
        } catch (\Exception $e) {
            $this->logError('Failed to parse CSV', ['error' => $e->getMessage()]);
            $error = Craft::$app->getConfig()->getGeneral()->devMode
                ? $e->getMessage()
                : Craft::t('redirect-manager', 'An unexpected error occurred.');
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Failed to parse CSV: {error}', ['error' => $error]));
            return $this->redirect('redirect-manager/import-export');
        }
    }

    /**
     * Map CSV columns
     *
     * @return Response
     */
    public function actionMap(): Response
    {
        $this->requireImportPermission();

        // Get data from session (now contains actual row data, not file path)
        $importData = Craft::$app->getSession()->get('redirect-import');

        if (!$importData || !isset($importData['allRows'])) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'No import data found. Please upload a CSV file.'));
            return $this->redirect('redirect-manager/import-export');
        }

        // Get first 5 rows for preview (data is already in session)
        $previewRows = array_slice($importData['allRows'], 0, 5);

        return $this->renderTemplate('redirect-manager/import-export/map', [
            'headers' => $importData['headers'],
            'previewRows' => $previewRows,
            'rowCount' => $importData['rowCount'],
            'createBackup' => $importData['createBackup'],
        ]);
    }

    /**
     * Preview import with mapped columns (POST - process mapping)
     *
     * @return Response
     */
    public function actionPreview(): Response
    {
        $this->requireImportPermission();
        $session = $this->importSession();

        // If GET request, show preview from session
        if (!Craft::$app->getRequest()->getIsPost()) {
            $previewData = $session->get('redirect-preview');

            if (!$previewData) {
                Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'No preview data found. Please map columns first.'));
                return $this->redirect('redirect-manager/import-export');
            }

            return $this->renderTemplate('redirect-manager/import-export/preview', $previewData);
        }

        // POST request - process column mapping

        $importData = $session->get('redirect-import');

        if (!$importData || !isset($importData['allRows'])) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Import session expired. Please upload the file again.'));
            return $this->redirect('redirect-manager/import-export');
        }

        // Get column mapping
        $mapping = Craft::$app->getRequest()->getBodyParam('mapping', []);

        // Create reverse mapping (column index => field name)
        $columnMap = [];
        foreach ($mapping as $colIndex => $fieldName) {
            if (is_string($fieldName) && in_array($fieldName, self::PORTABLE_IMPORT_FIELDS, true)) {
                $columnMap[(int)$colIndex] = $fieldName;
            }
        }

        // Validate required fields are mapped
        $mappedFields = array_values($columnMap);
        if (!in_array('sourceUrl', $mappedFields) || !in_array('destinationUrl', $mappedFields)) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Source URL and Destination URL must be mapped'));
            return $this->redirect('redirect-manager/import-export');
        }

        // Process rows from session (no file access needed)
        $validRows = [];
        $duplicateRows = [];
        $errorRows = [];
        $rowNumber = 1;
        $editableSiteIds = Craft::$app->getSites()->getEditableSiteIds();

        // Get existing redirects for duplicate detection
        $existingRedirects = (new \craft\db\Query())
            ->select(['sourceUrlParsed', 'siteIdKey'])
            ->from('{{%redirectmanager_redirects}}')
            ->all();

        $existingKeys = [];
        foreach ($existingRedirects as $existing) {
            $key = RedirectRecord::sourceIdentityKey(
                (string)$existing['sourceUrlParsed'],
                (int)$existing['siteIdKey'],
            );
            $existingKeys[$key] = true;
        }

        // Iterate over rows stored in session
        foreach ($importData['allRows'] as $row) {
            $rowNumber++;

            // Map CSV row to fields
            $redirect = [
                'sourceUrl' => '',
                'destinationUrl' => '',
                'siteId' => null,
                'redirectSrcMatch' => 'pathonly',
                'matchType' => 'exact',
                'statusCode' => 301,
                'priority' => 0,
                'enabled' => true,
                'hitCount' => 0,
                'lastHit' => null,
                'creationType' => 'manual',
                'sourcePlugin' => 'redirect-manager',
                'elementId' => null,
            ];
            $behaviorError = null;

            foreach ($columnMap as $colIndex => $fieldName) {
                if (isset($row[$colIndex])) {
                    $value = trim($row[$colIndex]);

                    // Type conversion and normalization
                    if ($fieldName === 'enabled') {
                        $redirect[$fieldName] = in_array(strtolower($value), ['1', 'true', 'yes', 'enabled']);
                    } elseif ($fieldName === 'priority') {
                        if ($value === '') {
                            $redirect[$fieldName] = 0;
                        } elseif (!ctype_digit($value) || (int)$value > 9) {
                            $behaviorError = Craft::t('redirect-manager', 'Invalid priority: {priority}. Priority must be a whole number from 0 to 9.', [
                                'priority' => $value,
                            ]);
                        } else {
                            $redirect[$fieldName] = (int)$value;
                        }
                    } elseif ($fieldName === 'statusCode' || $fieldName === 'siteId' || $fieldName === 'hitCount') {
                        // Handle empty values - hitCount defaults to 0, siteId can be null
                        if (!empty($value)) {
                            $redirect[$fieldName] = (int)$value;
                        } elseif ($fieldName === 'hitCount') {
                            $redirect[$fieldName] = 0;
                        } else {
                            $redirect[$fieldName] = null; // siteId can be null
                        }
                    } elseif ($fieldName === 'lastHit') {
                        // Parse datetime - accept various formats
                        if (!empty($value)) {
                            try {
                                $date = new \DateTime($value);
                                $redirect[$fieldName] = $date->format('Y-m-d H:i:s');
                            } catch (\Exception $e) {
                                $redirect[$fieldName] = null;
                            }
                        } else {
                            $redirect[$fieldName] = null;
                        }
                    } elseif ($fieldName === 'matchType') {
                        // Normalize match type from various formats
                        $valueLower = strtolower($value);
                        if ($valueLower === '') {
                            $redirect[$fieldName] = 'exact';
                        } elseif (in_array($valueLower, ['exactmatch', 'exact match', 'exact'], true)) {
                            $redirect[$fieldName] = 'exact';
                        } elseif (in_array($valueLower, ['regexmatch', 'regex match', 'regex', 'regexp'], true)) {
                            $redirect[$fieldName] = 'regex';
                        } elseif (in_array($valueLower, ['wildcardmatch', 'wildcard match', 'wildcard'], true)) {
                            $redirect[$fieldName] = 'wildcard';
                        } elseif (in_array($valueLower, ['prefixmatch', 'prefix match', 'prefix'], true)) {
                            $redirect[$fieldName] = 'prefix';
                        } else {
                            $behaviorError = Craft::t('redirect-manager', 'Invalid match type: {matchType}', [
                                'matchType' => $value,
                            ]);
                        }
                    } elseif ($fieldName === 'redirectSrcMatch') {
                        // Normalize source match mode
                        $valueLower = strtolower($value);
                        if ($valueLower === '') {
                            $redirect[$fieldName] = 'pathonly';
                        } elseif (in_array($valueLower, ['fullurl', 'full url', 'full', 'url'], true)) {
                            $redirect[$fieldName] = 'fullurl';
                        } elseif (in_array($valueLower, ['pathonly', 'path only', 'path'], true)) {
                            $redirect[$fieldName] = 'pathonly';
                        } else {
                            $behaviorError = Craft::t('redirect-manager', 'Invalid source match mode: {redirectSrcMatch}', [
                                'redirectSrcMatch' => $value,
                            ]);
                        }
                    } else {
                        // Strip formula escape prefix for round-trip compatibility
                        $redirect[$fieldName] = CsvImportHelper::stripFormulaEscapePrefix($value);
                    }
                }
            }

            if ($behaviorError !== null) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $redirect['sourceUrl'],
                    'destinationUrl' => $redirect['destinationUrl'] ?: '-',
                    'error' => $behaviorError,
                ];
                continue;
            }

            $redirect = $this->normalizeImportSource($redirect);

            // Validate required fields
            if (empty($redirect['sourceUrl']) || empty($redirect['destinationUrl'])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $redirect['sourceUrl'],
                    'destinationUrl' => $redirect['destinationUrl'] ?? '-',
                    'error' => Craft::t('redirect-manager', 'Missing required field(s): Source URL or Destination URL'),
                ];
                continue;
            }

            if ($redirect['siteId'] !== null && !in_array($redirect['siteId'], $editableSiteIds, true)) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $redirect['sourceUrl'],
                    'destinationUrl' => $redirect['destinationUrl'],
                    'error' => Craft::t('redirect-manager', 'User does not have permission to create redirects for this site.'),
                ];
                continue;
            }

            // Validate source URL format
            $sourceUrl = $redirect['sourceUrl'];
            $isValidSourceUrl = false;

            // For regex/wildcard patterns, be more lenient but still require URL-like structure
            if (in_array($redirect['matchType'], ['regex', 'wildcard'])) {
                // Regex/wildcard: must start with / or ^ (regex start anchor), or be a full URL with a host
                $isValidSourceUrl = preg_match('#^[/^]#', $sourceUrl) === 1 || UrlSafetyHelper::isHttpUrlWithHost($sourceUrl);
            } else {
                // Exact/prefix: must start with / or be a full URL with a host
                $isValidSourceUrl = str_starts_with($sourceUrl, '/') || UrlSafetyHelper::isHttpUrlWithHost($sourceUrl);
            }

            if (!$isValidSourceUrl) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $redirect['destinationUrl'],
                    'error' => Craft::t('redirect-manager', 'Invalid source URL format - must start with / or be a full URL (http/https)'),
                ];
                continue;
            }

            // Check for email addresses disguised as URLs (e.g., /john@example.com or john@example.com)
            // Email pattern: contains @ followed by domain-like string, but not in a query string context
            if (preg_match('#^/?[^?]*@[a-z0-9.-]+\.[a-z]{2,}$#i', $sourceUrl)) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $redirect['destinationUrl'],
                    'error' => Craft::t('redirect-manager', 'Source URL appears to be an email address'),
                ];
                continue;
            }

            // Validate destination URL format
            $destinationUrl = $redirect['destinationUrl'];

            if (!RedirectRecord::isValidDestination($destinationUrl)) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                    'error' => Craft::t('redirect-manager', 'Invalid destination URL format - must be a path (/) or valid URL scheme'),
                ];
                continue;
            }

            // Check for email addresses in destination (unless using a proper protocol)
            // Skip this check for URLs with recognized protocols (mailto:, tel:, whatsapp:, etc.)
            $hasValidProtocol = preg_match('#^(https?|mailto|tel|whatsapp|sms|fax|skype|slack|msteams):#i', $destinationUrl) === 1;
            if (!$hasValidProtocol && preg_match('#^/?[^?]*@[a-z0-9.-]+\.[a-z]{2,}$#i', $destinationUrl)) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                    'error' => Craft::t('redirect-manager', 'Destination appears to be an email - use mailto: prefix'),
                ];
                continue;
            }

            // Validate match type
            if (!in_array($redirect['matchType'], ['exact', 'regex', 'wildcard', 'prefix'])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                    'error' => Craft::t('redirect-manager', 'Invalid match type: {matchType}', [
                        'matchType' => $redirect['matchType'],
                    ]),
                ];
                continue;
            }

            // Validate capture references against match type / source pattern
            $captureError = RedirectRecord::captureReferenceError($destinationUrl, $redirect['matchType'], $sourceUrl);
            if ($captureError !== null) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                    'error' => $captureError,
                ];
                continue;
            }

            // Validate status code
            if (!in_array($redirect['statusCode'], [301, 302, 303, 307, 308, 410])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                    'error' => Craft::t('redirect-manager', 'Invalid status code: {statusCode}', [
                        'statusCode' => $redirect['statusCode'],
                    ]),
                ];
                continue;
            }

            // Check for duplicates
            $duplicateKey = RedirectRecord::sourceIdentityKey(
                (string)$redirect['sourceUrlParsed'],
                RedirectRecord::siteIdKey($redirect['siteId'] === null ? null : (int)$redirect['siteId']),
            );
            if (isset($existingKeys[$duplicateKey])) {
                $duplicateRows[] = [
                    'sourceUrl' => $redirect['sourceUrl'],
                    'destinationUrl' => $redirect['destinationUrl'],
                    'reason' => Craft::t('redirect-manager', 'Already exists with the same source URL and site scope'),
                ];
                continue;
            }

            // Check for infinite loops
            if (strtolower($redirect['sourceUrl']) === strtolower($redirect['destinationUrl'])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                    'error' => Craft::t('redirect-manager', 'Infinite loop: Source and destination are identical'),
                ];
                continue;
            }

            $validRows[] = $redirect;
            $existingKeys[$duplicateKey] = true;
        }

        // Get count of existing redirects for backup info
        $existingCount = (new \craft\db\Query())
            ->from('{{%redirectmanager_redirects}}')
            ->count();

        // Store validated data in session
        $session->set('redirect-import-validated', [
            'validRows' => $validRows,
            'duplicateRows' => $duplicateRows,
            'errorRows' => $errorRows,
            'createBackup' => $importData['createBackup'],
        ]);

        $summary = [
            'totalRows' => $rowNumber - 1,
            'validRows' => count($validRows),
            'duplicates' => count($duplicateRows),
            'errors' => count($errorRows),
        ];

        // Store preview data in session for rendering
        $session->set('redirect-preview', [
            'summary' => $summary,
            'validRows' => $validRows,
            'duplicateRows' => $duplicateRows,
            'errorRows' => $errorRows,
            'existingCount' => $existingCount,
            'createBackup' => $importData['createBackup'],
        ]);

        // Redirect to preview page
        return $this->redirect('redirect-manager/import-export/preview');
    }

    /**
     * Return the session used by the portable import lifecycle.
     */
    protected function importSession(): object
    {
        return Craft::$app->getSession();
    }

    /**
     * Perform the import
     *
     * @return Response|null
     */
    public function actionImport(): ?Response
    {
        $this->requirePostRequest();
        $this->requireImportPermission();
        $session = $this->importSession();

        $validatedData = $session->get('redirect-import-validated');
        $importData = $session->get('redirect-import');

        if (!$validatedData) {
            $session->setError(Craft::t('redirect-manager', 'Import session expired'));
            return $this->redirect('redirect-manager/import-export');
        }

        $validRows = is_array($validatedData['validRows'] ?? null) ? $validatedData['validRows'] : [];
        [$validRows, $siteScopeFailures] = $this->filterImportRowsForEditableSites(
            $validRows,
            Craft::$app->getSites()->getEditableSiteIds()
        );
        $settings = RedirectManager::$plugin->getSettings();
        $createBackup = $validatedData['createBackup'] && $settings->backupEnabled && $settings->backupOnImport;

        // Create backup if requested and there are existing redirects to backup
        $backupPath = null;
        if ($createBackup) {
            $existingCount = (new \craft\db\Query())
                ->from('{{%redirectmanager_redirects}}')
                ->count();

            if ($existingCount > 0) {
                try {
                    $backupPath = RedirectManager::$plugin->backup->createBackup('import');
                } catch (Throwable $e) {
                    $this->logError('Import safety backup failed', ['error' => $e->getMessage()]);
                    $session->setError($this->safetyBackupFailureMessage('Import was stopped because the safety backup could not be completed.'));
                    return $this->redirect('redirect-manager/import-export');
                }

                if ($backupPath === null) {
                    $session->setError($this->safetyBackupFailureMessage('Import was stopped because the safety backup could not be completed.'));
                    return $this->redirect('redirect-manager/import-export');
                }
            }
        }

        // Import redirects
        $imported = 0;
        $failed = $siteScopeFailures
            + count(is_array($validatedData['duplicateRows'] ?? null) ? $validatedData['duplicateRows'] : [])
            + count(is_array($validatedData['errorRows'] ?? null) ? $validatedData['errorRows'] : []);
        $db = Craft::$app->getDb();

        foreach ($validRows as $redirectData) {
            try {
                $redirectData = $this->normalizeImportSource($redirectData);

                $db->createCommand()->insert('{{%redirectmanager_redirects}}', [
                    'siteId' => $redirectData['siteId'],
                    'sourceUrl' => $redirectData['sourceUrl'],
                    'sourceUrlParsed' => $redirectData['sourceUrlParsed'],
                    'siteIdKey' => RedirectRecord::siteIdKey($redirectData['siteId'] ? (int)$redirectData['siteId'] : null),
                    'destinationUrl' => $redirectData['destinationUrl'],
                    'redirectSrcMatch' => $redirectData['redirectSrcMatch'],
                    'matchType' => $redirectData['matchType'],
                    'statusCode' => $redirectData['statusCode'],
                    'priority' => $redirectData['priority'],
                    'enabled' => $redirectData['enabled'],
                    'creationType' => 'manual',
                    'sourcePlugin' => 'redirect-manager',
                    'elementId' => null,
                    'hitCount' => $redirectData['hitCount'] ?? 0,
                    'lastHit' => $redirectData['lastHit'],
                    'dateCreated' => \craft\helpers\Db::prepareDateForDb(new \DateTime()),
                    'dateUpdated' => \craft\helpers\Db::prepareDateForDb(new \DateTime()),
                    'uid' => \craft\helpers\StringHelper::UUID(),
                ])->execute();

                $imported++;
            } catch (\Exception $e) {
                $this->logError('Failed to import redirect', [
                    'sourceUrl' => $redirectData['sourceUrl'],
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        if ($imported > 0) {
            RedirectManager::$plugin->redirects->invalidateCaches();
        }

        // Clean up session data (no temp file to delete - data was stored in session)
        $session->remove('redirect-import');
        $session->remove('redirect-import-validated');
        $session->remove('redirect-preview');

        $pluginName = RedirectManager::$plugin->getSettings()->getPluralLowerDisplayName();
        $message = Craft::t('redirect-manager', 'Successfully imported {imported} {pluginName}.', [
            'imported' => $imported,
            'pluginName' => $pluginName,
        ]);
        if ($failed > 0) {
            $message .= ' ' . Craft::t('redirect-manager', '{failed} failed.', ['failed' => $failed]);
        }

        // Save import history (best-effort)
        try {
            $history = new ImportHistoryRecord();
            $history->userId = Craft::$app->getUser()->getId();
            $history->filename = $importData['filename'] ?? null;
            $history->filesize = $importData['filesize'] ?? null;
            $history->imported = $imported;
            $history->failed = $failed;
            $history->backupPath = $backupPath ? RedirectManager::$plugin->backup->getRelativeBackupName($backupPath) : null;
            $history->save();
        } catch (\Throwable $e) {
            $this->logError('Failed to save import history', ['error' => $e->getMessage()]);
        }

        $session->setNotice($message);
        return $this->redirect('redirect-manager/import-export');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int> $editableSiteIds
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function filterImportRowsForEditableSites(array $rows, array $editableSiteIds): array
    {
        $filtered = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $siteId = $row['siteId'] ?? null;
            if ($siteId === null || $siteId === '') {
                $filtered[] = $row;
                continue;
            }

            $siteId = (int)$siteId;
            if (!in_array($siteId, $editableSiteIds, true)) {
                $skipped++;
                continue;
            }

            $row['siteId'] = $siteId;
            $filtered[] = $row;
        }

        return [$filtered, $skipped];
    }

    /**
     * @param array<string, mixed> $redirect
     * @return array<string, mixed>
     */
    private function normalizeImportSource(array $redirect): array
    {
        $normalized = RedirectRecord::normalizeSourceUrl(
            (string)$redirect['sourceUrl'],
            (string)$redirect['redirectSrcMatch'],
            (string)$redirect['matchType'],
        );
        $redirect['sourceUrl'] = $normalized['sourceUrl'];
        $redirect['sourceUrlParsed'] = $normalized['sourceUrlParsed'];

        return $redirect;
    }

    /**
     * Format backup reason for display
     *
     * @param string $reason
     * @return array{reasonLabel: string, reasonValue: string}
     */
    private function formatBackupReason(string $reason): array
    {
        $reason = strtolower($reason);

        [$label, $value] = match ($reason) {
            'import', 'before_import' => [Craft::t('redirect-manager', 'Before Import'), 'import'],
            'restore', 'before_restore' => [Craft::t('redirect-manager', 'Before Restore'), 'restore'],
            'manual', 'console' => [Craft::t('redirect-manager', 'Manual'), 'manual'],
            'scheduled' => [Craft::t('redirect-manager', 'Scheduled'), 'scheduled'],
            'maintenance' => [Craft::t('redirect-manager', 'Maintenance'), 'maintenance'],
            'other' => [Craft::t('redirect-manager', 'Other'), 'other'],
            default => [Craft::t('redirect-manager', 'Other'), 'other'],
        };

        return [
            'reasonLabel' => $label,
            'reasonValue' => $value,
        ];
    }

    /**
     * Download backup as ZIP
     *
     * @return Response
     * @since 5.23.0
     */
    public function actionDownloadBackup(): Response
    {
        $this->requireBackupPermission('redirectManager:downloadBackups');

        $settings = RedirectManager::$plugin->getSettings();
        if (!$settings->backupEnabled) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Backups are disabled in settings.'));
            return $this->redirect('redirect-manager/import-export');
        }

        $dirname = Craft::$app->getRequest()->getQueryParam('dirname');
        $backupService = RedirectManager::$plugin->backup;
        try {
            $usesVolume = $backupService->isUsingVolumeStorage();
            $backupDir = $usesVolume ? null : $backupService->validateBackupDirname(is_string($dirname) ? $dirname : null);
            $volumeBackupName = $usesVolume ? $backupService->validateVolumeBackupName(is_string($dirname) ? $dirname : null) : null;

            if (
                ($usesVolume && $volumeBackupName === null)
                || (!$usesVolume && $backupDir === null)
            ) {
                throw new UserException(Craft::t('redirect-manager', 'Backup not found'));
            }

            [$metadata, $redirects] = $this->readBackupDownloadMembers(
                $backupService,
                $usesVolume,
                $backupDir,
                $volumeBackupName,
            );

            $safeDirname = SafeSegmentHelper::filenamePart($usesVolume ? $volumeBackupName : basename((string)$backupDir), 'backup');
            return $this->prepareOwnedBackupDownload(
                $metadata,
                $redirects,
                'redirect-backup-' . $safeDirname . '.zip'
            );
        } catch (Throwable $e) {
            $this->logError('Backup download failed', ['error' => $e->getMessage()]);
            Craft::$app->getSession()->setError($this->safeBackupError($e, 'The backup ZIP could not be prepared. Check the configured backup storage and try again.'));
            return $this->redirect('redirect-manager/backups');
        }
    }

    /**
     * Restore from backup
     *
     * @return Response|null
     * @since 5.23.0
     */
    public function actionRestoreBackup(): ?Response
    {
        $this->requirePostRequest();
        $this->requireBackupPermission('redirectManager:restoreBackups');

        $request = Craft::$app->getRequest();
        $settings = RedirectManager::$plugin->getSettings();
        if (!$settings->backupEnabled) {
            $message = Craft::t('redirect-manager', 'Backups are disabled in settings.');
            if ($request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'message' => $message]);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirect('redirect-manager/import-export');
        }

        $dirname = $request->getBodyParam('dirname');
        $backupService = RedirectManager::$plugin->backup;
        try {
            $usesVolume = $backupService->isUsingVolumeStorage();
            $backupDir = $usesVolume ? null : $backupService->validateBackupDirname(is_string($dirname) ? $dirname : null);
            $volumeBackupName = $usesVolume ? $backupService->validateVolumeBackupName(is_string($dirname) ? $dirname : null) : null;

            if (
                ($usesVolume && $volumeBackupName === null)
                || (!$usesVolume && $backupDir === null)
            ) {
                throw new UserException(Craft::t('redirect-manager', 'Backup not found'));
            }

            $metadataContent = $usesVolume && $volumeBackupName !== null
                ? $backupService->readVolumeBackupFile($volumeBackupName, 'metadata.json')
                : file_get_contents($backupDir . '/metadata.json');
            $redirectContent = $usesVolume && $volumeBackupName !== null
                ? $backupService->readVolumeBackupFile($volumeBackupName, 'redirects.json')
                : file_get_contents($backupDir . '/redirects.json');

            if (!is_string($metadataContent) || !is_string($redirectContent)) {
                throw new UserException(Craft::t('redirect-manager', 'Backup not found'));
            }

            $redirects = $this->validateRestoreTargetAndRequireSafetyBackup(
                $backupService,
                $metadataContent,
                $redirectContent,
                is_string($dirname) ? $dirname : basename((string)$backupDir),
            );
            $restored = $this->replaceRedirectsFromBackup($redirects);

            $this->logInfo('Backup restored', ['dirname' => is_string($dirname) ? $dirname : basename((string)$backupDir), 'count' => $restored]);

            $successMessage = Craft::t('redirect-manager', 'Successfully restored {count} redirect(s) from backup', ['count' => $restored]);
            Craft::$app->getSession()->setNotice($successMessage);

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => $successMessage,
                    'restored' => $restored,
                ]);
            }

            return $this->redirect('redirect-manager/backups');
        } catch (Throwable $e) {
            $this->logError('Restore failed', ['error' => $e->getMessage()]);
            $errorMessage = $this->safeBackupError($e, 'The backup could not be restored. Check the selected backup and configured backup storage, then try again.');
            Craft::$app->getSession()->setError($errorMessage);

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => $errorMessage,
                ]);
            }

            return $this->redirect('redirect-manager/backups');
        }
    }

    /**
     * Delete backup
     *
     * @return Response|null
     * @since 5.23.0
     */
    public function actionDeleteBackup(): ?Response
    {
        $this->requirePostRequest();
        $this->requireBackupPermission('redirectManager:deleteBackups');

        $request = Craft::$app->getRequest();
        $settings = RedirectManager::$plugin->getSettings();
        if (!$settings->backupEnabled) {
            $message = Craft::t('redirect-manager', 'Backups are disabled in settings.');
            if ($request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'message' => $message]);
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirect('redirect-manager/import-export');
        }

        $dirname = $request->getBodyParam('dirname');
        $backupService = RedirectManager::$plugin->backup;
        try {
            $usesVolume = $backupService->isUsingVolumeStorage();
            $backupDir = $usesVolume ? null : $backupService->validateBackupDirname(is_string($dirname) ? $dirname : null);
            $volumeBackupName = $usesVolume ? $backupService->validateVolumeBackupName(is_string($dirname) ? $dirname : null) : null;

            if (
                ($usesVolume && $volumeBackupName === null)
                || (!$usesVolume && $backupDir === null)
            ) {
                throw new UserException(Craft::t('redirect-manager', 'Backup not found'));
            }

            if ($usesVolume && $volumeBackupName !== null) {
                $backupService->deleteVolumeBackup($volumeBackupName);
            } else {
                FileHelper::removeDirectory($backupDir);
                if (is_dir($backupDir)) {
                    throw new \RuntimeException('The local backup directory could not be removed.');
                }
            }

            $this->logInfo('Backup deleted', ['dirname' => is_string($dirname) ? $dirname : basename((string)$backupDir)]);

            $successMessage = Craft::t('redirect-manager', 'Backup deleted successfully');
            Craft::$app->getSession()->setNotice($successMessage);

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => $successMessage,
                ]);
            }

            return $this->redirect('redirect-manager/backups');
        } catch (Throwable $e) {
            $this->logError('Delete backup failed', ['error' => $e->getMessage()]);
            $errorMessage = $this->safeBackupError($e, 'The backup could not be deleted. Check the configured backup storage and permissions, then try again.');
            Craft::$app->getSession()->setError($errorMessage);

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => $errorMessage,
                ]);
            }

            return $this->redirect('redirect-manager/backups');
        }
    }

    /**
     * Create one request-owned temporary ZIP path.
     */
    protected function createOwnedBackupZipPath(): string
    {
        $path = tempnam(Craft::$app->getPath()->getTempPath(), 'redirect-backup-');
        if (!is_string($path)) {
            throw new \RuntimeException('Unable to allocate an owned backup ZIP path.');
        }

        return $path;
    }

    protected function createBackupZip(): \ZipArchive
    {
        return new \ZipArchive();
    }

    protected function openBackupZip(\ZipArchive $zip, string $path): bool
    {
        return $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true;
    }

    protected function addBackupZipMember(\ZipArchive $zip, string $name, string $contents): bool
    {
        return $zip->addFromString($name, $contents);
    }

    protected function closeBackupZip(\ZipArchive $zip): bool
    {
        return $zip->close();
    }

    protected function prepareBackupDownloadResponse(string $path, string $filename): Response
    {
        return Craft::$app->getResponse()->sendFile($path, $filename, [
            'inline' => false,
        ]);
    }

    /**
     * @param callable(): void $cleanup
     */
    protected function registerBackupDownloadShutdown(callable $cleanup): void
    {
        register_shutdown_function($cleanup);
    }

    /**
     * @param callable(): void $cleanup
     */
    protected function registerBackupResponseCleanup(Response $response, callable $cleanup): void
    {
        $response->on(Response::EVENT_AFTER_SEND, static function() use ($cleanup): void {
            $cleanup();
        });
    }

    protected function removeOwnedBackupZip(string $path): void
    {
        if (is_file($path) && !unlink($path)) {
            $this->logError('Failed to remove owned backup ZIP', ['path' => $path]);
        }
    }

    protected function prepareOwnedBackupDownload(string $metadata, string $redirects, string $filename): Response
    {
        $zipPath = $this->createOwnedBackupZipPath();
        $cleanup = fn() => $this->removeOwnedBackupZip($zipPath);
        $zip = null;
        $zipOpen = false;

        try {
            $this->registerBackupDownloadShutdown($cleanup);
            $zip = $this->createBackupZip();
            if (!$this->openBackupZip($zip, $zipPath)) {
                throw new \RuntimeException('Unable to open the owned backup ZIP.');
            }
            $zipOpen = true;

            if (!$this->addBackupZipMember($zip, 'metadata.json', $metadata)) {
                throw new \RuntimeException('Unable to add backup metadata to the ZIP.');
            }
            if (!$this->addBackupZipMember($zip, 'redirects.json', $redirects)) {
                throw new \RuntimeException('Unable to add backup redirects to the ZIP.');
            }
            if (!$this->closeBackupZip($zip)) {
                throw new \RuntimeException('Unable to finalize the backup ZIP.');
            }
            $zipOpen = false;

            $response = $this->prepareBackupDownloadResponse($zipPath, $filename);
            $this->registerBackupResponseCleanup($response, $cleanup);

            return $response;
        } catch (Throwable $e) {
            if ($zipOpen && $zip instanceof \ZipArchive) {
                try {
                    $this->closeBackupZip($zip);
                } catch (Throwable $closeError) {
                    $this->logError('Failed to close backup ZIP after download failure', ['error' => $closeError->getMessage()]);
                }
            }
            $cleanup();
            throw $e;
        }
    }

    /**
     * @return array{string, string}
     */
    protected function readBackupDownloadMembers(
        BackupService $backupService,
        bool $usesVolume,
        ?string $backupDir,
        ?string $volumeBackupName,
    ): array {
        if ($usesVolume) {
            if ($volumeBackupName === null) {
                throw new UserException(Craft::t('redirect-manager', 'Backup not found'));
            }
            $metadata = $backupService->readVolumeBackupFile($volumeBackupName, 'metadata.json');
            $redirects = $backupService->readVolumeBackupFile($volumeBackupName, 'redirects.json');
        } else {
            if ($backupDir === null) {
                throw new UserException(Craft::t('redirect-manager', 'Backup not found'));
            }
            $metadata = file_get_contents($backupDir . '/metadata.json');
            $redirects = file_get_contents($backupDir . '/redirects.json');
        }

        if (!is_string($metadata) || !is_string($redirects)) {
            throw new UserException(Craft::t('redirect-manager', 'Backup not found'));
        }

        return [$metadata, $redirects];
    }

    protected function requireRestoreSafetyBackup(BackupService $backupService): void
    {
        $currentRedirectCount = (int)(new Query())
            ->from(RedirectRecord::tableName())
            ->count();
        if ($currentRedirectCount === 0) {
            return;
        }

        try {
            $preRestoreBackup = $backupService->createBackup('restore');
        } catch (Throwable $e) {
            $this->logError('Pre-restore safety backup failed', ['error' => $e->getMessage()]);
            throw new UserException($this->safetyBackupFailureMessage('Restore was stopped because a safety backup of the current redirects could not be completed.'));
        }
        if ($preRestoreBackup === null) {
            throw new UserException($this->safetyBackupFailureMessage('Restore was stopped because a safety backup of the current redirects could not be completed.'));
        }
    }

    private function safetyBackupFailureMessage(string $context): string
    {
        return Craft::t('redirect-manager', $context) . ' '
            . Craft::t('redirect-manager', 'The backup could not be completed. Check the configured backup storage and permissions, then try again.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function validateRestoreTargetAndRequireSafetyBackup(
        BackupService $backupService,
        string $metadataContent,
        string $redirectContent,
        string $backupName,
    ): array {
        if (!$backupService->validateBackupIntegrity($metadataContent, $redirectContent, $backupName)) {
            throw new UserException(Craft::t('redirect-manager', 'Backup integrity check failed. The backup files may have been modified or corrupted.'));
        }

        $redirects = json_decode($redirectContent, true);
        if (!is_array($redirects) || $redirects === []) {
            throw new UserException(Craft::t('redirect-manager', 'Invalid backup file format'));
        }
        foreach ($redirects as $redirect) {
            if (!is_array($redirect)) {
                throw new UserException(Craft::t('redirect-manager', 'Invalid backup file format'));
            }
        }

        // Target validation must precede the required current-state snapshot.
        $this->requireRestoreSafetyBackup($backupService);

        /** @var array<int, array<string, mixed>> $redirects */
        return $redirects;
    }

    /**
     * @param array<int, array<string, mixed>> $redirects
     */
    protected function replaceRedirectsFromBackup(array $redirects): int
    {
        $db = Craft::$app->getDb();
        $restored = 0;
        $db->transaction(function() use ($db, $redirects, &$restored): void {
            $db->createCommand()->delete(RedirectRecord::tableName())->execute();

            foreach ($redirects as $redirect) {
                unset($redirect['id']);
                $siteId = isset($redirect['siteId']) && $redirect['siteId'] !== null ? (int)$redirect['siteId'] : null;
                $normalized = RedirectRecord::normalizeSourceUrl(
                    (string)($redirect['sourceUrl'] ?? $redirect['sourceUrlParsed'] ?? ''),
                    (string)($redirect['redirectSrcMatch'] ?? 'pathonly'),
                    (string)($redirect['matchType'] ?? 'exact'),
                );
                $redirect['sourceUrl'] = $normalized['sourceUrl'];
                $redirect['sourceUrlParsed'] = $normalized['sourceUrlParsed'];
                $redirect['siteIdKey'] = RedirectRecord::siteIdKey($siteId);
                $db->createCommand()->insert(RedirectRecord::tableName(), $redirect)->execute();
                $restored++;
            }
        });

        RedirectManager::$plugin->redirects->invalidateCaches();
        return $restored;
    }

    private function safeBackupError(Throwable $e, string $fallback): string
    {
        return $e instanceof UserException
            ? $e->getMessage()
            : Craft::t('redirect-manager', $fallback);
    }


    /**
     * Require any backup-related permission (view access)
     *
     * @return void
     */
    private function requireAnyBackupPermission(): void
    {
        $user = Craft::$app->getUser();
        $hasAccess =
            $user->checkPermission('redirectManager:manageBackups') ||
            $user->checkPermission('redirectManager:createBackups') ||
            $user->checkPermission('redirectManager:downloadBackups') ||
            $user->checkPermission('redirectManager:restoreBackups') ||
            $user->checkPermission('redirectManager:deleteBackups');

        if (!$hasAccess) {
            throw new ForbiddenHttpException(Craft::t('redirect-manager', 'User does not have permission to manage backups.'));
        }
    }

    /**
     * Require a specific backup operation permission.
     *
     * @param string $permission
     * @return void
     */
    private function requireBackupPermission(string $permission): void
    {
        if (!Craft::$app->getUser()->checkPermission($permission)) {
            throw new ForbiddenHttpException(Craft::t('redirect-manager', 'User does not have permission to manage backups.'));
        }
    }

    /**
     * Check if user can import redirects
     *
     * @return bool
     */
    private function canImport(): bool
    {
        return Craft::$app->getUser()->checkPermission('redirectManager:importRedirects');
    }

    /**
     * Check if user can export redirects
     *
     * @return bool
     */
    private function canExport(): bool
    {
        return Craft::$app->getUser()->checkPermission('redirectManager:exportRedirects');
    }

    /**
     * Require import permission
     *
     * @return void
     */
    private function requireImportPermission(): void
    {
        if (!$this->canImport()) {
            throw new ForbiddenHttpException(Craft::t('redirect-manager', 'User does not have permission to import redirects.'));
        }
    }

    /**
     * Require clear history permission
     *
     * @return void
     */
    private function requireClearImportHistoryPermission(): void
    {
        if (!Craft::$app->getUser()->checkPermission('redirectManager:clearImportHistory')) {
            throw new ForbiddenHttpException(Craft::t('redirect-manager', 'User does not have permission to clear import history.'));
        }
    }

    /**
     * Require export permission
     *
     * @return void
     */
    private function requireExportPermission(): void
    {
        if (!$this->canExport()) {
            throw new ForbiddenHttpException(Craft::t('redirect-manager', 'User does not have permission to export redirects.'));
        }
    }
}

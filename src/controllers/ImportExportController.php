<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\controllers;

use Craft;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\UploadedFile;
use lindemannrock\base\helpers\CsvImportHelper;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\records\ImportHistoryRecord;
use lindemannrock\redirectmanager\RedirectManager;
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
     * @since 5.0.0
     */
    public function actionIndex(): Response
    {
        $this->requireAnyImportExportPermission();

        $settings = RedirectManager::$plugin->getSettings();
        $importLimits = [
            'maxRows' => CsvImportHelper::DEFAULT_MAX_ROWS,
            'maxBytes' => CsvImportHelper::DEFAULT_MAX_BYTES,
        ];
        $canImport = $this->canImport();
        $canExport = $this->canExport();
        $canViewHistory = $this->canViewHistory();
        $history = ImportHistoryRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(20)
            ->all();

        $formattedHistory = [];
        if ($canViewHistory) {
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
        }

        return $this->renderTemplate('redirect-manager/import-export/index', [
            'settings' => $settings,
            'importHistory' => $formattedHistory,
            'canImport' => $canImport,
            'canExport' => $canExport,
            'canViewHistory' => $canViewHistory,
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
        $backups = RedirectManager::$plugin->backup->getBackups();
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

        $backupPath = RedirectManager::$plugin->backup->createBackup('manual');

        if (!$backupPath) {
            $message = Craft::t('redirect-manager', 'No redirects found to back up.');
            Craft::$app->getSession()->setError($message);
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
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
     * Export redirects to CSV
     *
     * @return Response
     * @since 5.0.0
     */
    public function actionExport(): Response
    {
        $this->requireExportPermission();

        $request = Craft::$app->getRequest();

        // Check if specific redirects were selected (from query param or body param)
        $redirectIdsJson = $request->getQueryParam('redirectIds') ?? $request->getBodyParam('redirectIds');
        $redirectIds = $redirectIdsJson ? json_decode($redirectIdsJson, true) : null;

        // Build query
        $query = (new \craft\db\Query())
            ->from('{{%redirectmanager_redirects}}')
            ->orderBy(['priority' => SORT_ASC, 'dateCreated' => SORT_DESC]);

        // Filter by selected IDs if provided
        if (!empty($redirectIds)) {
            $query->where(['in', 'id', $redirectIds]);
        }

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
            return $this->redirect(Craft::$app->getRequest()->getReferrer() ?? 'redirect-manager/redirects');
        }

        // Send as CSV download using ExportHelper for consistent filename
        $settings = RedirectManager::$plugin->getSettings();
        $filename = ExportHelper::filename($settings, ['export'], 'csv');

        return ExportHelper::toCsv($rows, $headers, $filename, ['lastHit']);
    }

    /**
     * Upload and parse CSV file
     *
     * @return Response
     * @since 5.0.0
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
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Failed to parse CSV: {error}', ['error' => $e->getMessage()]));
            return $this->redirect('redirect-manager/import-export');
        }
    }

    /**
     * Map CSV columns
     *
     * @return Response
     * @since 5.0.0
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
     * @since 5.0.0
     */
    public function actionPreview(): Response
    {
        $this->requireImportPermission();

        // If GET request, show preview from session
        if (!Craft::$app->getRequest()->getIsPost()) {
            $previewData = Craft::$app->getSession()->get('redirect-preview');

            if (!$previewData) {
                Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'No preview data found. Please map columns first.'));
                return $this->redirect('redirect-manager/import-export');
            }

            return $this->renderTemplate('redirect-manager/import-export/preview', $previewData);
        }

        // POST request - process column mapping

        $importData = Craft::$app->getSession()->get('redirect-import');

        if (!$importData || !isset($importData['allRows'])) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Import session expired. Please upload the file again.'));
            return $this->redirect('redirect-manager/import-export');
        }

        // Get column mapping
        $mapping = Craft::$app->getRequest()->getBodyParam('mapping', []);

        // Create reverse mapping (column index => field name)
        $columnMap = [];
        foreach ($mapping as $colIndex => $fieldName) {
            if (!empty($fieldName)) {
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

        // Get existing redirects for duplicate detection
        $existingRedirects = (new \craft\db\Query())
            ->select(['sourceUrl', 'matchType', 'redirectSrcMatch'])
            ->from('{{%redirectmanager_redirects}}')
            ->all();

        $existingKeys = [];
        foreach ($existingRedirects as $existing) {
            $key = strtolower($existing['sourceUrl']) . '|' . $existing['matchType'] . '|' . $existing['redirectSrcMatch'];
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
                'creationType' => 'import',
                'sourcePlugin' => 'redirect-manager',
            ];

            foreach ($columnMap as $colIndex => $fieldName) {
                if (isset($row[$colIndex])) {
                    $value = trim($row[$colIndex]);

                    // Type conversion and normalization
                    if ($fieldName === 'enabled') {
                        $redirect[$fieldName] = in_array(strtolower($value), ['1', 'true', 'yes', 'enabled']);
                    } elseif ($fieldName === 'statusCode' || $fieldName === 'priority' || $fieldName === 'siteId' || $fieldName === 'hitCount') {
                        // Handle empty values - priority and hitCount default to 0, siteId can be null
                        if (!empty($value)) {
                            $redirect[$fieldName] = (int)$value;
                        } elseif ($fieldName === 'priority' || $fieldName === 'hitCount') {
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
                        if (in_array($valueLower, ['exactmatch', 'exact match', 'exact'])) {
                            $redirect[$fieldName] = 'exact';
                        } elseif (in_array($valueLower, ['regexmatch', 'regex match', 'regex', 'regexp'])) {
                            $redirect[$fieldName] = 'regex';
                        } elseif (in_array($valueLower, ['wildcardmatch', 'wildcard match', 'wildcard'])) {
                            $redirect[$fieldName] = 'wildcard';
                        } elseif (in_array($valueLower, ['prefixmatch', 'prefix match', 'prefix'])) {
                            $redirect[$fieldName] = 'prefix';
                        } else {
                            $redirect[$fieldName] = 'exact'; // Default
                        }
                    } elseif ($fieldName === 'redirectSrcMatch') {
                        // Normalize source match mode
                        $valueLower = strtolower($value);
                        if (in_array($valueLower, ['fullurl', 'full url', 'full', 'url'])) {
                            $redirect[$fieldName] = 'fullurl';
                        } else {
                            $redirect[$fieldName] = 'pathonly'; // Default
                        }
                    } elseif ($fieldName === 'sourceUrl' || $fieldName === 'destinationUrl') {
                        // Strip formula escape prefix for round-trip compatibility
                        $redirect[$fieldName] = $this->stripFormulaEscapePrefix($value);
                    } else {
                        $redirect[$fieldName] = $value;
                    }
                }
            }

            // Validate required fields
            if (empty($redirect['sourceUrl']) || empty($redirect['destinationUrl'])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $redirect['sourceUrl'] ?? '-',
                    'destinationUrl' => $redirect['destinationUrl'] ?? '-',
                    'error' => 'Missing required field(s): Source URL or Destination URL',
                ];
                continue;
            }

            // Validate source URL format
            $sourceUrl = $redirect['sourceUrl'];
            $isValidSourceUrl = false;

            // For regex/wildcard patterns, be more lenient but still require URL-like structure
            if (in_array($redirect['matchType'], ['regex', 'wildcard'])) {
                // Regex/wildcard: must start with / or ^ (regex start anchor) or contain URL-like characters
                $isValidSourceUrl = preg_match('#^[/^]|^https?://#i', $sourceUrl) === 1;
            } else {
                // Exact/prefix: must start with / or be a full URL
                $isValidSourceUrl = preg_match('#^/|^https?://#i', $sourceUrl) === 1;
            }

            if (!$isValidSourceUrl) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $redirect['destinationUrl'],
                    'error' => 'Invalid source URL format - must start with / or be a full URL (http/https)',
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
                    'error' => 'Source URL appears to be an email address',
                ];
                continue;
            }

            // Validate destination URL format
            $destinationUrl = $redirect['destinationUrl'];
            $isValidDestUrl = false;

            // Destination can be:
            // - Relative path (/...)
            // - Full URL (http/https)
            // - Special protocols: mailto:, tel:, whatsapp:, sms:, fax:, skype:, slack:, teams:
            // - Regex capture groups ($1, $2, etc.)
            $validProtocols = '#^/|^https?://|^mailto:|^tel:|^whatsapp:|^sms:|^fax:|^skype:|^slack://|^msteams:|^\$\d#i';
            if (preg_match($validProtocols, $destinationUrl) === 1) {
                $isValidDestUrl = true;
            }

            if (!$isValidDestUrl) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                    'error' => 'Invalid destination URL format - must be a path (/) or valid URL scheme',
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
                    'error' => 'Destination appears to be an email - use mailto: prefix',
                ];
                continue;
            }

            // Validate match type
            if (!in_array($redirect['matchType'], ['exact', 'regex', 'wildcard', 'prefix'])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                    'error' => 'Invalid match type: ' . $redirect['matchType'],
                ];
                continue;
            }

            // Validate status code
            if (!in_array($redirect['statusCode'], [301, 302, 303, 307, 308, 410])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                    'error' => 'Invalid status code: ' . $redirect['statusCode'],
                ];
                continue;
            }

            // Check for duplicates
            $duplicateKey = strtolower($redirect['sourceUrl']) . '|' . $redirect['matchType'] . '|' . $redirect['redirectSrcMatch'];
            if (isset($existingKeys[$duplicateKey])) {
                $duplicateRows[] = [
                    'sourceUrl' => $redirect['sourceUrl'],
                    'destinationUrl' => $redirect['destinationUrl'],
                    'reason' => 'Already exists with same source URL, match type, and source match mode',
                ];
                continue;
            }

            // Check for infinite loops
            if (strtolower($redirect['sourceUrl']) === strtolower($redirect['destinationUrl'])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'sourceUrl' => $sourceUrl,
                    'destinationUrl' => $destinationUrl,
                    'error' => 'Infinite loop: Source and destination are identical',
                ];
                continue;
            }

            $validRows[] = $redirect;
        }

        // Get count of existing redirects for backup info
        $existingCount = (new \craft\db\Query())
            ->from('{{%redirectmanager_redirects}}')
            ->count();

        // Store validated data in session
        Craft::$app->getSession()->set('redirect-import-validated', [
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
        Craft::$app->getSession()->set('redirect-preview', [
            'summary' => $summary,
            'validRows' => $validRows,
            'duplicateRows' => $duplicateRows,
            'errorRows' => $errorRows,
            'existingCount' => $existingCount,
            'createBackup' => $importData['createBackup'],
        ]);

        // Store validated data for the import action
        Craft::$app->getSession()->set('redirect-import-validated', [
            'validRows' => $validRows,
            'duplicateRows' => $duplicateRows,
            'errorRows' => $errorRows,
            'createBackup' => $importData['createBackup'],
        ]);

        // Redirect to preview page
        return $this->redirect('redirect-manager/import-export/preview');
    }

    /**
     * Perform the import
     *
     * @return Response|null
     * @since 5.0.0
     */
    public function actionImport(): ?Response
    {
        $this->requirePostRequest();
        $this->requireImportPermission();

        $validatedData = Craft::$app->getSession()->get('redirect-import-validated');
        $importData = Craft::$app->getSession()->get('redirect-import');

        if (!$validatedData) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Import session expired'));
            return $this->redirect('redirect-manager/import-export');
        }

        $validRows = $validatedData['validRows'];
        $settings = RedirectManager::$plugin->getSettings();
        $createBackup = $validatedData['createBackup'] && $settings->backupEnabled && $settings->backupOnImport;

        // Create backup if requested and there are existing redirects to backup
        $backupPath = null;
        if ($createBackup) {
            $existingCount = (new \craft\db\Query())
                ->from('{{%redirectmanager_redirects}}')
                ->count();

            if ($existingCount > 0) {
                $backupPath = RedirectManager::$plugin->backup->createBackup('import');
                if (!$backupPath) {
                    Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Failed to create backup'));
                    return $this->redirect('redirect-manager/import-export');
                }
            }
        }

        // Import redirects
        $imported = 0;
        $failed = 0;
        $db = Craft::$app->getDb();

        foreach ($validRows as $redirectData) {
            try {
                // Parse source URL to store parsed version
                // For regex/wildcard patterns, don't use parse_url() as it misinterprets ? and other regex chars
                if (in_array($redirectData['matchType'], ['regex', 'wildcard'])) {
                    $sourceUrlParsed = $redirectData['sourceUrl'];
                } else {
                    $parsedUrl = parse_url($redirectData['sourceUrl']);
                    $sourceUrlParsed = $redirectData['redirectSrcMatch'] === 'pathonly'
                        ? ($parsedUrl['path'] ?? '/')
                        : $redirectData['sourceUrl'];
                }

                // Detect creationType if not explicitly set
                // If creationType is already set and valid, use it; otherwise auto-detect
                $creationType = $redirectData['creationType'] ?? null;
                if (empty($creationType) || $creationType === 'import') {
                    // Auto-detect based on matchType and elementId
                    if (in_array($redirectData['matchType'], ['regex', 'wildcard'])) {
                        // Regex/wildcard patterns are always manual
                        $creationType = 'manual';
                    } elseif (!empty($redirectData['elementId']) && (int)$redirectData['elementId'] > 0) {
                        // If associated with an element, it was auto-created from entry changes
                        $creationType = 'entry-change';
                    } else {
                        // Default to manual for exact/prefix matches without element association
                        $creationType = 'manual';
                    }
                }

                $db->createCommand()->insert('{{%redirectmanager_redirects}}', [
                    'siteId' => $redirectData['siteId'],
                    'sourceUrl' => $redirectData['sourceUrl'],
                    'sourceUrlParsed' => $sourceUrlParsed,
                    'destinationUrl' => $redirectData['destinationUrl'],
                    'redirectSrcMatch' => $redirectData['redirectSrcMatch'],
                    'matchType' => $redirectData['matchType'],
                    'statusCode' => $redirectData['statusCode'],
                    'priority' => $redirectData['priority'],
                    'enabled' => $redirectData['enabled'],
                    'creationType' => $creationType,
                    'sourcePlugin' => $redirectData['sourcePlugin'] ?? 'redirect-manager',
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

        // Clean up session data (no temp file to delete - data was stored in session)
        Craft::$app->getSession()->remove('redirect-import');
        Craft::$app->getSession()->remove('redirect-import-validated');
        Craft::$app->getSession()->remove('redirect-preview');

        $message = Craft::t('redirect-manager', 'Successfully imported {imported} redirect(s).', ['imported' => $imported]);
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

        Craft::$app->getSession()->setNotice($message);
        return $this->redirect('redirect-manager/import-export');
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

        $label = match ($reason) {
            'restore' => Craft::t('redirect-manager', 'Before Restore'),
            'manual' => Craft::t('redirect-manager', 'Manual'),
            'scheduled' => Craft::t('redirect-manager', 'Scheduled'),
            default => Craft::t('redirect-manager', 'Before Import'),
        };

        return [
            'reasonLabel' => $label,
            'reasonValue' => $reason ?: 'import',
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
        $backupDir = RedirectManager::$plugin->backup->validateBackupDirname($dirname);

        if ($backupDir === null || !file_exists($backupDir . '/metadata.json')) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Backup not found'));
            return $this->redirect('redirect-manager/backups');
        }

        // Create temporary ZIP file
        $safeDirname = basename($backupDir);
        $zipPath = Craft::$app->getPath()->getTempPath() . '/redirect-backup-' . $safeDirname . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFile($backupDir . '/metadata.json', 'metadata.json');
            $zip->addFile($backupDir . '/redirects.json', 'redirects.json');
            $zip->close();

            return Craft::$app->getResponse()->sendFile($zipPath, 'redirect-backup-' . $safeDirname . '.zip', [
                'inline' => false,
            ]);
        }

        Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Failed to create backup ZIP'));
        return $this->redirect('redirect-manager/backups');
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
        $backupDir = RedirectManager::$plugin->backup->validateBackupDirname($dirname);

        if ($backupDir === null || !file_exists($backupDir . '/redirects.json')) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Backup not found'));
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => Craft::t('redirect-manager', 'Backup not found'),
                ]);
            }
            return $this->redirect('redirect-manager/backups');
        }

        try {
            // Create backup BEFORE restoring (in case restore fails)
            $preRestoreBackup = RedirectManager::$plugin->backup->createBackup('restore');
            if (!$preRestoreBackup) {
                $this->logWarning('No backup created before restore (no existing redirects to backup)');
            }

            // Read redirects.json
            $redirects = json_decode(file_get_contents($backupDir . '/redirects.json'), true);

            if (!$redirects || !is_array($redirects)) {
                throw new \Exception('Invalid backup file format');
            }

            $db = Craft::$app->getDb();

            // Delete all current redirects
            $db->createCommand()->delete('{{%redirectmanager_redirects}}')->execute();

            // Restore redirects from backup
            $restored = 0;
            foreach ($redirects as $redirect) {
                // Remove id to let database auto-generate new IDs
                unset($redirect['id']);

                $db->createCommand()->insert('{{%redirectmanager_redirects}}', $redirect)->execute();
                $restored++;
            }

            // Clear redirect cache
            RedirectManager::$plugin->redirects->invalidateCaches();

            $this->logInfo('Backup restored', ['dirname' => basename($backupDir), 'count' => $restored]);

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
        } catch (\Exception $e) {
            $this->logError('Restore failed', ['error' => $e->getMessage()]);
            $errorMessage = Craft::t('redirect-manager', 'Failed to restore backup: {error}', ['error' => $e->getMessage()]);
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
        $backupDir = RedirectManager::$plugin->backup->validateBackupDirname($dirname);

        if ($backupDir === null) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Backup not found'));
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'message' => Craft::t('redirect-manager', 'Backup not found'),
                ]);
            }
            return $this->redirect('redirect-manager/backups');
        }

        try {
            // Delete entire backup directory
            FileHelper::removeDirectory($backupDir);

            $this->logInfo('Backup deleted', ['dirname' => basename($backupDir)]);

            $successMessage = Craft::t('redirect-manager', 'Backup deleted successfully');
            Craft::$app->getSession()->setNotice($successMessage);

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => $successMessage,
                ]);
            }

            return $this->redirect('redirect-manager/backups');
        } catch (\Exception $e) {
            $this->logError('Delete backup failed', ['error' => $e->getMessage()]);
            $errorMessage = Craft::t('redirect-manager', 'Failed to delete backup: {error}', ['error' => $e->getMessage()]);
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
     * Strip formula escape prefix from imported values
     *
     * Reverses the sanitization done during export so that round-trip
     * export/import preserves the original value.
     *
     * @param string $value
     * @return string
     */
    private function stripFormulaEscapePrefix(string $value): string
    {
        // If value starts with ' followed by a formula character, strip the '
        if (preg_match("/^'(\\s*[=+\\-@])/", $value)) {
            return substr($value, 1);
        }

        return $value;
    }

    /**
     * Require any backup-related permission (view access)
     *
     * @return void
     * @since 5.23.0
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
            throw new ForbiddenHttpException('User does not have permission to manage backups');
        }
    }

    /**
     * Require a specific backup permission (or manageBackups)
     *
     * @param string $permission
     * @return void
     * @since 5.23.0
     */
    private function requireBackupPermission(string $permission): void
    {
        $user = Craft::$app->getUser();
        if (!$user->checkPermission('redirectManager:manageBackups') && !$user->checkPermission($permission)) {
            throw new ForbiddenHttpException('User does not have permission to manage backups');
        }
    }

    /**
     * Check if user can import redirects
     *
     * @return bool
     * @since 5.23.0
     */
    private function canImport(): bool
    {
        $user = Craft::$app->getUser();
        return $user->checkPermission('redirectManager:manageImportExport') ||
            $user->checkPermission('redirectManager:importRedirects');
    }

    /**
     * Check if user can export redirects
     *
     * @return bool
     * @since 5.23.0
     */
    private function canExport(): bool
    {
        $user = Craft::$app->getUser();
        return $user->checkPermission('redirectManager:manageImportExport') ||
            $user->checkPermission('redirectManager:exportRedirects');
    }

    /**
     * Check if user can view import history
     *
     * @return bool
     * @since 5.24.0
     */
    private function canViewHistory(): bool
    {
        $user = Craft::$app->getUser();
        return $user->checkPermission('redirectManager:manageImportExport') ||
            $user->checkPermission('redirectManager:viewImportHistory');
    }

    /**
     * Require import permission (legacy manageImportExport or importRedirects)
     *
     * @return void
     * @since 5.23.0
     */
    private function requireImportPermission(): void
    {
        if (!$this->canImport()) {
            throw new ForbiddenHttpException('User does not have permission to import redirects');
        }
    }

    /**
     * Require clear history permission
     *
     * @return void
     * @since 5.24.0
     */
    private function requireClearImportHistoryPermission(): void
    {
        if (!Craft::$app->getUser()->checkPermission('redirectManager:manageImportExport') &&
            !Craft::$app->getUser()->checkPermission('redirectManager:clearImportHistory')) {
            throw new ForbiddenHttpException('User does not have permission to clear import history');
        }
    }

    /**
     * Require export permission (legacy manageImportExport or exportRedirects)
     *
     * @return void
     * @since 5.23.0
     */
    private function requireExportPermission(): void
    {
        if (!$this->canExport()) {
            throw new ForbiddenHttpException('User does not have permission to export redirects');
        }
    }

    /**
     * Require any import/export permission for the main page
     *
     * @return void
     * @since 5.23.0
     */
    private function requireAnyImportExportPermission(): void
    {
        if (!$this->canImport() && !$this->canExport() && !$this->canViewHistory()) {
            throw new ForbiddenHttpException('User does not have permission to manage import/export');
        }
    }
}

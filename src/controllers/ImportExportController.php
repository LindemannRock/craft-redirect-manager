<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\redirectmanager\controllers;

use Craft;
use craft\helpers\FileHelper;
use craft\web\Controller;
use craft\web\UploadedFile;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\redirectmanager\RedirectManager;
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
        $this->setLoggingHandle('redirect-manager');
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
        $backups = $this->getBackups();

        return $this->renderTemplate('redirect-manager/import-export/index', [
            'settings' => $settings,
            'backups' => $backups,
        ]);
    }

    /**
     * Export redirects to CSV
     *
     * @return Response
     */
    public function actionExport(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageImportExport');

        // Check if specific redirects were selected
        $redirectIdsJson = Craft::$app->getRequest()->getBodyParam('redirectIds');
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

        // Create CSV content
        $csv = [];
        $csv[] = $headers;

        foreach ($redirects as $redirect) {
            $csv[] = [
                $redirect['sourceUrl'],
                $redirect['destinationUrl'],
                $redirect['siteId'] ?? '',
                $redirect['redirectSrcMatch'],
                $redirect['matchType'],
                $redirect['statusCode'],
                $redirect['priority'],
                $redirect['enabled'] ? '1' : '0',
                $redirect['hitCount'] ?? 0,
                $redirect['lastHit'] ?? '',
                $redirect['creationType'],
                $redirect['sourcePlugin'] ?? 'redirect-manager',
            ];
        }

        // Generate CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        // Send as download
        $settings = RedirectManager::$plugin->getSettings();
        $filenamePart = strtolower(str_replace(' ', '-', $settings->getPluralLowerDisplayName()));
        $filename = $filenamePart . '-' . date('Y-m-d-His') . '.csv';

        return Craft::$app->getResponse()
            ->sendContentAsFile($csvContent, $filename, [
                'mimeType' => 'text/csv',
            ]);
    }

    /**
     * Upload and parse CSV file
     *
     * @return Response
     */
    public function actionUpload(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageImportExport');

        $file = UploadedFile::getInstanceByName('csvFile');

        if (!$file) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Please select a CSV file to upload'));
            return $this->redirect('redirect-manager/import-export');
        }

        // Validate file type
        $extension = strtolower($file->getExtension());
        if (!in_array($extension, ['csv', 'txt'])) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Invalid file type. Please upload a CSV file.'));
            return $this->redirect('redirect-manager/import-export');
        }

        // Get delimiter
        $delimiter = Craft::$app->getRequest()->getBodyParam('delimiter', ',');
        if ($delimiter === "\t") {
            $delimiter = "\t"; // Handle tab character
        }

        $createBackup = (bool)Craft::$app->getRequest()->getBodyParam('createBackup', true);

        // Save file temporarily
        $tempPath = Craft::$app->getPath()->getTempPath() . '/redirect-import-' . uniqid() . '.csv';
        $file->saveAs($tempPath);

        // Parse CSV
        try {
            $handle = fopen($tempPath, 'r');
            $headers = fgetcsv($handle, 0, $delimiter);

            if (!$headers) {
                throw new \Exception('Could not read CSV headers');
            }

            // Validate delimiter - check if we got only 1 column (likely wrong delimiter)
            if (count($headers) === 1 && strpos($headers[0], ',') !== false) {
                throw new \Exception('CSV appears to use comma (,) delimiter but you selected: ' . ($delimiter === "\t" ? 'Tab' : $delimiter) . '. Please select the correct delimiter.');
            } elseif (count($headers) === 1 && strpos($headers[0], ';') !== false) {
                throw new \Exception('CSV appears to use semicolon (;) delimiter but you selected: ' . ($delimiter === "\t" ? 'Tab' : $delimiter) . '. Please select the correct delimiter.');
            } elseif (count($headers) === 1 && strpos($headers[0], '|') !== false) {
                throw new \Exception('CSV appears to use pipe (|) delimiter but you selected: ' . ($delimiter === "\t" ? 'Tab' : $delimiter) . '. Please select the correct delimiter.');
            } elseif (count($headers) === 1 && strpos($headers[0], "\t") !== false) {
                throw new \Exception('CSV appears to use Tab delimiter but you selected: ' . $delimiter . '. Please select the correct delimiter.');
            }

            // Read first few rows for preview
            $previewRows = [];
            $rowCount = 0;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $rowCount < 5) {
                $previewRows[] = $row;
                $rowCount++;
            }

            // Count total rows
            while (fgetcsv($handle, 0, $delimiter) !== false) {
                $rowCount++;
            }

            fclose($handle);

            // Store in session for next steps
            Craft::$app->getSession()->set('redirect-import', [
                'filePath' => $tempPath,
                'delimiter' => $delimiter,
                'headers' => $headers,
                'rowCount' => $rowCount,
                'createBackup' => $createBackup,
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
     */
    public function actionMap(): Response
    {
        $this->requirePermission('redirectManager:manageImportExport');

        // Get data from session
        $importData = Craft::$app->getSession()->get('redirect-import');

        if (!$importData) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'No import data found. Please upload a CSV file.'));
            return $this->redirect('redirect-manager/import-export');
        }

        // Parse CSV to get preview rows
        $previewRows = [];
        try {
            $handle = fopen($importData['filePath'], 'r');
            fgetcsv($handle, 0, $importData['delimiter']); // Skip headers

            $rowCount = 0;
            while (($row = fgetcsv($handle, 0, $importData['delimiter'])) !== false && $rowCount < 5) {
                $previewRows[] = $row;
                $rowCount++;
            }
            fclose($handle);
        } catch (\Exception $e) {
            $this->logError('Failed to read CSV for mapping', ['error' => $e->getMessage()]);
        }

        return $this->renderTemplate('redirect-manager/import-export/map', [
            'headers' => $importData['headers'],
            'previewRows' => $previewRows,
            'rowCount' => $importData['rowCount'],
            'delimiter' => $importData['delimiter'],
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
        $this->requirePermission('redirectManager:manageImportExport');

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

        if (!$importData || !file_exists($importData['filePath'])) {
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

        // Parse CSV and validate
        $handle = fopen($importData['filePath'], 'r');
        $headers = fgetcsv($handle, 0, $importData['delimiter']); // Skip header row

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

        while (($row = fgetcsv($handle, 0, $importData['delimiter'])) !== false) {
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
                    } else {
                        $redirect[$fieldName] = $value;
                    }
                }
            }

            // Validate required fields
            if (empty($redirect['sourceUrl']) || empty($redirect['destinationUrl'])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'data' => implode(',', $row),
                    'error' => 'Missing required field(s): Source URL or Destination URL',
                ];
                continue;
            }

            // Validate match type
            if (!in_array($redirect['matchType'], ['exact', 'regex', 'wildcard', 'prefix'])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'data' => implode(',', $row),
                    'error' => 'Invalid match type: ' . $redirect['matchType'],
                ];
                continue;
            }

            // Validate status code
            if (!in_array($redirect['statusCode'], [301, 302, 303, 307, 308, 410])) {
                $errorRows[] = [
                    'rowNumber' => $rowNumber,
                    'data' => implode(',', $row),
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
                    'data' => implode(',', $row),
                    'error' => 'Infinite loop: Source and destination are identical',
                ];
                continue;
            }

            $validRows[] = $redirect;
        }

        fclose($handle);

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
     */
    public function actionImport(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageImportExport');

        $validatedData = Craft::$app->getSession()->get('redirect-import-validated');

        if (!$validatedData) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Import session expired'));
            return $this->redirect('redirect-manager/import-export');
        }

        $validRows = $validatedData['validRows'];
        $createBackup = $validatedData['createBackup'];

        // Create backup if requested and there are existing redirects to backup
        $backupPath = null;
        if ($createBackup) {
            $existingCount = (new \craft\db\Query())
                ->from('{{%redirectmanager_redirects}}')
                ->count();

            if ($existingCount > 0) {
                $backupPath = $this->createBackup();
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
                $parsedUrl = parse_url($redirectData['sourceUrl']);
                $sourceUrlParsed = $redirectData['redirectSrcMatch'] === 'pathonly'
                    ? ($parsedUrl['path'] ?? '/')
                    : $redirectData['sourceUrl'];

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
                    'creationType' => $redirectData['creationType'] ?? 'import',
                    'sourcePlugin' => $redirectData['sourcePlugin'] ?? 'redirect-manager',
                    'hitCount' => $redirectData['hitCount'] ?? 0,
                    'lastHit' => $redirectData['lastHit'],
                    'dateCreated' => date('Y-m-d H:i:s'),
                    'dateUpdated' => date('Y-m-d H:i:s'),
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

        // Delete temp file BEFORE cleaning up session
        $importData = Craft::$app->getSession()->get('redirect-import');
        if ($importData && file_exists($importData['filePath'])) {
            @unlink($importData['filePath']);
        }

        // Clean up session data
        Craft::$app->getSession()->remove('redirect-import');
        Craft::$app->getSession()->remove('redirect-import-validated');
        Craft::$app->getSession()->remove('redirect-preview');

        $message = Craft::t('redirect-manager', 'Successfully imported {imported} redirect(s).', ['imported' => $imported]);
        if ($failed > 0) {
            $message .= ' ' . Craft::t('redirect-manager', '{failed} failed.', ['failed' => $failed]);
        }

        Craft::$app->getSession()->setNotice($message);
        return $this->redirect('redirect-manager/import-export');
    }

    /**
     * Create backup of existing redirects
     *
     * @param string $reason Reason for backup (import, restore, manual)
     * @return string|null Backup directory path or null on failure
     */
    private function createBackup(string $reason = 'import'): ?string
    {
        try {
            // Get all redirects
            $redirects = (new \craft\db\Query())
                ->from('{{%redirectmanager_redirects}}')
                ->all();

            if (empty($redirects)) {
                return null; // No redirects to backup
            }

            $settings = RedirectManager::$plugin->getSettings();
            $timestamp = date('Y-m-d_H-i-s');

            // Create backup directory using configured path
            $backupDir = $settings->getBackupPath() . '/' . $timestamp;
            FileHelper::createDirectory($backupDir);

            // Create metadata.json
            $metadata = [
                'date' => $timestamp,
                'timestamp' => time(),
                'reason' => $reason,
                'user' => Craft::$app->getUser()->getIdentity()->username ?? 'unknown',
                'userId' => Craft::$app->getUser()->getId(),
                'redirectCount' => count($redirects),
                'craftVersion' => Craft::$app->getVersion(),
                'pluginVersion' => RedirectManager::$plugin->getVersion(),
            ];

            file_put_contents($backupDir . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));

            // Create redirects.json
            file_put_contents($backupDir . '/redirects.json', json_encode($redirects, JSON_PRETTY_PRINT));

            $this->logInfo('Backup created', ['path' => $backupDir, 'count' => count($redirects), 'reason' => $reason]);

            return $backupDir;
        } catch (\Exception $e) {
            $this->logError('Backup failed', ['error' => $e->getMessage(), 'reason' => $reason]);
            return null;
        }
    }

    /**
     * Get all backups from filesystem
     *
     * @return array
     */
    private function getBackups(): array
    {
        $settings = RedirectManager::$plugin->getSettings();
        $backupDir = $settings->getBackupPath();

        if (!is_dir($backupDir)) {
            return [];
        }

        $backups = [];
        $dirs = glob($backupDir . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $metadataFile = $dir . '/metadata.json';
            if (file_exists($metadataFile)) {
                $metadata = json_decode(file_get_contents($metadataFile), true);
                $metadata['path'] = $dir;
                $metadata['dirname'] = basename($dir);

                // Calculate total size of backup directory
                $totalSize = 0;
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $file) {
                    if ($file->isFile()) {
                        $totalSize += $file->getSize();
                    }
                }

                // Format size for display
                $metadata['size'] = $totalSize;
                $metadata['formattedSize'] = Craft::$app->getFormatter()->asShortSize($totalSize, 2);

                $backups[] = $metadata;
            }
        }

        // Sort by timestamp descending (newest first)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $backups;
    }

    /**
     * Download backup as ZIP
     *
     * @return Response
     */
    public function actionDownloadBackup(): Response
    {
        $this->requirePermission('redirectManager:manageImportExport');

        $settings = RedirectManager::$plugin->getSettings();
        $dirname = Craft::$app->getRequest()->getQueryParam('dirname');
        $backupDir = $settings->getBackupPath() . '/' . $dirname;

        if (!is_dir($backupDir) || !file_exists($backupDir . '/metadata.json')) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Backup not found'));
            return $this->redirect('redirect-manager/import-export');
        }

        // Create temporary ZIP file
        $zipPath = Craft::$app->getPath()->getTempPath() . '/redirect-backup-' . $dirname . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFile($backupDir . '/metadata.json', 'metadata.json');
            $zip->addFile($backupDir . '/redirects.json', 'redirects.json');
            $zip->close();

            return Craft::$app->getResponse()->sendFile($zipPath, 'redirect-backup-' . $dirname . '.zip', [
                'inline' => false,
            ]);
        }

        Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Failed to create backup ZIP'));
        return $this->redirect('redirect-manager/import-export');
    }

    /**
     * Restore from backup
     *
     * @return Response|null
     */
    public function actionRestoreBackup(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageImportExport');

        $settings = RedirectManager::$plugin->getSettings();
        $dirname = Craft::$app->getRequest()->getBodyParam('dirname');
        $backupDir = $settings->getBackupPath() . '/' . $dirname;

        if (!is_dir($backupDir) || !file_exists($backupDir . '/redirects.json')) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Backup not found'));
            return $this->redirect('redirect-manager/import-export');
        }

        try {
            // Create backup BEFORE restoring (in case restore fails)
            $preRestoreBackup = $this->createBackup('restore');
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

            $this->logInfo('Backup restored', ['dirname' => $dirname, 'count' => $restored]);

            Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'Successfully restored {count} redirect(s) from backup', ['count' => $restored]));
            return $this->redirect('redirect-manager/redirects');
        } catch (\Exception $e) {
            $this->logError('Restore failed', ['error' => $e->getMessage()]);
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Failed to restore backup: {error}', ['error' => $e->getMessage()]));
            return $this->redirect('redirect-manager/import-export');
        }
    }

    /**
     * Delete backup
     *
     * @return Response|null
     */
    public function actionDeleteBackup(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('redirectManager:manageImportExport');

        $settings = RedirectManager::$plugin->getSettings();
        $dirname = Craft::$app->getRequest()->getBodyParam('dirname');
        $backupDir = $settings->getBackupPath() . '/' . $dirname;

        if (!is_dir($backupDir)) {
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Backup not found'));
            return $this->redirect('redirect-manager/import-export');
        }

        try {
            // Delete entire backup directory
            FileHelper::removeDirectory($backupDir);

            $this->logInfo('Backup deleted', ['dirname' => $dirname]);

            Craft::$app->getSession()->setNotice(Craft::t('redirect-manager', 'Backup deleted successfully'));
            return $this->redirect('redirect-manager/import-export#history');
        } catch (\Exception $e) {
            $this->logError('Delete backup failed', ['error' => $e->getMessage()]);
            Craft::$app->getSession()->setError(Craft::t('redirect-manager', 'Failed to delete backup'));
            return $this->redirect('redirect-manager/import-export');
        }
    }
}

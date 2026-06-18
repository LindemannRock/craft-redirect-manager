<?php
/**
 * Redirect Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\redirectmanager\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Security utilities for Redirect Manager
 *
 * @since 5.1.0
 */
class SecurityController extends Controller
{
    private const API_TOKEN_ENV_VAR = 'REDIRECT_MANAGER_API_TOKEN';

    private const IP_SALT_ENV_VAR = 'REDIRECT_MANAGER_IP_SALT';

    /**
     * Generate a secure token for the JSON API endpoint and optionally update .env file.
     *
     * @since 5.33.0
     */
    public function actionGenerateApiToken(): int
    {
        $this->stdout("Redirect Manager - JSON API Token Generator\n", Console::FG_CYAN);
        $this->stdout(str_repeat('=', 60) . "\n\n");

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $this->stdout("Generated secure API token:\n", Console::FG_YELLOW);
        $this->stdout($token . "\n\n", Console::FG_GREEN);

        $envPath = $this->envPath();
        if (!file_exists($envPath)) {
            $this->stdout("Warning: .env file not found at: {$envPath}\n\n", Console::FG_RED);
            $this->stdout("Manually add this to your .env file:\n", Console::FG_CYAN);
            $this->stdout(self::API_TOKEN_ENV_VAR . "=\"{$token}\"\n\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        if (!$this->confirm('Write this token to the project .env file?', true)) {
            $this->stdout("\nOperation cancelled. Add this manually when ready:\n", Console::FG_YELLOW);
            $this->stdout(self::API_TOKEN_ENV_VAR . "=\"{$token}\"\n\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $envContent = file_get_contents($envPath);
        if ($envContent === false) {
            $this->stdout("\nError: Could not read .env file\n", Console::FG_RED);
            $this->stdout("Please add manually:\n", Console::FG_CYAN);
            $this->stdout(self::API_TOKEN_ENV_VAR . "=\"{$token}\"\n\n", Console::FG_GREEN);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $tokenExists = preg_match('/^' . self::API_TOKEN_ENV_VAR . '=/m', $envContent) === 1;

        if ($tokenExists) {
            $this->stdout("Existing " . self::API_TOKEN_ENV_VAR . " found in .env\n\n", Console::FG_YELLOW);
            $this->stdout("WARNING: ", Console::FG_RED);
            $this->stdout("Replacing the token will immediately invalidate consumers using the old token.\n\n");

            if (!$this->confirm('Do you want to replace the existing API token?', false)) {
                $this->stdout("\nOperation cancelled. Existing API token unchanged.\n", Console::FG_YELLOW);
                return ExitCode::OK;
            }

            $envContent = preg_replace(
                '/^' . self::API_TOKEN_ENV_VAR . '=.*$/m',
                self::API_TOKEN_ENV_VAR . '="' . $token . '"',
                $envContent
            );
            $action = 'Updated';
        } else {
            if ($envContent !== '' && substr($envContent, -1) !== "\n") {
                $envContent .= "\n";
            }
            $envContent .= "\n# Redirect Manager JSON API Token (generated " . date('Y-m-d H:i:s') . ")\n";
            $envContent .= self::API_TOKEN_ENV_VAR . '="' . $token . '"' . "\n";
            $action = 'Added';
        }

        if ($envContent === null || file_put_contents($envPath, $envContent) === false) {
            $this->stdout("\nError: Could not write to .env file\n", Console::FG_RED);
            $this->stdout("Please add manually:\n", Console::FG_CYAN);
            $this->stdout(self::API_TOKEN_ENV_VAR . "=\"{$token}\"\n\n", Console::FG_GREEN);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\n✓ {$action} " . self::API_TOKEN_ENV_VAR . " in .env file\n", Console::FG_GREEN);
        $this->stdout("Location: {$envPath}\n\n", Console::FG_CYAN);

        $this->stdout("Important:\n", Console::FG_YELLOW);
        $this->stdout("• Never commit .env to version control\n");
        $this->stdout("• Store the token securely (password manager recommended)\n");
        $this->stdout("• Copy the token to every environment that should expose the JSON API endpoint\n");
        $this->stdout("• Rotating the token requires updating every external consumer\n\n");

        return ExitCode::OK;
    }

    /**
     * Generate a secure salt for IP hashing and optionally update .env file
     *
     * @return int
     */
    public function actionGenerateSalt(): int
    {
        $this->stdout("Redirect Manager - IP Hash Salt Generator\n", Console::FG_CYAN);
        $this->stdout(str_repeat('=', 60) . "\n\n");

        // Generate cryptographically secure random salt
        $salt = bin2hex(random_bytes(32)); // 64-character hex string

        $this->stdout("Generated secure salt:\n", Console::FG_YELLOW);
        $this->stdout($salt . "\n\n", Console::FG_GREEN);

        // Check if .env file exists and try to update it
        $envPath = $this->envPath();

        if (!file_exists($envPath)) {
            $this->stdout("Warning: .env file not found at: {$envPath}\n\n", Console::FG_RED);
            $this->stdout("Manually add this to your .env file:\n", Console::FG_CYAN);
            $this->stdout(self::IP_SALT_ENV_VAR . "=\"{$salt}\"\n\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        // Read current .env file
        $envContent = file_get_contents($envPath);
        if ($envContent === false) {
            $this->stdout("\nError: Could not read .env file\n", Console::FG_RED);
            $this->stdout("Please add manually:\n", Console::FG_CYAN);
            $this->stdout(self::IP_SALT_ENV_VAR . "=\"{$salt}\"\n\n", Console::FG_GREEN);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $saltExists = preg_match('/^' . self::IP_SALT_ENV_VAR . '=/m', $envContent);

        if ($saltExists) {
            $this->stdout("Existing " . self::IP_SALT_ENV_VAR . " found in .env\n\n", Console::FG_YELLOW);
            $this->stdout("WARNING: ", Console::FG_RED);
            $this->stdout("Replacing the salt will break unique visitor tracking!\n");
            $this->stdout("All existing analytics will use the old hash values.\n\n");

            if (!$this->confirm('Do you want to replace the existing salt?', false)) {
                $this->stdout("\nOperation cancelled. Existing salt unchanged.\n", Console::FG_YELLOW);
                return ExitCode::OK;
            }

            // Replace existing salt
            $envContent = preg_replace(
                '/^' . self::IP_SALT_ENV_VAR . '=.*$/m',
                self::IP_SALT_ENV_VAR . '="' . $salt . '"',
                $envContent
            );
            $action = "Updated";
        } else {
            // Append new salt
            if (!empty($envContent) && substr($envContent, -1) !== "\n") {
                $envContent .= "\n";
            }
            $envContent .= "\n# Redirect Manager IP Hash Salt (generated " . date('Y-m-d H:i:s') . ")\n";
            $envContent .= self::IP_SALT_ENV_VAR . '="' . $salt . '"' . "\n";
            $action = "Added";
        }

        // Write back to .env file
        if (file_put_contents($envPath, $envContent) === false) {
            $this->stdout("\nError: Could not write to .env file\n", Console::FG_RED);
            $this->stdout("Please add manually:\n", Console::FG_CYAN);
            $this->stdout(self::IP_SALT_ENV_VAR . "=\"{$salt}\"\n\n", Console::FG_GREEN);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\n✓ {$action} " . self::IP_SALT_ENV_VAR . " in .env file\n", Console::FG_GREEN);
        $this->stdout("Location: {$envPath}\n\n", Console::FG_CYAN);

        $this->stdout("Important:\n", Console::FG_YELLOW);
        $this->stdout("• Never commit .env to version control\n");
        $this->stdout("• Store the salt securely (password manager recommended)\n");
        $this->stdout("• Use the SAME salt across all environments (dev/staging/production)\n");
        $this->stdout("• Changing the salt will reset unique visitor tracking\n\n");

        return ExitCode::OK;
    }

    private function envPath(): string
    {
        return defined('CRAFT_BASE_PATH')
            ? CRAFT_BASE_PATH . DIRECTORY_SEPARATOR . '.env'
            : \Craft::getAlias('@root/.env');
    }
}

<?php
/**
 * Email Configuration
 * Copy .env.example to .env and fill in your values.
 * Never hardcode credentials here — always use the .env file.
 */

// Load .env file if it exists (simple key=value parser)
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

define('EMAIL_USER',    $_ENV['EMAIL_USER']    ?? '');
define('EMAIL_PASS',    $_ENV['EMAIL_PASS']    ?? '');
define('EMAIL_HOST',    $_ENV['EMAIL_HOST']    ?? 'smtp.gmail.com');
define('EMAIL_PORT',    $_ENV['EMAIL_PORT']    ?? 587);
define('EMAIL_FROM',    $_ENV['EMAIL_FROM']    ?? EMAIL_USER);
define('EMAIL_NAME',    $_ENV['EMAIL_NAME']    ?? 'Barangay 853');
<?php
// config/database.php
// ByteBandits – QA Management System
// Centralized DB connection using mysqli

function loadProjectEnvForDatabase(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $envPath = dirname(__DIR__) . '/.env';
    if (!is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $separatorPos = strpos($line, '=');
        if ($separatorPos === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separatorPos));
        $value = trim(substr($line, $separatorPos + 1));
        if ($name === '') {
            continue;
        }

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function requireEnvValue(string $name, bool $allowEmpty = false): string
{
    $value = getenv($name);
    if ($value === false) {
        http_response_code(500);
        die(json_encode([
            'status' => 'error',
            'message' => 'Missing required environment variable: ' . $name,
        ]));
    }

    $stringValue = (string)$value;
    if (!$allowEmpty && trim($stringValue) === '') {
        http_response_code(500);
        die(json_encode([
            'status' => 'error',
            'message' => 'Environment variable cannot be empty: ' . $name,
        ]));
    }

    if (!$allowEmpty) {
        return trim($stringValue);
    }

    return $stringValue;
}

loadProjectEnvForDatabase();

if (!defined('DB_HOST')) {
    define('DB_HOST', requireEnvValue('DB_HOST'));
}
if (!defined('DB_USER')) {
    define('DB_USER', requireEnvValue('DB_USER'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', requireEnvValue('DB_PASS', true));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', requireEnvValue('DB_NAME'));
}

function getConnection(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

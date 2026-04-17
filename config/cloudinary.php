<?php
// config/cloudinary.php
// Cloudinary configuration for policy document uploads.

function loadProjectEnv(): void
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

function getCloudinaryConfig(): array
{
    loadProjectEnv();

    $config = [
        'cloud_name' => '',
        'api_key' => '',
        'api_secret' => '',
        'folder' => 'qa_system/policies',
    ];

    $cloudinaryUrl = trim((string)getenv('CLOUDINARY_URL'));
    if ($cloudinaryUrl !== '' && stripos($cloudinaryUrl, 'cloudinary://') === 0) {
        $parts = parse_url($cloudinaryUrl);
        if (is_array($parts)) {
            if (!empty($parts['host'])) {
                $config['cloud_name'] = (string)$parts['host'];
            }
            if (!empty($parts['user'])) {
                $config['api_key'] = (string)$parts['user'];
            }
            if (!empty($parts['pass'])) {
                $config['api_secret'] = (string)$parts['pass'];
            }
        }
    }

    $cloudName = trim((string)getenv('CLOUDINARY_CLOUD_NAME'));
    $apiKey = trim((string)getenv('CLOUDINARY_API_KEY'));
    $apiSecret = trim((string)getenv('CLOUDINARY_API_SECRET'));
    $folder = trim((string)getenv('CLOUDINARY_FOLDER'));

    if ($cloudName !== '') {
        $config['cloud_name'] = $cloudName;
    }
    if ($apiKey !== '') {
        $config['api_key'] = $apiKey;
    }
    if ($apiSecret !== '') {
        $config['api_secret'] = $apiSecret;
    }
    if ($folder !== '') {
        $config['folder'] = $folder;
    }

    return $config;
}

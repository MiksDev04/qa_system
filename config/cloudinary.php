<?php
// config/cloudinary.php
// Cloudinary configuration for policy document uploads.

function getCloudinaryConfig(): array
{
    $config = [
        'cloud_name' => 'dcumsgzer',
        'api_key' => '285221998566549',
        'api_secret' => 'p-wDqYZiSyCfYkzUN_bwXk_6F58',
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

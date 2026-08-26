<?php
declare(strict_types=1);

if (!defined('URLSHORTM_APP')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Forbidden');
}

$env = function (string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return $value === false ? $default : $value;
};

$localConfig = [];
$localFile = __DIR__ . '/../config.local.php';
if (is_file($localFile)) {
    $localConfig = (array) require $localFile;
}

$app = [
    'debug'          => $env('URLSHORTM_DEBUG') === '1' || ($localConfig['debug'] ?? false),
    'slug_length'    => 6,
    'slug_charset'   => '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
    'slug_retry'     => 5,
    'max_url_length' => 2048,
    'uploads'        => [
        'dir'       => dirname(__DIR__) . '/uploads',
        'max_bytes' => 5 * 1024 * 1024,
        'extensions'=> ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'],
    ],
    'rate_limit'     => [
        'shorten' => [
            'limit'  => (int) ($localConfig['rate_shorten_limit'] ?? 50),
            'window' => 3600,
        ],
    ],
];

$dbDefaults = [
    'driver'  => 'mysql',
    'host'    => '127.0.0.1',
    'port'    => '3306',
    'name'    => 'urlshortm',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
];

$localDb = $localConfig['db'] ?? [];

$db = array_merge(
    $dbDefaults,
    $localDb,
    [
        'driver'  => $env('URLSHORTM_DB_DRIVER') ?? ($localDb['driver'] ?? $dbDefaults['driver']),
        'host'    => $env('URLSHORTM_DB_HOST') ?? ($localDb['host'] ?? $dbDefaults['host']),
        'port'    => $env('URLSHORTM_DB_PORT') ?? ($localDb['port'] ?? $dbDefaults['port']),
        'name'    => $env('URLSHORTM_DB_NAME') ?? ($localDb['name'] ?? $dbDefaults['name']),
        'user'    => $env('URLSHORTM_DB_USER') ?? ($localDb['user'] ?? $dbDefaults['user']),
        'pass'    => $env('URLSHORTM_DB_PASS') ?? ($localDb['pass'] ?? $dbDefaults['pass']),
        'charset' => $env('URLSHORTM_DB_CHARSET') ?? ($localDb['charset'] ?? $dbDefaults['charset']),
    ]
);

return [
    'app' => $app,
    'db'  => $db,
];

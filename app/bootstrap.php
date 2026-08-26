<?php
declare(strict_types=1);

define('URLSHORTM_APP', true);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/helpers_global.php';
require_once __DIR__ . '/db.php';

\App\Config::load(require __DIR__ . '/config.php');

$debug = (bool) \App\Config::get('app.debug', false);

if ($debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

set_exception_handler(function (Throwable $e) use ($debug): void {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');

    error_log('[UrlShortM] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if ($debug) {
        echo '<pre>' . \App\escape((string) $e) . '</pre>';
        exit;
    }

    echo render('error', ['message' => 'Đã có lỗi xảy ra, vui lòng thử lại sau.']);
    exit;
});

$sessionOptions = [
    'httponly' => true,
    'samesite' => 'Lax',
];

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $sessionOptions['secure'] = true;
}

session_set_cookie_params($sessionOptions);
session_start(['use_strict_mode' => true]);

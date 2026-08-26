<?php
declare(strict_types=1);

/**
 * Router cho `php -S` (mô phỏng Apache rewrite + chặn thư mục nội bộ).
 * Dùng: php -S 127.0.0.1:PORT -t <docroot> tests/router.php
 */

$docroot = dirname(__DIR__);
$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = $docroot . '/' . ltrim($uri, '/');

if ($uri !== '/' && is_file($file)) {
    return false; // serve static thật (assets)
}

if (preg_match('#^/(app|database|tests|scripts|\.mark-repo)(/|$)#', $uri) === 1 || $uri === '/config.local.php') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Forbidden');
}

require $docroot . '/index.php';

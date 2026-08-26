<?php
declare(strict_types=1);

// Sao chép file này thành config.local.php và sửa khi cần.
// Không commit config.local.php (đã trong .gitignore).
// Không truy cập file này trực tiếp qua web.

if (!defined('URLSHORTM_APP')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Forbidden');
}

return [
    'debug' => false,

    // 'rate_shorten_limit' => 50,

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => '3306',
        'name'    => 'urlshortm',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
];

<?php
declare(strict_types=1);

use App\Container;
use App\Database;

/**
 * Render một trang thật ra stdout với dữ liệu demo (dùng cho preview tĩnh).
 * CLI: php scripts/preview-render.php <page> <sqlite-file>
 */

if (PHP_SAPI !== 'cli') {
    exit('cli only');
}

$page = (string) ($argv[1] ?? '');
$dbFile = (string) ($argv[2] ?? '');
if ($dbFile === '' || !is_file($dbFile)) {
    fwrite(STDERR, "missing sqlite file\n");
    exit(1);
}

putenv('URLSHORTM_DB_DRIVER=sqlite');
putenv('URLSHORTM_DB_NAME=' . $dbFile);
putenv('URLSHORTM_STORE_RAW_IP=1');

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';

require dirname(__DIR__) . '/app/bootstrap.php';

$container = Container::getInstance();

$dashboardTabs = ['tong-quan', 'links', 'folder', 'baocao', 'tai-khoan', 'cai-dat', 'pixels', 'domains', 'utms', 'demographics'];

if (in_array($page, $dashboardTabs, true)) {
    $stmt = Database::default()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute(['preview@vidu.vn']);
    $userId = (int) $stmt->fetchColumn();
    if ($userId <= 0) {
        fwrite(STDERR, "no preview user\n");
        exit(1);
    }
    $_SESSION['user_id'] = $userId;
    $_GET['tab'] = $page;
    $container->dashboardController()->index();
    exit;
}

if ($page === 'home') {
    $container->homeController()->index();
    exit;
}

if ($page === 'dang-nhap') {
    $container->authController()->showLogin();
    exit;
}

if ($page === 'dang-ky') {
    $container->authController()->showRegister();
    exit;
}

if ($page === 'tro-giup') {
    $container->helpController()->index();
    exit;
}

fwrite(STDERR, "unknown page: $page\n");
exit(1);

<?php
declare(strict_types=1);

/**
 * Sinh bộ preview tĩnh (preview/*.html + preview/assets) với dữ liệu demo
 * hardcoded, chạy bằng PHP thật — mở được trên GitHub Pages / trình duyệt.
 * CLI: php scripts/generate-preview.php
 */

if (PHP_SAPI !== 'cli') {
    exit('cli only');
}

$root = dirname(__DIR__);
$php = PHP_BINARY;
$dbFile = sys_get_temp_dir() . '/usm-preview-' . getmypid() . '.sqlite';
$previewDir = $root . '/preview';

if (is_file($dbFile)) {
    unlink($dbFile);
}

putenv('URLSHORTM_DB_DRIVER=sqlite');
putenv('URLSHORTM_DB_NAME=' . $dbFile);
putenv('URLSHORTM_STORE_RAW_IP=1');

require $root . '/app/bootstrap.php';

$pdo = new PDO('sqlite:' . $dbFile, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA busy_timeout = 3000');

createSchema($pdo);
seed($pdo);
$pdo = null;

if (!is_dir($previewDir)) {
    mkdir($previewDir, 0777, true);
}
foreach (glob($previewDir . '/dashboard-*.html') ?: [] as $f) {
    unlink($f);
}
foreach (['landing.html', 'dang-nhap.html', 'dang-ky.html', 'tro-giup.html'] as $f) {
    if (is_file($previewDir . '/' . $f)) {
        unlink($previewDir . '/' . $f);
    }
}

$pages = [
    'home'         => 'landing.html',
    'dang-nhap'    => 'dang-nhap.html',
    'dang-ky'      => 'dang-ky.html',
    'tro-giup'     => 'tro-giup.html',
    'tong-quan'    => 'dashboard-tong-quan.html',
    'links'        => 'dashboard-links.html',
    'folder'       => 'dashboard-folder.html',
    'baocao'       => 'dashboard-baocao.html',
    'tai-khoan'    => 'dashboard-tai-khoan.html',
    'cai-dat'      => 'dashboard-cai-dat.html',
    'pixels'       => 'dashboard-pixels.html',
    'domains'      => 'dashboard-domains.html',
    'utms'         => 'dashboard-utms.html',
    'demographics' => 'dashboard-demographics.html',
];

foreach ($pages as $page => $file) {
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($root . '/scripts/preview-render.php')
        . ' ' . escapeshellarg($page) . ' ' . escapeshellarg($dbFile);

    $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $spec, $pipes);
    if (!is_resource($proc)) {
        fwrite(STDERR, "RENDER FAIL: $page (proc_open)\n");
        continue;
    }
    $html = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);

    if (trim($stderr) !== '') {
        fwrite(STDERR, "stderr [$page]: " . trim($stderr) . "\n");
    }
    if (trim($html) === '' || !str_contains($html, '<html')) {
        fwrite(STDERR, "RENDER FAIL: $page\n");
        continue;
    }

    $html = postProcess($html);
    file_put_contents($previewDir . '/' . $file, $html);
    echo "ok  $page -> $file (" . strlen($html) . " bytes)\n";
}

copyDir($root . '/assets', $previewDir . '/assets');
unlink($dbFile);

// Trang cổng index.html — link tới mọi trang preview.
file_put_contents($previewDir . '/index.html', buildPortal($pages));

echo "DONE: preview/ generated\n";

function buildPortal(array $pages): string
{
    $groups = [
        'Landing'    => ['landing.html' => 'Trang chủ (landing)', 'dang-nhap.html' => 'Đăng nhập', 'dang-ky.html' => 'Đăng ký', 'tro-giup.html' => 'Trợ giúp / Wiki'],
        'Dashboard'  => ['dashboard-tong-quan.html' => 'Tổng quan', 'dashboard-links.html' => 'All Link', 'dashboard-folder.html' => 'Folder'],
        'Báo cáo'    => ['dashboard-baocao.html' => 'Báo cáo (biểu đồ + bảng chi tiết + CSV)', 'dashboard-demographics.html' => 'Nhân khẩu học (Meta)'],
        'Cài đặt'    => ['dashboard-tai-khoan.html' => 'Tài khoản', 'dashboard-cai-dat.html' => 'Cài đặt tài khoản', 'dashboard-pixels.html' => 'Thiết lập Pixels', 'dashboard-domains.html' => 'Custom domain', 'dashboard-utms.html' => 'UTMs tracking'],
        'Admin (quản trị hệ thống)' => ['admin/index.html' => 'Tổng quan hệ thống', 'admin/users.html' => 'Quản lý Người dùng', 'admin/admins.html' => 'Quản lý Admin', 'admin/links.html' => 'Quản lý Link', 'admin/folders.html' => 'Quản lý Folder', 'admin/packages.html' => 'Gói dịch vụ', 'admin/orders.html' => 'Đơn hàng / Thanh toán', 'admin/vouchers.html' => 'Quản lý Voucher', 'admin/domains.html' => 'Quản lý Domain', 'admin/pixels.html' => 'Pixel & UTM', 'admin/settings.html' => 'Cài đặt Website', 'admin/logs.html' => 'Nhật ký hệ thống', 'admin/notifications.html' => 'Thông báo & Hỗ trợ'],
    ];
    unset($pages);

    $cards = '';
    foreach ($groups as $label => $items) {
        $cards .= '<section class="pg"><h2>' . $label . '</h2><ul>';
        foreach ($items as $file => $title) {
            $cards .= '<li><a href="' . $file . '">' . $title . '</a></li>';
        }
        $cards .= '</ul></section>';
    }

    return '<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UrlShortM — Xem trước giao diện</title>
<style>
    :root { --bg:#FBF6F0; --surface:#FFFFFF; --ink:#33292B; --muted:#8A7B78; --accent:#FF6B4A; --line:rgba(51,41,43,.08); --font:"Segoe UI",system-ui,sans-serif; }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--bg); color:var(--ink); font-family:var(--font); }
    header { background:var(--surface); border-bottom:1px solid var(--line); padding:1.4rem 1.6rem; }
    header h1 { margin:0; font-size:1.25rem; }
    header p { margin:.3rem 0 0; color:var(--muted); font-size:.9rem; }
    main { max-width:960px; margin:0 auto; padding:1.6rem; }
    .pg { background:var(--surface); border:1px solid var(--line); border-radius:12px; padding:1.1rem 1.3rem; margin-bottom:1rem; }
    .pg h2 { margin:0 0 .6rem; font-size:1rem; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; }
    .pg ul { list-style:none; margin:0; padding:0; display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:.5rem; }
    .pg a { display:block; padding:.6rem .8rem; border:1px solid var(--line); border-radius:8px; color:var(--ink); text-decoration:none; font-size:.9rem; }
    .pg a:hover { border-color:var(--accent); background:#FFF1EB; }
    .note { color:var(--muted); font-size:.85rem; line-height:1.6; background:var(--surface); border:1px dashed var(--line); border-radius:12px; padding:1rem 1.3rem; }
</style>
</head>
<body>
<header>
    <h1>UrlShortM — Xem trước giao diện</h1>
    <p>Bộ HTML tĩnh với dữ liệu demo. Bấm vào từng trang để xem.</p>
</header>
<main>
    ' . $cards . '
    <p class="note">Biểu đồ ở trang Báo cáo &amp; Nhân khẩu học được dựng bằng Chart.js (tự host trong <code>assets/</code>) — không cần mạng. Đây là bản demo dữ liệu cứng, các nút tác động (Lưu, Xoá, Tải CSV...) ở bản preview tĩnh không chạy.</p>
</main>
</body>
</html>
';
}

function postProcess(string $html): string
{
    $base = 'http://localhost';

    $html = str_replace($base . '/assets/', 'assets/', $html);

    // Map các trang có file preview tương ứng.
    $pageLinks = [
        '/'               => 'landing.html',
        '/dang-nhap'      => 'dang-nhap.html',
        '/dang-ky'        => 'dang-ky.html',
        '/tro-giup'       => 'tro-giup.html',
    ];
    foreach ($pageLinks as $path => $file) {
        $html = preg_replace(
            '#(href|action)="' . preg_quote($base . $path, '#') . '"#',
            '$1="' . $file . '"',
            $html
        ) ?? $html;
    }

    $tabs = ['tong-quan', 'links', 'folder', 'baocao', 'tai-khoan', 'cai-dat', 'pixels', 'domains', 'utms', 'demographics'];
    foreach ($tabs as $t) {
        $html = preg_replace(
            '#href="' . preg_quote($base . '/dashboard?tab=' . $t, '#') . '"#',
            'href="dashboard-' . $t . '.html"',
            $html
        ) ?? $html;
    }

    // Các link tuyệt đối còn lại (action, link action...) -> vô hiệu (#).
    $html = preg_replace('#(href|action)="' . preg_quote($base, '#') . '/[^"]*"#', '$1="#"', $html) ?? $html;

    // Dọn nốt các tham chiếu base còn sót (title, src...) để preview không trỏ về localhost.
    $html = str_replace($base . '/', '#', $html);

    return $html;
}

function copyDir(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0777, true);
    }
    foreach (scandir($src) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $from = $src . '/' . $entry;
        $to = $dst . '/' . $entry;
        if (is_dir($from)) {
            copyDir($from, $to);
        } else {
            copy($from, $to);
        }
    }
}

function createSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE short_links (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        slug          TEXT NOT NULL UNIQUE,
        target_url    TEXT NOT NULL,
        click_count   INTEGER NOT NULL DEFAULT 0,
        user_id       INTEGER NULL,
        folder_id     INTEGER NULL,
        link_type     TEXT NOT NULL DEFAULT \'link\',
        title         TEXT NULL,
        description   TEXT NULL,
        thumbnail     TEXT NULL,
        pixels        TEXT NULL,
        utm_campaign  TEXT NULL,
        utm_medium    TEXT NULL,
        utm_source    TEXT NULL,
        utm_term      TEXT NULL,
        utm_content   TEXT NULL,
        domain        TEXT NULL,
        password_hash TEXT NULL,
        starts_at     TEXT NULL,
        ends_at       TEXT NULL,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at    TEXT NULL
    )');

    $pdo->exec('CREATE TABLE users (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        email            TEXT NOT NULL UNIQUE,
        password_hash    TEXT NOT NULL,
        display_name     TEXT NULL,
        status           TEXT NOT NULL DEFAULT \'active\',
        email_verified_at TEXT NULL,
        last_login_at    TEXT NULL,
        phone            TEXT NULL,
        address          TEXT NULL,
        city             TEXT NULL,
        tax_type         TEXT NULL,
        company_name     TEXT NULL,
        tax_id           TEXT NULL,
        invoice_name     TEXT NULL,
        created_at       TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at       TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE folders (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        name       TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE pixels (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NULL,
        code       TEXT NOT NULL,
        name       TEXT NULL,
        platform   TEXT NULL,
        is_active  INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        UNIQUE (user_id, code)
    )');

    $pdo->exec('CREATE TABLE domains (
        id                 INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id            INTEGER NOT NULL,
        domain             TEXT NOT NULL,
        is_verified        INTEGER NOT NULL DEFAULT 0,
        is_active          INTEGER NOT NULL DEFAULT 1,
        verification_token TEXT NULL,
        verified_at        TEXT NULL,
        dns_checked_at     TEXT NULL,
        last_error         TEXT NULL,
        created_at         TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE utm_profiles (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id      INTEGER NOT NULL,
        name         TEXT NOT NULL,
        utm_campaign TEXT NULL,
        utm_medium   TEXT NULL,
        utm_source   TEXT NULL,
        utm_term     TEXT NULL,
        utm_content  TEXT NULL,
        created_at   TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE click_events (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        link_id    INTEGER NOT NULL,
        user_id    INTEGER NULL,
        opened_at  TEXT NOT NULL,
        ip_hash    TEXT NOT NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        referrer   TEXT NULL,
        country    TEXT NULL,
        device     TEXT NULL,
        browser    TEXT NULL,
        os         TEXT NULL
    )');

    $pdo->exec('CREATE TABLE user_settings (
        user_id    INTEGER NOT NULL,
        skey       TEXT NOT NULL,
        svalue     TEXT NULL,
        updated_at TEXT NULL,
        PRIMARY KEY (user_id, skey)
    )');

    $pdo->exec('CREATE TABLE demographic_snapshots (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        payload    TEXT NULL,
        fetched_at TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');
}

function seed(PDO $pdo): void
{
    $pdo->prepare('INSERT INTO users (email, password_hash, display_name, email_verified_at, last_login_at, phone, address, city, tax_type, company_name, tax_id, invoice_name)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            'preview@vidu.vn',
            password_hash('demo123', PASSWORD_DEFAULT),
            'Nguyễn Văn Demo',
            '2026-08-01 09:00:00',
            '2026-08-27 08:30:00',
            '0901234567',
            '12 Lê Lợi, Phường Bến Nghé',
            'Hồ Chí Minh',
            'business',
            'Công ty TNHH Demo Marketing',
            '0312345678',
            'Công ty TNHH Demo Marketing',
        ]);
    $uid = (int) $pdo->lastInsertId();

    $folders = ['Facebook', 'TikTok', 'Google Ads', 'Email'];
    $folderIds = [];
    foreach ($folders as $name) {
        $pdo->prepare('INSERT INTO folders (user_id, name) VALUES (?, ?)')->execute([$uid, $name]);
        $folderIds[$name] = (int) $pdo->lastInsertId();
    }

    $pixelCodes = [
        ['fb', 'FB Pixels Bán hàng', 'facebook'],
        ['tt', 'TikTok Pixel', 'tiktok'],
        ['gg', 'Google Ads Pixel', 'google'],
    ];
    $pixelIds = [];
    foreach ($pixelCodes as $i => $pix) {
        $pdo->prepare('INSERT INTO pixels (user_id, code, name, platform, is_active, sort_order) VALUES (?, ?, ?, ?, 1, ?)')
            ->execute([$uid, $pix[0], $pix[1], $pix[2], $i]);
        $pixelIds[] = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('INSERT INTO domains (user_id, domain, is_verified, is_active, verified_at) VALUES (?, ?, 1, 1, ?)')
        ->execute([$uid, 'link.mark.test', '2026-08-10 10:00:00']);

    $pdo->prepare('INSERT INTO utm_profiles (user_id, name, utm_campaign, utm_medium, utm_source) VALUES (?, ?, ?, ?, ?)')
        ->execute([$uid, 'FB Quảng cáo tháng 8', 'fb-camp-08', 'cpc', 'facebook']);
    $pdo->prepare('INSERT INTO utm_profiles (user_id, name, utm_campaign, utm_medium, utm_source) VALUES (?, ?, ?, ?, ?)')
        ->execute([$uid, 'TikTok Trending', 'tt-sale-99', 'paid', 'tiktok']);

    $links = [
        ['slug' => 'fB8xk2', 'title' => 'Bán hàng FB — Chiến dịch tháng 8', 'target' => 'https://banhang.shopee.vn/portal/sale/order', 'type' => 'link', 'folder' => 'Facebook', 'pixels' => ['fb'], 'utm' => ['fb-camp-08', 'cpc', 'facebook', 'hot', 'banner']],
        ['slug' => 'tT9zK4', 'title' => 'TikTok Sale 9.9', 'target' => 'https://shopee.vn/flash-sale-9-9', 'type' => 'link', 'folder' => 'TikTok', 'pixels' => ['tt'], 'utm' => ['tt-sale-99', 'paid', 'tiktok', '', 'video']],
        ['slug' => 'gA3mN1', 'title' => 'Google Ads — Keyword nóng', 'target' => 'https://example.com/gg-ads', 'type' => 'link', 'folder' => 'Google Ads', 'pixels' => ['gg'], 'utm' => ['gg-keyword', 'cpc', 'google', 'may-tinh', '']],
        ['slug' => 'wC2pN5', 'title' => 'Tư vấn qua WhatsApp', 'target' => 'https://wa.me/84912345678', 'type' => 'whatsapp', 'folder' => 'Facebook', 'pixels' => [], 'utm' => []],
        ['slug' => 'zR4hJd', 'title' => 'Đăng ký nhận quà 8.8', 'target' => 'https://example.com/nhan-qua-88', 'type' => 'link', 'folder' => 'Facebook', 'pixels' => ['fb', 'gg'], 'utm' => ['88-qua', 'cpc', 'facebook', '', '']],
        ['slug' => 'k6QpT2', 'title' => 'Email khuyến mãi cuối tuần', 'target' => 'marketing@vidu.vn', 'type' => 'mailto', 'folder' => 'Email', 'pixels' => [], 'utm' => []],
        ['slug' => 'mX7vB9', 'title' => 'Báo giá bí mật (có mật khẩu)', 'target' => 'https://example.com/bao-gia', 'type' => 'link', 'folder' => 'Email', 'pixels' => [], 'utm' => [], 'password' => true],
        ['slug' => 'dD2fK6', 'title' => 'Link custom domain', 'target' => 'https://example.com/custom-domain', 'type' => 'link', 'folder' => 'Google Ads', 'pixels' => ['gg'], 'utm' => ['cd-test', 'cpc', 'google', '', ''], 'domain' => 'link.mark.test'],
    ];

    $linkIds = [];
    foreach ($links as $link) {
        $pdo->prepare(
            'INSERT INTO short_links (slug, target_url, user_id, folder_id, link_type, title, pixels, utm_campaign, utm_medium, utm_source, utm_term, utm_content, domain, password_hash, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $link['slug'],
            $link['target'],
            $uid,
            $folderIds[$link['folder']],
            $link['type'],
            $link['title'],
            json_encode($link['pixels'], JSON_UNESCAPED_UNICODE),
            $link['utm'][0] ?? null,
            $link['utm'][1] ?? null,
            $link['utm'][2] ?? null,
            $link['utm'][3] ?? null,
            $link['utm'][4] ?? null,
            $link['domain'] ?? null,
            !empty($link['password']) ? password_hash('secret', PASSWORD_DEFAULT) : null,
            date('Y-m-d H:i:s', strtotime('-20 days')),
        ]);
        $linkIds[$link['slug']] = (int) $pdo->lastInsertId();
    }

    seedClicks($pdo, $uid, $linkIds);

    // Cấu hình Meta + snapshot nhân khẩu học
    $pdo->prepare('INSERT INTO user_settings (user_id, skey, svalue) VALUES (?, ?, ?)')->execute([$uid, 'meta_ad_account', 'act_1234567890']);
    $pdo->prepare('INSERT INTO user_settings (user_id, skey, svalue) VALUES (?, ?, ?)')->execute([$uid, 'meta_token', 'EAAXxDemoPreviewTokenEnds9876']);

    $payload = json_encode([
        'age' => [
            ['label' => '18-24', 'count' => 342],
            ['label' => '25-34', 'count' => 528],
            ['label' => '35-44', 'count' => 291],
            ['label' => '45-54', 'count' => 124],
            ['label' => '55+', 'count' => 43],
        ],
        'gender' => [
            ['label' => 'female', 'count' => 648],
            ['label' => 'male', 'count' => 319],
            ['label' => 'unknown', 'count' => 51],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $pdo->prepare('INSERT INTO demographic_snapshots (user_id, payload, fetched_at) VALUES (?, ?, ?)')
        ->execute([$uid, $payload, date('Y-m-d H:i:s', strtotime('-2 hours'))]);
}

function seedClicks(PDO $pdo, int $uid, array $linkIds): void
{
    mt_srand(20260827);

    $ua = [
        'desktop-Chrome-Windows'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        'desktop-Chrome-macOS'     => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        'desktop-Firefox-Windows'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0',
        'desktop-Edge-Windows'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0',
        'mobile-Safari-iOS'        => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        'mobile-Chrome-Android'    => 'Mozilla/5.0 (Linux; Android 13; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
        'mobile-Samsung-Android'   => 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/24.0 Chrome/117.0.0.0 Mobile Safari/537.36',
        'tablet-Safari-iOS'        => 'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    ];
    $uaKeys = array_keys($ua);

    // device/browser/os theo key
    $split = [];
    foreach ($ua as $key => $_ua) {
        [$dev, $br, $os] = explode('-', $key, 3);
        $split[$key] = ['device' => $dev === 'mobile' ? 'mobile' : ($dev === 'tablet' ? 'tablet' : 'desktop'), 'browser' => $br, 'os' => $os];
    }

    $countries = ['VN', 'VN', 'VN', 'VN', 'VN', 'VN', 'VN', 'US', 'US', 'CN', 'CN', 'SG', 'JP', 'KR', 'UA', 'TH', 'ID', null];
    $referrers = [
        'https://www.facebook.com/', 'https://www.facebook.com/', 'https://www.facebook.com/',
        'https://www.google.com/', 'https://www.google.com/',
        'https://www.tiktok.com/', 'https://www.instagram.com/', 'https://m.youtube.com/',
        null, null,
    ];

    $total = 148;
    $now = time();
    $linkSlugs = array_keys($linkIds);
    $counts = [];

    $ins = $pdo->prepare(
        'INSERT INTO click_events (link_id, user_id, opened_at, ip_hash, ip_address, user_agent, referrer, country, device, browser, os)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    for ($i = 0; $i < $total; $i++) {
        $slug = $linkSlugs[$i % count($linkSlugs)];
        $linkId = $linkIds[$slug];
        $counts[$slug] = ($counts[$slug] ?? 0) + 1;

        $uaKey = $uaKeys[mt_rand(0, count($uaKeys) - 1)];
        $country = $countries[mt_rand(0, count($countries) - 1)];
        $referrer = $referrers[mt_rand(0, count($referrers) - 1)];

        $ip = fakeIp($country);
        $hoursAgo = mt_rand(0, 13 * 24);
        $ts = $now - $hoursAgo * 3600 - mt_rand(0, 3599);

        $ins->execute([
            $linkId,
            $uid,
            date('Y-m-d H:i:s', $ts),
            \App\hash_ip($ip),
            $ip,
            $ua[$uaKey],
            $referrer,
            $country,
            $split[$uaKey]['device'],
            $split[$uaKey]['browser'],
            $split[$uaKey]['os'],
        ]);
    }

    // Đồng bộ click_count cho link list
    foreach ($counts as $slug => $c) {
        $pdo->prepare('UPDATE short_links SET click_count = click_count + ? WHERE slug = ?')->execute([$c, $slug]);
    }
}

function fakeIp(?string $country): string
{
    switch ($country) {
        case 'VN':
            return '113.' . mt_rand(160, 191) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
        case 'US':
            return '157.240.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
        case 'CN':
            return '27.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
        case 'SG':
            return '103.55.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
        case 'JP':
            return '126.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
        case 'KR':
            return '121.134.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
        case 'UA':
            return '46.16.' . mt_rand(0, 63) . '.' . mt_rand(1, 254);
        case 'TH':
            return '203.113.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
        case 'ID':
            return '103.106.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
        default:
            return '10.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
    }
}

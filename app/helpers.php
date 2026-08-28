<?php
declare(strict_types=1);

namespace App;

final class Config
{
    private static array $config = [];

    public static function load(array $config): void
    {
        self::$config = $config;
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

function config(string $path, mixed $default = null): mixed
{
    return Config::get($path, $default);
}

function escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(): string
{
    // Tự nhận diện domain/host đang chạy cho toàn hệ thống (không cần cấu hình).
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));

    return rtrim($scheme . '://' . $host . $base, '/');
}

function url_for(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $location, int $status = 302): never
{
    http_response_code($status);
    header('Location: ' . $location);
    exit;
}

function render(string $template, array $data = []): string
{
    $appDir = __DIR__;
    $file   = $appDir . '/View/' . $template . '.php';

    if (!is_file($file)) {
        throw new \RuntimeException("View not found: {$template}");
    }

    extract($data, EXTR_SKIP);

    ob_start();
    include $file;

    return (string) ob_get_clean();
}

/**
 * @return array{id:int,email:string,display_name:?string,status:string}|null user đang đăng nhập
 */
function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    try {
        return Container::getInstance()->userRepository()->findById((int) $id);
    } catch (\Throwable) {
        return null;
    }
}

/**
 * @return array{id:int,email:string,display_name:?string,role:string,status:string}|null admin đang đăng nhập
 */
function current_admin(): ?array
{
    $id = $_SESSION['admin_id'] ?? null;
    if (!$id) {
        return null;
    }

    try {
        return Container::getInstance()->adminRepository()->findById((int) $id);
    } catch (\Throwable) {
        return null;
    }
}

/**
 * Shell trang quản trị (sidebar + top bar) — render toàn trang và exit.
 * `$content` là HTML phần nội dung.
 *
 * @param array{id:int,email:string,display_name:?string,role:string} $admin
 */
function render_admin_page(array $admin, string $title, string $activeKey, string $content): never
{
    $nav = [
        ['key' => 'tong-quan', 'label' => 'Tổng quan', 'href' => url_for('admin'), 'subs' => []],
        ['key' => 'users', 'label' => 'Quản lý người dùng', 'href' => url_for('admin/users'), 'subs' => []],
        ['key' => 'admins', 'label' => 'Quản lý Admin', 'href' => '#', 'subs' => ['Danh sách Admin', 'Phân quyền', 'Super Admin']],
        ['key' => 'links', 'label' => 'Quản lý Link', 'href' => url_for('admin/links'), 'subs' => []],
        ['key' => 'folders', 'label' => 'Quản lý Folder', 'href' => '#', 'subs' => ['Danh sách Folder', 'Tạo / Sửa / Xoá']],
        ['key' => 'packages', 'label' => 'Gói dịch vụ', 'href' => url_for('admin/packages'), 'subs' => []],
        ['key' => 'orders', 'label' => 'Đơn hàng / Thanh toán', 'href' => '#', 'subs' => [
            ['Danh sách đơn hàng', url_for('admin/orders'), 'orders'],
            ['Cổng thanh toán', url_for('admin/payments'), 'payments'],
        ]],
        ['key' => 'vouchers', 'label' => 'Quản lý Voucher', 'href' => url_for('admin/vouchers'), 'subs' => []],
        ['key' => 'domains', 'label' => 'Quản lý Domain', 'href' => url_for('admin/domains'), 'subs' => []],
        ['key' => 'pixels', 'label' => 'Pixel & UTM', 'href' => '#', 'subs' => ['Pixel của User', 'UTM Preset']],
        ['key' => 'settings', 'label' => 'Cài đặt Website', 'href' => '#', 'subs' => [
            ['Thông tin hệ thống', url_for('admin/settings'), 'settings'],
            ['Thông tin website', url_for('admin/settings/website'), 'website'],
            ['Hoá đơn', url_for('admin/settings/invoice'), 'invoice'],
            ['Email (SMTP)', url_for('admin/settings/smtp'), 'smtp'],
            ['Media', url_for('admin/settings/media'), 'media'],
            ['SEO', url_for('admin/settings/seo'), 'seo'],
            ['Email Template', url_for('admin/emails'), 'emails'],
        ]],
        ['key' => 'logs', 'label' => 'Nhật ký hệ thống', 'href' => '#', 'subs' => ['Activity Log', 'Lịch sử đăng nhập', 'Lỗi hệ thống']],
        ['key' => 'notifications', 'label' => 'Thông báo & Hỗ trợ', 'href' => '#', 'subs' => ['Gửi thông báo', 'Ticket hỗ trợ']],
    ];

    $navHtml = '';
    foreach ($nav as $i => $item) {
        $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $isActive = $item['key'] === $activeKey;
        $subs = '';
        foreach ($item['subs'] as $sub) {
            if (is_array($sub)) {
                [$subLabel, $subHref, $subKey] = [$sub[0], $sub[1], $sub[2] ?? null];
                $subActive = $subKey !== null && $subKey === $activeKey;
                $subs .= '<a class="a-nav-sub' . ($subActive ? ' is-active' : '') . '" href="' . $subHref . '">' . escape($subLabel) . '</a>';
            } else {
                $subs .= '<a class="a-nav-sub" href="#">' . escape((string) $sub) . '</a>';
            }
        }
        $navHtml .= '<div class="a-nav-group"><a class="a-nav-item' . ($isActive ? ' is-active' : '') . '" href="' . $item['href'] . '"><span class="a-nav-num">' . $num . '</span>' . escape($item['label']) . '</a>' . ($subs !== '' ? '<div class="a-nav-subwrap">' . $subs . '</div>' : '') . '</div>';
    }

    $crumb = 'Quản trị hệ thống';

    echo '<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . escape($title) . '</title>
<link rel="stylesheet" href="' . url_for('assets/css/style.css') . '">
<link rel="stylesheet" href="' . url_for('assets/css/admin.css') . '">
</head>
<body>
<div class="adash">

    <aside class="adash-side">
        <div class="a-side-head">
            <span class="a-logo-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span>
            <div class="a-logo-text">UrlShortM<small>Admin Panel</small></div>
        </div>
        <div class="a-admin">
            <span class="a-admin-avatar" aria-hidden="true">' . escape(mb_substr($admin['display_name'] ?: 'A', 0, 1, 'UTF-8')) . '</span>
            <div class="a-admin-meta">
                <strong>' . escape($admin['display_name'] ?: $admin['email']) . '</strong>
                <span class="a-admin-role">' . escape($admin['email']) . '</span>
                <span class="a-admin-status"><span class="a-admin-dot" aria-hidden="true"></span> Đang trực</span>
            </div>
            <span class="a-badge-super">' . ($admin['role'] === 'super_admin' ? 'Super' : 'Admin') . '</span>
        </div>
        <nav class="a-nav" aria-label="Menu quản trị">' . $navHtml . '</nav>
        <div class="a-side-foot">
            <a class="a-side-link" href="' . url_for('') . '">&#8592; Về trang chủ</a>
            <form class="dash-logout" method="post" action="' . url_for('admin/dang-xuat') . '">' . csrf_field() . '<button type="submit" class="a-side-link">Thoát đăng nhập</button></form>
        </div>
    </aside>

    <div class="adash-main">
        <header class="a-top">
            <div>
                <p class="a-crumb">// ' . $crumb . '</p>
                <h1 class="a-title">' . escape($title) . '</h1>
            </div>
            <div class="a-tape" aria-hidden="true"><span>1.284 user</span><span>///</span><span>4.910 link</span><span>///</span><span class="a-tape-live"><span class="a-pulse" aria-hidden="true"></span> hệ thống đang chạy</span></div>
        </header>
        <div class="a-content">' . $content . '</div>
    </div>
</div>
<script src="' . url_for('assets/js/vendor/chart.umd.min.js') . '"></script>
<script src="' . url_for('assets/js/admin.js') . '"></script>
</body>
</html>';
    exit;
}

function csrf_field(): string
{
    return (new \App\Security\Csrf())->field();
}

/**
 * HTML head bổ sung từ Cài đặt Website (SEO, OG, verification, tracking, custom code).
 */
function site_seo_head(): string
{
    $s = Container::getInstance()->siteSettingsService();
    $base = rtrim(base_url(), '/');
    $out = '';

    $desc = (string) $s->get('seo_meta_description', $s->siteIntro());
    if ($desc !== '') {
        $out .= '<meta name="description" content="' . escape($desc) . '">' . "\n";
    }
    $kw = (string) $s->get('seo_meta_keywords', '');
    if ($kw !== '') {
        $out .= '<meta name="keywords" content="' . escape($kw) . '">' . "\n";
    }
    $robots = (string) $s->get('seo_robots_meta', '');
    if ($robots !== '') {
        $out .= '<meta name="robots" content="' . escape($robots) . '">' . "\n";
    }
    $canonical = (string) $s->get('seo_canonical_url', '');
    $canonical = $canonical !== '' ? $canonical : $base;
    $out .= '<link rel="canonical" href="' . escape($canonical) . '">' . "\n";

    $hreflang = (string) $s->get('seo_hreflang', '');
    if ($hreflang !== '') {
        $out .= '<link rel="alternate" hreflang="' . escape($hreflang) . '" href="' . escape($canonical) . '">' . "\n";
    }

    $ogImage = (string) $s->get('seo_og_image', '');
    $og = [
        'og:title'       => (string) $s->get('seo_og_title', '') !== '' ? (string) $s->get('seo_og_title') : $s->siteName(),
        'og:description' => (string) $s->get('seo_og_description', '') !== '' ? (string) $s->get('seo_og_description') : $desc,
        'og:type'        => (string) $s->get('seo_og_type', 'website'),
        'og:url'         => $base,
        'og:site_name'   => $s->siteName(),
    ];
    if ($ogImage !== '') {
        $og['og:image'] = $ogImage;
    }
    foreach ($og as $prop => $content) {
        if ($content !== '') {
            $out .= '<meta property="' . escape($prop) . '" content="' . escape($content) . '">' . "\n";
        }
    }

    foreach ([
        'google-site-verification' => 'seo_gsc',
        'msvalidate.01'            => 'seo_bing',
        'yandex-verification'      => 'seo_yandex',
        'baidu-site-verification'  => 'seo_baidu',
    ] as $name => $key) {
        $val = (string) $s->get($key, '');
        if ($val !== '') {
            $out .= '<meta name="' . escape($name) . '" content="' . escape($val) . '">' . "\n";
        }
    }

    $ga4 = (string) $s->get('seo_ga4', '');
    if ($ga4 !== '') {
        $out .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . escape($ga4) . '"></script>' . "\n"
            . '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . escape($ga4) . '");</script>' . "\n";
    }
    $gtm = (string) $s->get('seo_gtm', '');
    if ($gtm !== '') {
        $out .= '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer","' . escape($gtm) . '");</script>' . "\n";
    }
    $metaPixel = (string) $s->get('seo_meta_pixel', '');
    if ($metaPixel !== '') {
        $out .= '<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");fbq("init","' . escape($metaPixel) . '");fbq("track","PageView");</script>' . "\n";
    }
    $ttPixel = (string) $s->get('seo_tiktok_pixel', '');
    if ($ttPixel !== '') {
        $out .= '<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e,n){var o="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._init=ttq._init||[];ttq._init.push([e,n]);var s=d.getElementsByTagName("script")[0];s.parentNode.insertBefore(w.createElement("script"),s)}(w,d,"ttq");ttq.page();ttq.load("' . escape($ttPixel) . '");}(window,document,"ttq");</script>' . "\n";
    }
    $indexnow = (string) $s->get('seo_indexnow_key', '');
    if ($indexnow !== '') {
        $out .= '<meta name="indexnow-key" content="' . escape($indexnow) . '">' . "\n";
    }
    $sitemap = (string) $s->get('seo_sitemap_url', '');
    if ($sitemap === '') {
        $sitemap = $base . '/sitemap.xml';
    }
    $out .= '<link rel="sitemap" type="application/xml" href="' . escape($sitemap) . '">' . "\n";
    if ((string) $s->get('seo_ai_meta', '1') !== '1') {
        $out .= '<meta name="robots" content="noai,noimageai">' . "\n";
    }
    $head = (string) $s->get('seo_head_code', '');
    if ($head !== '') {
        $out .= $head . "\n";
    }

    return $out;
}

function site_seo_body(): string
{
    $s = Container::getInstance()->siteSettingsService();
    $out = '';
    $gtm = (string) $s->get('seo_gtm', '');
    if ($gtm !== '') {
        $out .= '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . escape($gtm) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
    }
    $out .= (string) $s->get('seo_body_code', '') . "\n";

    return $out;
}

function site_seo_lang(): string
{
    $lang = (string) Container::getInstance()->siteSettingsService()->get('seo_hreflang', '');

    return $lang !== '' ? $lang : 'vi';
}

function site_seo_footer(): string
{
    return (string) Container::getInstance()->siteSettingsService()->get('seo_footer_code', '') . "\n";
}

/**
 * Nội dung robots.txt hiện tại: dùng cấu hình tuỳ chỉnh (seo_robots_txt) nếu có,
 * ngược lại tạo mặc định. Luôn đảm bảo dòng Sitemap.
 */
function robots_txt_content(): string
{
    $base = rtrim(base_url(), '/');
    $custom = (string) Container::getInstance()->siteSettingsService()->get('seo_robots_txt', '');

    if ($custom !== '') {
        if (stripos($custom, 'Sitemap:') === false) {
            $custom .= "\n\nSitemap: {$base}/sitemap.xml\n";
        }

        return $custom;
    }

    return "User-agent: *\n"
        . "Allow: /\n"
        . "Disallow: /admin\n"
        . "Disallow: /dashboard\n"
        . "Disallow: /dang-nhap\n"
        . "Disallow: /dang-ky\n"
        . "Disallow: /thanh-toan\n"
        . "Disallow: /hoa-don\n"
        . "Disallow: /app/\n"
        . "Disallow: /database/\n"
        . "Disallow: /tests/\n"
        . "\nSitemap: {$base}/sitemap.xml\n";
}/**
 * URL ngắn cho link (dùng domain tuỳ chỉnh nếu có, ngược lại base local).
 *
 * @param array<string,mixed> $link
 */
function short_url_for(array $link): string
{
    $domain = $link['domain'] ?? null;

    if ($domain === null || $domain === '') {
        return rtrim(system_short_base(), '/') . '/' . $link['slug'];
    }

    $d = strtolower($domain);
    if (in_array($d, ['localhost', '127.0.0.1'], true)) {
        $base = base_url();
    } elseif (preg_match('/\.(test|localhost)$/', $d) === 1) {
        // Domain local (.test/.localhost) dùng HTTP khi chạy Laragon.
        $base = 'http://' . $domain;
    } else {
        $base = 'https://' . $domain;
    }

    return rtrim($base, '/') . '/' . $link['slug'];
}

/**
 * Base URL cho link rút gọn: domain hệ thống mặc định (đang chạy) nếu có,
 * ngược lại dùng host hiện tại. .test/.localhost -> HTTP (Laragon); còn lại HTTPS.
 */
function system_short_base(): string
{
    try {
        $row = Container::getInstance()->domainRepository()->systemDefault();
        if ($row !== null) {
            $d = strtolower((string) $row['domain']);
            if (in_array($d, ['localhost', '127.0.0.1'], true) || preg_match('/\.(test|localhost)$/', $d) === 1) {
                return 'http://' . $row['domain'];
            }

            return 'https://' . $row['domain'];
        }
    } catch (\Throwable) {
        // bỏ qua -> dùng host hiện tại
    }

    return base_url();
}

/**
 * Gắn tham số UTM vào URL đích (chỉ cho URL web http/https).
 *
 * @param array<string,mixed> $utm
 */
function append_utm(string $target, array $utm): string
{
    $utm = array_filter($utm, static fn ($v) => $v !== null && trim((string) $v) !== '');

    if ($utm === [] || preg_match('#^https?://#i', $target) !== 1) {
        return $target;
    }

    $separator = str_contains($target, '?') ? '&' : '?';

    return $target . $separator . http_build_query($utm);
}

/**
 * Băm IP (sha256 + salt) để không lưu PII thô.
 */
function hash_ip(string $ip): string
{
    $salt = (string) \App\Config::get('app.tracking.ip_salt', 'urlshortm-track-v1');

    return hash('sha256', $salt . '|' . $ip);
}

/**
 * Nhãn tiếng Việt cho mã quốc gia ISO 2 ký tự.
 */
function country_label(?string $code): string
{
    $map = [
        'VN' => 'Việt Nam', 'US' => 'Mỹ', 'CN' => 'Trung Quốc', 'UA' => 'Ukraine',
        'RU' => 'Nga', 'JP' => 'Nhật Bản', 'KR' => 'Hàn Quốc', 'SG' => 'Singapore',
        'ID' => 'Indonesia', 'TH' => 'Thái Lan', 'MY' => 'Malaysia', 'PH' => 'Philippines',
        'IN' => 'Ấn Độ', 'DE' => 'Đức', 'FR' => 'Pháp', 'GB' => 'Anh',
        'HK' => 'Hồng Kông', 'TW' => 'Đài Loan', 'AU' => 'Úc', 'CA' => 'Canada',
    ];

    if ($code !== null && isset($map[$code])) {
        return $map[$code];
    }

    return ($code !== null && $code !== '') ? $code : '—';
}

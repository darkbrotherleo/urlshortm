<?php
declare(strict_types=1);

namespace App;

final class Router
{
    /** Slug: 3-16 kÃ½ tá»± [0-9a-zA-Z-_] (cho phÃ©p custom back-half). */
    public const SLUG_PATTERN = '/^[0-9a-zA-Z\-_]{3,16}$/';

    /** ÄÆ°á»ng dáº«n dÃ nh riÃªng, khÃ´ng pháº£i slug. */
    public const RESERVED = [
        'shorten', 'assets', 'index', 'app', 'tests', 'database',
        'config', 'scripts', 'robots', 'favicon', 'stats',
        'dang-ky', 'dang-nhap', 'dang-xuat', 'dashboard',
        'link', 'folder', 'new', 'edit', 'bulk', 'settings', 'unlock', 'tro-giup',
        'admin', 'bang-gia', 'thanh-toan', 'hoa-don', 'tinh-nang', 'sitemap',
        'kich-hoat', 'quen-mat-khau', 'dat-lai-mat-khau',
    ];

    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';

    /**
     * @return array{handler:string, params:array<string,mixed>}
     */
    public function match(string $method, string $path): array
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        if ($method === self::METHOD_GET && $path === '/') {
            return ['handler' => 'home', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/shorten') {
            return ['handler' => 'shorten', 'params' => []];
        }

        if ($method === self::METHOD_GET && preg_match('#^/stats/([0-9a-zA-Z\-_]+)$#', $path, $m) === 1) {
            return ['handler' => 'stats', 'params' => ['slug' => $m[1]]];
        }

        if ($path === '/dang-ky' && ($method === self::METHOD_GET || $method === self::METHOD_POST)) {
            return ['handler' => 'register', 'params' => []];
        }

        if ($path === '/dang-nhap' && ($method === self::METHOD_GET || $method === self::METHOD_POST)) {
            return ['handler' => 'login', 'params' => []];
        }

        if ($path === '/dang-xuat' && $method === self::METHOD_POST) {
            return ['handler' => 'logout', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/kich-hoat') {
            return ['handler' => 'activate', 'params' => []];
        }

        if ($path === '/quen-mat-khau' && ($method === self::METHOD_GET || $method === self::METHOD_POST)) {
            return ['handler' => 'forgot_password', 'params' => []];
        }

        if ($path === '/dat-lai-mat-khau' && ($method === self::METHOD_GET || $method === self::METHOD_POST)) {
            return ['handler' => 'reset_password', 'params' => []];
        }

        if ($path === '/admin/dang-nhap' && ($method === self::METHOD_GET || $method === self::METHOD_POST)) {
            return ['handler' => 'admin_login', 'params' => []];
        }

        if ($path === '/admin/dang-xuat' && $method === self::METHOD_POST) {
            return ['handler' => 'admin_logout', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/admin') {
            return ['handler' => 'admin_dashboard', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/admin/users') {
            return ['handler' => 'admin_users', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/users/update') {
            return ['handler' => 'admin_users_update', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/admin/packages') {
            return ['handler' => 'admin_packages', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/admin/packages/new') {
            return ['handler' => 'admin_packages_new', 'params' => []];
        }

        if ($method === self::METHOD_GET && preg_match('#^/admin/packages/(\d+)/edit$#', $path, $m) === 1) {
            return ['handler' => 'admin_packages_edit', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && $path === '/admin/packages/store') {
            return ['handler' => 'admin_packages_store', 'params' => []];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/packages/(\d+)/update$#', $path, $m) === 1) {
            return ['handler' => 'admin_packages_update', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/packages/(\d+)/delete$#', $path, $m) === 1) {
            return ['handler' => 'admin_packages_delete', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/packages/(\d+)/toggle$#', $path, $m) === 1) {
            return ['handler' => 'admin_packages_toggle', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_GET && $path === '/bang-gia') {
            return ['handler' => 'pricing', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/sitemap.xml') {
            return ['handler' => 'sitemap', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/robots.txt') {
            return ['handler' => 'robots_txt', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/tinh-nang') {
            return ['handler' => 'features', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/thanh-toan') {
            return ['handler' => 'checkout', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/thanh-toan/pay') {
            return ['handler' => 'checkout_pay', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/thanh-toan/thanh-cong') {
            return ['handler' => 'checkout_success', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/thanh-toan/huy') {
            return ['handler' => 'checkout_cancel', 'params' => []];
        }

        if ($method === self::METHOD_GET && preg_match('#^/hoa-don/([0-9A-Za-z\-]+)$#', $path, $m) === 1) {
            return ['handler' => 'checkout_invoice', 'params' => ['code' => $m[1]]];
        }

        if ($method === self::METHOD_GET && $path === '/admin/payments') {
            return ['handler' => 'admin_payments', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/payments/save') {
            return ['handler' => 'admin_payments_save', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/admin/orders') {
            return ['handler' => 'admin_orders', 'params' => []];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/orders/(\d+)/status$#', $path, $m) === 1) {
            return ['handler' => 'admin_orders_status', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_GET && $path === '/admin/links') {
            return ['handler' => 'admin_links', 'params' => []];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/links/(\d+)/toggle$#', $path, $m) === 1) {
            return ['handler' => 'admin_links_toggle', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/links/(\d+)/update$#', $path, $m) === 1) {
            return ['handler' => 'admin_links_update', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_GET && $path === '/admin/vouchers') {
            return ['handler' => 'admin_vouchers', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/vouchers/store') {
            return ['handler' => 'admin_vouchers_store', 'params' => []];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/vouchers/(\d+)/update$#', $path, $m) === 1) {
            return ['handler' => 'admin_vouchers_update', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/vouchers/(\d+)/toggle$#', $path, $m) === 1) {
            return ['handler' => 'admin_vouchers_toggle', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_GET && $path === '/admin/domains') {
            return ['handler' => 'admin_domains', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/domains/system/add') {
            return ['handler' => 'admin_domains_system_add', 'params' => []];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/domains/system/(\d+)/default$#', $path, $m) === 1) {
            return ['handler' => 'admin_domains_system_default', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/domains/system/(\d+)/toggle$#', $path, $m) === 1) {
            return ['handler' => 'admin_domains_system_toggle', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/domains/system/(\d+)/delete$#', $path, $m) === 1) {
            return ['handler' => 'admin_domains_system_delete', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/domains/user/(\d+)/toggle$#', $path, $m) === 1) {
            return ['handler' => 'admin_domains_user_toggle', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/domains/user/(\d+)/delete$#', $path, $m) === 1) {
            return ['handler' => 'admin_domains_user_delete', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_GET && $path === '/admin/settings') {
            return ['handler' => 'admin_settings', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/admin/settings/website') {
            return ['handler' => 'admin_settings_website', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/settings/website/save') {
            return ['handler' => 'admin_settings_website_save', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/admin/settings/invoice') {
            return ['handler' => 'admin_settings_invoice', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/settings/invoice/save') {
            return ['handler' => 'admin_settings_invoice_save', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/admin/settings/smtp') {
            return ['handler' => 'admin_settings_smtp', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/settings/smtp/save') {
            return ['handler' => 'admin_settings_smtp_save', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/settings/smtp/test') {
            return ['handler' => 'admin_settings_smtp_test', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/admin/settings/media') {
            return ['handler' => 'admin_settings_media', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/settings/media/save') {
            return ['handler' => 'admin_settings_media_save', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/settings/media/upload') {
            return ['handler' => 'admin_settings_media_upload', 'params' => []];
        }

        if ($method === self::METHOD_POST && preg_match('#^/admin/settings/media/(\d+)/delete$#', $path, $m) === 1) {
            return ['handler' => 'admin_settings_media_delete', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_GET && $path === '/admin/settings/seo') {
            return ['handler' => 'admin_settings_seo', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/settings/seo/save') {
            return ['handler' => 'admin_settings_seo_save', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/admin/emails') {
            return ['handler' => 'admin_emails', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/admin/emails/send') {
            return ['handler' => 'admin_emails_send', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/tro-giup') {
            return ['handler' => 'help_index', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/tro-giup/pixel-id') {
            return ['handler' => 'help_pixel', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/tro-giup/custom-domain') {
            return ['handler' => 'help_custom_domain', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/dashboard') {
            return ['handler' => 'dashboard', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/folder/create') {
            return ['handler' => 'folder_create', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/folder/delete') {
            return ['handler' => 'folder_delete', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/link-folder') {
            return ['handler' => 'link_folder', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/settings') {
            return ['handler' => 'settings_update', 'params' => []];
        }

        if ($method === self::METHOD_POST && preg_match('#^/([0-9a-zA-Z\-_]{3,16})/unlock$#', $path, $m) === 1) {
            return ['handler' => 'unlock', 'params' => ['slug' => $m[1]]];
        }

        if ($method === self::METHOD_GET && $path === '/dashboard/bao-cao/export') {
            return ['handler' => 'report_export', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/dashboard/link/new') {
            return ['handler' => 'link_new', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/link') {
            return ['handler' => 'link_store', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/link/bulk') {
            return ['handler' => 'link_bulk', 'params' => []];
        }

        if ($method === self::METHOD_GET && preg_match('#^/dashboard/link/(\d+)/edit$#', $path, $m) === 1) {
            return ['handler' => 'link_edit', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/dashboard/link/(\d+)/update$#', $path, $m) === 1) {
            return ['handler' => 'link_update', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/dashboard/link/(\d+)/delete$#', $path, $m) === 1) {
            return ['handler' => 'link_delete', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/pixel/create') {
            return ['handler' => 'pixel_create', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/pixel/delete') {
            return ['handler' => 'pixel_delete', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/pixel/update') {
            return ['handler' => 'pixel_update', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/domain/create') {
            return ['handler' => 'domain_create', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/domain/delete') {
            return ['handler' => 'domain_delete', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/domain/verify') {
            return ['handler' => 'domain_verify', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/utm/store') {
            return ['handler' => 'utm_store', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/utm/delete') {
            return ['handler' => 'utm_delete', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/demographics/save') {
            return ['handler' => 'demo_save', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/demographics/fetch') {
            return ['handler' => 'demo_fetch', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/demographics/clear') {
            return ['handler' => 'demo_clear', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/account/password') {
            return ['handler' => 'account_password', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/account/deactivate') {
            return ['handler' => 'account_deactivate', 'params' => []];
        }

        if ($method === self::METHOD_GET) {
            $slug = ltrim($path, '/');
            if (preg_match(self::SLUG_PATTERN, $slug) === 1 && !in_array($slug, self::RESERVED, true)) {
                return ['handler' => 'redirect', 'params' => ['slug' => $slug]];
            }
        }

        return ['handler' => 'notfound', 'params' => []];
    }
}

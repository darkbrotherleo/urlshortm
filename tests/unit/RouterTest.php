<?php
declare(strict_types=1);

use App\Router;

return function (TestSuite $suite): void {
    $suite->test('GET / -> home', function (): void {
        $r = new Router();
        $m = $r->match('GET', '/');
        assert_same('home', $m['handler']);
    });

    $suite->test('POST /shorten -> shorten', function (): void {
        $r = new Router();
        $m = $r->match('POST', '/shorten');
        assert_same('shorten', $m['handler']);
    });

    $suite->test('GET /{slug 6 ký tự} -> redirect', function (): void {
        $r = new Router();
        $m = $r->match('GET', '/Ab3x9Q');
        assert_same('redirect', $m['handler']);
        assert_same('Ab3x9Q', $m['params']['slug']);
    });

    $suite->test('GET /{slug} không rơi vào reserved', function (): void {
        $r = new Router();
        assert_same('notfound', $r->match('GET', '/assets')['handler']);
        assert_same('notfound', $r->match('GET', '/shorten')['handler']);
        assert_same('notfound', $r->match('GET', '/stats')['handler']);
    });

    $suite->test('GET /stats/{slug} -> stats', function (): void {
        $r = new Router();
        $m = $r->match('GET', '/stats/Ab3x9Q');
        assert_same('stats', $m['handler']);
        assert_same('Ab3x9Q', $m['params']['slug']);
    });

    $suite->test('GET /stats/{slug sai} vẫn vào stats (controller trả 400)', function (): void {
        $r = new Router();
        assert_same('stats', $r->match('GET', '/stats/abc')['handler']);
        assert_same('stats', $r->match('GET', '/stats/Ab3x9Qx')['handler']);
    });

    $suite->test('auth routes: dang-ky / dang-nhap / dang-xuat', function (): void {
        $r = new Router();
        assert_same('register', $r->match('GET', '/dang-ky')['handler']);
        assert_same('register', $r->match('POST', '/dang-ky')['handler']);
        assert_same('login', $r->match('GET', '/dang-nhap')['handler']);
        assert_same('login', $r->match('POST', '/dang-nhap')['handler']);
        assert_same('logout', $r->match('POST', '/dang-xuat')['handler']);
        assert_same('notfound', $r->match('GET', '/dang-xuat')['handler']);
        assert_same('redirect', $r->match('GET', '/dang-kyx')['handler']);
    });

    $suite->test('GET /dashboard -> dashboard', function (): void {
        $r = new Router();
        assert_same('dashboard', $r->match('GET', '/dashboard')['handler']);
        assert_same('notfound', $r->match('POST', '/dashboard')['handler']);
    });

    $suite->test('dashboard POST handlers', function (): void {
        $r = new Router();
        assert_same('folder_create', $r->match('POST', '/dashboard/folder/create')['handler']);
        assert_same('folder_delete', $r->match('POST', '/dashboard/folder/delete')['handler']);
        assert_same('link_folder', $r->match('POST', '/dashboard/link-folder')['handler']);
        assert_same('settings_update', $r->match('POST', '/dashboard/settings')['handler']);
        assert_same('notfound', $r->match('GET', '/dashboard/folder/create')['handler']);
    });

    $suite->test('link manager routes', function (): void {
        $r = new Router();
        assert_same('link_new', $r->match('GET', '/dashboard/link/new')['handler']);
        assert_same('link_store', $r->match('POST', '/dashboard/link')['handler']);
        assert_same('link_bulk', $r->match('POST', '/dashboard/link/bulk')['handler']);
        assert_same('link_edit', $r->match('GET', '/dashboard/link/12/edit')['handler']);
        assert_same('link_update', $r->match('POST', '/dashboard/link/12/update')['handler']);
        assert_same('link_delete', $r->match('POST', '/dashboard/link/12/delete')['handler']);
        assert_same('notfound', $r->match('POST', '/dashboard/link/12')['handler']);
    });

    $suite->test('POST /{slug}/unlock -> unlock', function (): void {
        $r = new Router();
        assert_same('unlock', $r->match('POST', '/Ab3x9Q/unlock')['handler']);
        assert_same('unlock', $r->match('POST', '/abc/unlock')['handler']);
        assert_same('notfound', $r->match('POST', '/ab/unlock')['handler']);
        assert_same('notfound', $r->match('GET', '/Ab3x9Q/unlock')['handler']);
    });

    $suite->test('GET /tro-giup -> help_index', function (): void {
        $r = new Router();
        assert_same('help_index', $r->match('GET', '/tro-giup')['handler']);
    });

    $suite->test('GET /tro-giup/pixel-id -> help_pixel', function (): void {
        $r = new Router();
        assert_same('help_pixel', $r->match('GET', '/tro-giup/pixel-id')['handler']);
        assert_same('help_index', $r->match('GET', '/tro-giup')['handler']);
    });

    $suite->test('GET /tro-giup/custom-domain -> help_custom_domain', function (): void {
        $r = new Router();
        assert_same('help_custom_domain', $r->match('GET', '/tro-giup/custom-domain')['handler']);
    });

    $suite->test('settings routes: pixels/domain/utm', function (): void {
        $r = new Router();
        assert_same('pixel_create', $r->match('POST', '/dashboard/pixel/create')['handler']);
        assert_same('pixel_delete', $r->match('POST', '/dashboard/pixel/delete')['handler']);
        assert_same('pixel_update', $r->match('POST', '/dashboard/pixel/update')['handler']);
        assert_same('domain_create', $r->match('POST', '/dashboard/domain/create')['handler']);
        assert_same('domain_delete', $r->match('POST', '/dashboard/domain/delete')['handler']);
        assert_same('domain_verify', $r->match('POST', '/dashboard/domain/verify')['handler']);
assert_same('utm_store', $r->match('POST', '/dashboard/utm/store')['handler']);
assert_same('utm_delete', $r->match('POST', '/dashboard/utm/delete')['handler']);
assert_same('demo_save', $r->match('POST', '/dashboard/demographics/save')['handler']);
assert_same('demo_fetch', $r->match('POST', '/dashboard/demographics/fetch')['handler']);
assert_same('demo_clear', $r->match('POST', '/dashboard/demographics/clear')['handler']);
assert_same('account_password', $r->match('POST', '/dashboard/account/password')['handler']);
assert_same('account_deactivate', $r->match('POST', '/dashboard/account/deactivate')['handler']);
assert_same('admin_login', $r->match('GET', '/admin/dang-nhap')['handler']);
assert_same('admin_login', $r->match('POST', '/admin/dang-nhap')['handler']);
assert_same('admin_logout', $r->match('POST', '/admin/dang-xuat')['handler']);
assert_same('admin_dashboard', $r->match('GET', '/admin')['handler']);
assert_same('admin_users', $r->match('GET', '/admin/users')['handler']);
assert_same('admin_users_update', $r->match('POST', '/admin/users/update')['handler']);
assert_same('admin_packages', $r->match('GET', '/admin/packages')['handler']);
assert_same('admin_packages_new', $r->match('GET', '/admin/packages/new')['handler']);
assert_same('admin_packages_edit', $r->match('GET', '/admin/packages/5/edit')['handler']);
assert_same('admin_packages_store', $r->match('POST', '/admin/packages/store')['handler']);
assert_same('admin_packages_update', $r->match('POST', '/admin/packages/5/update')['handler']);
assert_same('admin_packages_delete', $r->match('POST', '/admin/packages/5/delete')['handler']);
assert_same('admin_packages_toggle', $r->match('POST', '/admin/packages/5/toggle')['handler']);
assert_same('checkout', $r->match('GET', '/thanh-toan')['handler']);
assert_same('pricing', $r->match('GET', '/bang-gia')['handler']);
assert_same('features', $r->match('GET', '/tinh-nang')['handler']);
assert_same('sitemap', $r->match('GET', '/sitemap.xml')['handler']);
assert_same('robots_txt', $r->match('GET', '/robots.txt')['handler']);
assert_same('activate', $r->match('GET', '/kich-hoat')['handler']);
assert_same('forgot_password', $r->match('GET', '/quen-mat-khau')['handler']);
assert_same('forgot_password', $r->match('POST', '/quen-mat-khau')['handler']);
assert_same('reset_password', $r->match('GET', '/dat-lai-mat-khau')['handler']);
assert_same('reset_password', $r->match('POST', '/dat-lai-mat-khau')['handler']);
assert_same('checkout_pay', $r->match('POST', '/thanh-toan/pay')['handler']);
assert_same('checkout_success', $r->match('GET', '/thanh-toan/thanh-cong')['handler']);
assert_same('checkout_cancel', $r->match('GET', '/thanh-toan/huy')['handler']);
assert_same('checkout_invoice', $r->match('GET', '/hoa-don/DH-ABC12345')['handler']);
assert_same('admin_payments', $r->match('GET', '/admin/payments')['handler']);
assert_same('admin_payments_save', $r->match('POST', '/admin/payments/save')['handler']);
assert_same('admin_orders', $r->match('GET', '/admin/orders')['handler']);
assert_same('admin_orders_status', $r->match('POST', '/admin/orders/5/status')['handler']);
assert_same('admin_links', $r->match('GET', '/admin/links')['handler']);
assert_same('admin_links_toggle', $r->match('POST', '/admin/links/5/toggle')['handler']);
assert_same('admin_links_update', $r->match('POST', '/admin/links/5/update')['handler']);
assert_same('admin_vouchers', $r->match('GET', '/admin/vouchers')['handler']);
assert_same('admin_vouchers_store', $r->match('POST', '/admin/vouchers/store')['handler']);
assert_same('admin_vouchers_update', $r->match('POST', '/admin/vouchers/5/update')['handler']);
assert_same('admin_vouchers_toggle', $r->match('POST', '/admin/vouchers/5/toggle')['handler']);
assert_same('admin_domains', $r->match('GET', '/admin/domains')['handler']);
assert_same('admin_domains_system_add', $r->match('POST', '/admin/domains/system/add')['handler']);
assert_same('admin_domains_system_default', $r->match('POST', '/admin/domains/system/5/default')['handler']);
assert_same('admin_domains_system_toggle', $r->match('POST', '/admin/domains/system/5/toggle')['handler']);
assert_same('admin_domains_system_delete', $r->match('POST', '/admin/domains/system/5/delete')['handler']);
assert_same('admin_domains_user_toggle', $r->match('POST', '/admin/domains/user/5/toggle')['handler']);
assert_same('admin_domains_user_delete', $r->match('POST', '/admin/domains/user/5/delete')['handler']);
assert_same('admin_settings', $r->match('GET', '/admin/settings')['handler']);
assert_same('admin_settings_website', $r->match('GET', '/admin/settings/website')['handler']);
assert_same('admin_settings_website_save', $r->match('POST', '/admin/settings/website/save')['handler']);
assert_same('admin_settings_invoice', $r->match('GET', '/admin/settings/invoice')['handler']);
assert_same('admin_settings_invoice_save', $r->match('POST', '/admin/settings/invoice/save')['handler']);
assert_same('admin_settings_smtp', $r->match('GET', '/admin/settings/smtp')['handler']);
assert_same('admin_settings_smtp_save', $r->match('POST', '/admin/settings/smtp/save')['handler']);
assert_same('admin_settings_smtp_test', $r->match('POST', '/admin/settings/smtp/test')['handler']);
assert_same('admin_settings_media', $r->match('GET', '/admin/settings/media')['handler']);
assert_same('admin_settings_media_save', $r->match('POST', '/admin/settings/media/save')['handler']);
assert_same('admin_settings_media_upload', $r->match('POST', '/admin/settings/media/upload')['handler']);
assert_same('admin_settings_media_delete', $r->match('POST', '/admin/settings/media/5/delete')['handler']);
assert_same('admin_settings_seo', $r->match('GET', '/admin/settings/seo')['handler']);
assert_same('admin_settings_seo_save', $r->match('POST', '/admin/settings/seo/save')['handler']);
assert_same('admin_emails', $r->match('GET', '/admin/emails')['handler']);
assert_same('admin_emails_send', $r->match('POST', '/admin/emails/send')['handler']);
        assert_same('notfound', $r->match('GET', '/dashboard/pixel/create')['handler']);
    });

    $suite->test('GET /{slug sai định dạng} -> notfound', function (): void {
        $r = new Router();
        assert_same('redirect', $r->match('GET', '/abc')['handler']);
        assert_same('redirect', $r->match('GET', '/Ab3x9-')['handler']);
        assert_same('notfound', $r->match('GET', '/ab')['handler']);
        assert_same('notfound', $r->match('GET', '/Ab3x9Q/extra')['handler']);
        assert_same('notfound', $r->match('GET', '/Ab3x9.Q')['handler']);
        assert_same('notfound', $r->match('GET', '/abcdefghijklmnopq')['handler']);
    });

    $suite->test('POST vào / -> notfound', function (): void {
        $r = new Router();
        assert_same('notfound', $r->match('POST', '/')['handler']);
    });

    $suite->test('path có trailing slash được chuẩn hoá', function (): void {
        $r = new Router();
        assert_same('home', $r->match('GET', '///')['handler']);
    });
};

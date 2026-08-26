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

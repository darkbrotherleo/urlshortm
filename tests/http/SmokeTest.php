<?php
declare(strict_types=1);

/**
 * Smoke test end-to-end qua HTTP thật bằng PHP built-in server + SQLite.
 * Được chạy khi gọi: php tests/run-tests.php --all
 */

use App\Repository\UrlRepository;

return function (TestSuite $suite): void {
    $docroot = dirname(__DIR__, 2);
    $tmpDir = sys_get_temp_dir();
    $dbFile = $tmpDir . '/urlshortm_smoke_' . bin2hex(random_bytes(4)) . '.sqlite';
    $stdoutFile = $tmpDir . '/urlshortm_smoke_' . bin2hex(random_bytes(4)) . '.out.log';
    $stderrFile = $tmpDir . '/urlshortm_smoke_' . bin2hex(random_bytes(4)) . '.err.log';
    $port = random_int(20000, 60000);
    $server = null;
    $pipes = [];

    try {
        create_sqlite_file($dbFile);

        $env = getenv();
        $env = is_array($env) ? $env : [];
        $env['URLSHORTM_DB_DRIVER'] = 'sqlite';
        $env['URLSHORTM_DB_NAME'] = $dbFile;
        $env['URLSHORTM_DEBUG'] = '1';

        $cmd = sprintf(
            '%s -d error_reporting=E_ALL -S 127.0.0.1:%d -t "%s" "%s"',
            escapeshellarg(PHP_BINARY),
            $port,
            $docroot,
            $docroot . '/tests/router.php'
        );

        $server = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutFile, 'a'],
            2 => ['file', $stderrFile, 'a'],
        ], $pipes, null, $env);

        if (!is_resource($server)) {
            throw new RuntimeException('Không thể khởi động PHP built-in server');
        }
        fclose($pipes[0]);

        $base = 'http://127.0.0.1:' . $port;

        try {
            wait_for_server($base);
        } catch (Throwable $e) {
            $detail = is_file($stderrFile) ? file_get_contents($stderrFile) : '';
            throw new RuntimeException($e->getMessage() . "\nserver stderr: " . $detail);
        }

        register_shutdown_function(function () use (&$server, &$pipes, $dbFile, $stdoutFile, $stderrFile): void {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($server)) {
                $status = proc_get_status($server);
                if (!empty($status['pid'])) {
                    @exec('taskkill /F /T /PID ' . (int) $status['pid'] . ' 2>nul');
                }
                proc_close($server);
            }
            @unlink($dbFile);
            if (getenv('URLSHORTM_KEEP_SMOKE') !== '1') {
                @unlink($stdoutFile);
                @unlink($stderrFile);
            }
        });
    } catch (Throwable $e) {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        if (is_resource($server)) {
            $status = proc_get_status($server);
            if (!empty($status['pid'])) {
                @exec('taskkill /F /T /PID ' . (int) $status['pid'] . ' 2>nul');
            }
            proc_close($server);
        }
        @unlink($dbFile);
        @unlink($stdoutFile);
        @unlink($stderrFile);
        throw $e;
    }

    $suite->test('[http] GET / trả 200 có tool thật', function () use ($base): void {
        $res = http_request('GET', $base . '/');
        assert_same(200, $res['status']);
        assert_contains('name="target"', $res['body']);
        assert_contains('Rút gọn ngay tại đây', $res['body']);
        assert_contains('giờ gọn trong vài giây', $res['body']);
        assert_false(str_contains($res['body'], 'stats-band'), 'trang chủ vẫn còn băng số liệu');
        assert_false(str_contains($res['body'], 'link đã rút gọn'), 'trang chủ vẫn còn text băng số liệu');
    });

    $suite->test('[http] nội dung không lộ thuật ngữ kỹ thuật', function () use ($base): void {
        $res = http_request('GET', $base . '/');
        foreach (['utf8mb4', 'base62', 'spec-sheet', 'relay://', 'prepared statement', 'HTTP 301', 'API'] as $term) {
            assert_false(str_contains($res['body'], $term), 'trang vẫn chứa thuật ngữ kỹ thuật: ' . $term);
        }
    });

    $suite->test('[http] POST /shorten thiếu CSRF -> 403', function () use ($base): void {
        $res = http_request('POST', $base . '/shorten', [], 'target=https://example.com/x');
        assert_same(403, $res['status']);
    });

    $suite->test('[http] luồng tạo link -> 301 redirect -> click tăng', function () use ($base, $dbFile): void {
        $home = http_request('GET', $base . '/');
        $cookie = extract_cookie($home);
        $token = extract_csrf($home['body']);
        assert_true($token !== null, 'thiếu csrf token trên trang chủ');

        $post = http_request(
            'POST',
            $base . '/shorten',
            $cookie !== null ? ['Cookie: ' . $cookie] : [],
            http_build_query(['target' => 'example.com/a/b', 'csrf_token' => $token])
        );
        assert_same(200, $post['status']);
        assert_contains('Link của bạn đã sẵn sàng', $post['body']);

        preg_match('#href="[^"]+/([0-9a-zA-Z]{6})"#', $post['body'], $m);
        assert_true(isset($m[1]), 'không tìm thấy slug trong kết quả');
        $slug = $m[1];

        $redir = http_request('GET', $base . '/' . $slug, [], null, false);
        assert_same(301, $redir['status']);
        assert_same('https://example.com/a/b', $redir['location']);

        http_request('GET', $base . '/' . $slug, [], null, false);
        http_request('GET', $base . '/' . $slug, [], null, false);

        $repo = new UrlRepository(new PDO('sqlite:' . $dbFile));
        $row = $repo->findBySlug($slug);
        assert_true($row !== null, 'slug không tồn tại trong DB');
        assert_same(3, (int) $row['click_count']);

        // Stats JSON trả đúng contract
        $stats = http_request('GET', $base . '/stats/' . $slug);
        assert_same(200, $stats['status']);
        assert_contains('application/json', $stats['headers'] ? implode("\n", $stats['headers']) : '');
        $payload = json_decode($stats['body'], true);
        assert_true(is_array($payload), 'stats body không phải JSON');
        assert_same($slug, $payload['slug'] ?? null);
        assert_same(3, $payload['click_count'] ?? null);
    });

    $suite->test('[http] /stats slug lạ -> 404, sai định dạng -> 400', function () use ($base): void {
        $missing = http_request('GET', $base . '/stats/zzzzzz', [], null, false);
        assert_same(404, $missing['status']);

        $bad = http_request('GET', $base . '/stats/ab', [], null, false);
        assert_same(400, $bad['status']);
    });

    $suite->test('[http] slug lạ -> 404', function () use ($base): void {
        $res = http_request('GET', $base . '/zzzzzz', [], null, false);
        assert_same(404, $res['status']);
    });

    $suite->test('[http] redirect giữ nguyên Unicode target', function () use ($base): void {
        $home = http_request('GET', $base . '/');
        $cookie = extract_cookie($home);
        $token = extract_csrf($home['body']);

        $post = http_request(
            'POST',
            $base . '/shorten',
            $cookie !== null ? ['Cookie: ' . $cookie] : [],
            http_build_query(['target' => 'https://example.com/đường-dẫn?q=từ', 'csrf_token' => $token])
        );
        preg_match('#href="[^"]+/([0-9a-zA-Z]{6})"#', $post['body'], $m);
        assert_true(isset($m[1]), 'không tạo được link Unicode');

        $redir = http_request('GET', $base . '/' . $m[1], [], null, false);
        assert_same(301, $redir['status']);
        assert_contains('đường-dẫn', $redir['location']);
    });

    $suite->test('[http] URL sai -> 400, không tạo record', function () use ($base): void {
        $home = http_request('GET', $base . '/');
        $cookie = extract_cookie($home);
        $token = extract_csrf($home['body']);

        $post = http_request(
            'POST',
            $base . '/shorten',
            $cookie !== null ? ['Cookie: ' . $cookie] : [],
            http_build_query(['target' => 'javascript:alert(1)', 'csrf_token' => $token])
        );
        assert_same(400, $post['status']);
        assert_contains('URL không hợp lệ', $post['body']);
    });

    $suite->test('[http] asset CSS phục vụ được', function () use ($base): void {
        $res = http_request('GET', $base . '/assets/css/style.css');
        assert_same(200, $res['status']);
        assert_contains(':root', $res['body']);
    });

    $suite->test('[http] chặn truy cập thư mục nội bộ', function () use ($base): void {
        $res = http_request('GET', $base . '/app/config.php', [], null, false);
        assert_same(403, $res['status']);
    });

    $suite->test('[http] GET /dang-ky và /dang-nhap trả 200 có form', function () use ($base): void {
        $reg = http_request('GET', $base . '/dang-ky');
        assert_same(200, $reg['status']);
        assert_contains('name="name"', $reg['body']);
        assert_contains('name="email"', $reg['body']);
        assert_contains('name="password"', $reg['body']);
        assert_contains('name="password_confirm"', $reg['body']);

        $log = http_request('GET', $base . '/dang-nhap');
        assert_same(200, $log['status']);
        assert_contains('name="email"', $log['body']);
        assert_contains('name="password"', $log['body']);
    });

    $suite->test('[http] đăng ký thiếu CSRF -> 403', function () use ($base): void {
        $res = http_request('POST', $base . '/dang-ky', [], http_build_query([
            'name' => 'A', 'email' => 'a@b.vn', 'password' => 'matkhau123', 'password_confirm' => 'matkhau123',
        ]));
        assert_same(403, $res['status']);
    });

    $suite->test('[http] đăng ký -> đăng nhập -> link gắn user -> thoát', function () use ($base, $dbFile): void {
        $page = http_request('GET', $base . '/dang-ky');
        $cookie = extract_cookie($page);
        $token = extract_csrf($page['body']);
        assert_true($token !== null, 'thiếu csrf trên trang đăng ký');

        $post = http_request('POST', $base . '/dang-ky', $cookie !== null ? ['Cookie: ' . $cookie] : [], http_build_query([
            'name' => 'Minh Anh', 'email' => 'smoke@vidu.vn', 'password' => 'matkhau123', 'password_confirm' => 'matkhau123',
            'csrf_token' => $token,
        ]), false);
        assert_same(302, $post['status']);
        assert_contains('/dashboard', $post['location'] ?? '', 'đăng ký phải chuyển về dashboard');
        $cookie2 = extract_cookie($post);
        assert_true($cookie2 !== null, 'thiếu session cookie sau đăng ký');

        $home = http_request('GET', $base . '/', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $home['status']);
        assert_contains('Xin chào', $home['body']);
        assert_contains('Minh Anh', $home['body']);
        assert_matches('#class="nav-user" href="[^"]*/dashboard"#', $home['body'], 'header chưa hiển thị tài khoản dạng link về dashboard');

        // Lưu ý: KHÔNG mở kết nối DB đọc trước khi gọi write — reader SQLite giữ
        // SHARED lock sẽ chặn server ghi (database is locked). Đọc DB sau shorten.
        $t2 = extract_csrf($home['body']);
        $shorten = http_request('POST', $base . '/shorten', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'target' => 'https://example.com/user-link', 'csrf_token' => $t2,
        ]));
        preg_match('#href="[^"]+/([0-9a-zA-Z]{6})"#', $shorten['body'], $m);
        assert_true(isset($m[1]), 'không tạo được link khi đã đăng nhập; status=' . $shorten['status']);

        $pdo = new PDO('sqlite:' . $dbFile);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute(['smoke@vidu.vn']);
        $uid = (int) $stmt->fetchColumn();
        $stmt->closeCursor();
        assert_true($uid > 0, 'không tìm thấy user sau đăng ký');

        $link = (new UrlRepository($pdo))->findBySlug($m[1]);
        assert_true($link !== null, 'slug không tồn tại trong DB: ' . $m[1]);
        assert_same($uid, (int) $link['user_id'], 'link không gắn đúng user_id');

        $t3 = extract_csrf($shorten['body']);
        $out = http_request('POST', $base . '/dang-xuat', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'csrf_token' => $t3,
        ]), false);
        assert_same(302, $out['status']);

        $home2 = http_request('GET', $base . '/');
        assert_false(str_contains($home2['body'], 'Xin chào'), 'vẫn còn trạng thái đăng nhập sau khi thoát');

        // Đăng nhập lại -> 302 về dashboard + header hiển thị tài khoản link về dashboard
        $loginPage = http_request('GET', $base . '/dang-nhap');
        $lc = extract_cookie($loginPage);
        $lt = extract_csrf($loginPage['body']);
        $login = http_request('POST', $base . '/dang-nhap', $lc !== null ? ['Cookie: ' . $lc] : [], http_build_query([
            'email' => 'smoke@vidu.vn', 'password' => 'matkhau123', 'csrf_token' => $lt,
        ]), false);
        assert_same(302, $login['status']);
        assert_contains('/dashboard', $login['location'] ?? '', 'đăng nhập phải chuyển về dashboard');
        $lc2 = extract_cookie($login);
        $afterLogin = http_request('GET', $base . '/', $lc2 !== null ? ['Cookie: ' . $lc2] : []);
        assert_matches('#class="nav-user" href="[^"]*/dashboard"#', $afterLogin['body'], 'header chưa hiển thị tài khoản dạng link về dashboard');
    });

    $suite->test('[http] link ẩn danh có user_id NULL', function () use ($base, $dbFile): void {
        $home = http_request('GET', $base . '/');
        $cookie = extract_cookie($home);
        $token = extract_csrf($home['body']);

        $post = http_request('POST', $base . '/shorten', $cookie !== null ? ['Cookie: ' . $cookie] : [], http_build_query([
            'target' => 'https://example.com/anonymous', 'csrf_token' => $token,
        ]));
        preg_match('#href="[^"]+/([0-9a-zA-Z]{6})"#', $post['body'], $m);
        assert_true(isset($m[1]));

        $pdo = new PDO('sqlite:' . $dbFile);
        $link = (new UrlRepository($pdo))->findBySlug($m[1]);
        assert_true($link !== null);
        assert_null($link['user_id']);
    });

    $suite->test('[http] dashboard: guest bị chặn, user xem tab active', function () use ($base): void {
        // Khách -> 302 về đăng nhập
        $guest = http_request('GET', $base . '/dashboard', [], null, false);
        assert_same(302, $guest['status']);

        // Đăng ký user riêng
        $page = http_request('GET', $base . '/dang-ky');
        $cookie = extract_cookie($page);
        $token = extract_csrf($page['body']);
        $reg = http_request('POST', $base . '/dang-ky', $cookie !== null ? ['Cookie: ' . $cookie] : [], http_build_query([
            'name' => 'Dash User', 'email' => 'dash@vidu.vn', 'password' => 'matkhau123', 'password_confirm' => 'matkhau123',
            'csrf_token' => $token,
        ]), false);
        assert_same(302, $reg['status']);
        $cookie2 = extract_cookie($reg);
        assert_true($cookie2 !== null, 'thiếu session sau đăng ký');

        // Tab Tổng quan (mặc định) — active trên Tổng quan
        $dash = http_request('GET', $base . '/dashboard', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $dash['status']);
        assert_contains('Bảng điều khiển', $dash['body']);
        assert_contains('Link gần đây', $dash['body']);
        assert_matches('#class="dash-nav-item is-active"[^>]*href="[^"]*\?tab=tong-quan"#', $dash['body'], 'tab Tổng quan chưa active');

        // Tạo link khi đã đăng nhập
        $t2 = extract_csrf($dash['body']);
        $shorten = http_request('POST', $base . '/shorten', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'target' => 'https://example.com/dash-link', 'csrf_token' => $t2,
        ]));
        preg_match('#href="[^"]+/([0-9a-zA-Z]{6})"#', $shorten['body'], $m);
        assert_true(isset($m[1]), 'không tạo được link trong flow dashboard');
        $slug = $m[1];

        // Tab Link của tôi — active trên links, hiển thị slug
        $linksTab = http_request('GET', $base . '/dashboard?tab=links', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $linksTab['status']);
        assert_contains($slug, $linksTab['body'], 'link mới chưa hiện trong dashboard');
        assert_false(str_contains($linksTab['body'], 'Link gần đây'), 'tab links vẫn hiển thị nội dung tổng quan');
        assert_matches('#class="dash-nav-sub is-active"[^>]*href="[^"]*\?tab=links"#', $linksTab['body'], 'tab Link của tôi chưa active');

        // Tab Tài khoản — active trên tài khoản, hiển thị email
        $accTab = http_request('GET', $base . '/dashboard?tab=tai-khoan', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $accTab['status']);
        assert_contains('dash@vidu.vn', $accTab['body']);
        assert_matches('#class="dash-nav-item is-active"[^>]*href="[^"]*\?tab=tai-khoan"#', $accTab['body'], 'tab Tài khoản chưa active');
    });

    $suite->test('[http] dashboard: sidebar phân cấp + thư mục + cài đặt', function () use ($base, $dbFile): void {
        // Đăng ký user
        $page = http_request('GET', $base . '/dang-ky');
        $cookie = extract_cookie($page);
        $token = extract_csrf($page['body']);
        $reg = http_request('POST', $base . '/dang-ky', $cookie !== null ? ['Cookie: ' . $cookie] : [], http_build_query([
            'name' => 'Folder User', 'email' => 'folder@vidu.vn', 'password' => 'matkhau123', 'password_confirm' => 'matkhau123',
            'csrf_token' => $token,
        ]), false);
        $cookie2 = extract_cookie($reg);
        assert_true($cookie2 !== null);

        // Sidebar có nhóm "Quản lý link" + All Link + Folder + Cài đặt
        $dash = http_request('GET', $base . '/dashboard', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('Quản lý link', $dash['body']);
        assert_contains('All Link', $dash['body']);
        assert_contains('Folder', $dash['body']);
        assert_contains('Cài đặt', $dash['body']);

        // Tạo link
        $t = extract_csrf($dash['body']);
        $shorten = http_request('POST', $base . '/shorten', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'target' => 'https://example.com/folder-link', 'csrf_token' => $t,
        ]));
        preg_match('#href="[^"]+/([0-9a-zA-Z]{6})"#', $shorten['body'], $m);
        assert_true(isset($m[1]));
        $slug = $m[1];

        // Tạo thư mục (POST) -> 302 về ?tab=folder
        $t2 = extract_csrf($shorten['body']);
        $mk = http_request('POST', $base . '/dashboard/folder/create', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'name' => 'Công việc', 'csrf_token' => $t2,
        ]), false);
        assert_same(302, $mk['status']);

        $folderTab = http_request('GET', $base . '/dashboard?tab=folder', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $folderTab['status']);
        assert_contains('Công việc', $folderTab['body'], 'thư mục mới chưa hiển thị');
        assert_matches('#class="dash-nav-sub is-active"[^>]*href="[^"]*\?tab=folder"#', $folderTab['body'], 'tab Folder chưa active');

        // Lấy folder_id + link_id từ DB, rồi ĐÓNG connection để không giữ SHARED lock
        // khi gọi write tiếp theo (server sẽ bị lock nếu reader còn mở).
        if (preg_match('#\?tab=folder&amp;folder=(\d+)#', $folderTab['body'], $fm) !== 1) {
            preg_match('#\?tab=folder&folder=(\d+)#', $folderTab['body'], $fm);
        }
        assert_true(isset($fm[1]), 'không tìm thấy folder id');
        $fid = (int) $fm[1];

        $rdb = new PDO('sqlite:' . $dbFile);
        $rs = $rdb->prepare('SELECT id FROM short_links WHERE slug = ?');
        $rs->execute([$slug]);
        $linkId = (int) $rs->fetchColumn();
        $rs->closeCursor();
        $rdb = null;
        assert_true($linkId > 0, 'không tìm thấy link id');

        $t3 = extract_csrf($folderTab['body']);
        $assign = http_request('POST', $base . '/dashboard/link-folder', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'link_id' => $linkId, 'folder_id' => $fid, 'return_tab' => 'folder', 'return_folder' => $fid, 'csrf_token' => $t3,
        ]), false);
        assert_same(302, $assign['status']);

        // Vào folder -> link xuất hiện
        $inFolder = http_request('GET', $base . '/dashboard?tab=folder&folder=' . $fid, $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $inFolder['status']);
        assert_contains($slug, $inFolder['body'], 'link chưa vào folder');

        // Cài đặt: đổi tên hiển thị
        $settings = http_request('GET', $base . '/dashboard?tab=cai-dat', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $settings['status']);
        assert_contains('Tên hiển thị', $settings['body']);
        $t4 = extract_csrf($settings['body']);
        $save = http_request('POST', $base . '/dashboard/settings', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'display_name' => 'Tên Mới', 'csrf_token' => $t4,
        ]), false);
        assert_same(302, $save['status']);

        $after = http_request('GET', $base . '/dashboard?tab=cai-dat', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('Tên Mới', $after['body'], 'tên hiển thị chưa được cập nhật');
    });

    $suite->test('[http] link manager: tạo -> bảng -> mật khẩu -> unlock -> sửa -> xoá -> hết hạn', function () use ($base): void {
        // Đăng ký + login
        $page = http_request('GET', $base . '/dang-ky');
        $cookie = extract_cookie($page);
        $token = extract_csrf($page['body']);
        $reg = http_request('POST', $base . '/dang-ky', $cookie !== null ? ['Cookie: ' . $cookie] : [], http_build_query([
            'name' => 'Link Mgr', 'email' => 'linkmgr@vidu.vn', 'password' => 'matkhau123', 'password_confirm' => 'matkhau123',
            'csrf_token' => $token,
        ]), false);
        $cookie2 = extract_cookie($reg);
        assert_true($cookie2 !== null);

        // Trang tạo link
        $form = http_request('GET', $base . '/dashboard/link/new', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $form['status']);
        assert_contains('Tạo Link Mới', $form['body']);
        assert_contains('name="link_type"', $form['body']);
        assert_contains('name="utm_campaign"', $form['body']);
        assert_contains('name="custom_slug"', $form['body']);
        assert_contains('name="folder_id"', $form['body']);
        assert_contains('name="password"', $form['body']);
        assert_contains('name="starts_at"', $form['body']);

        // Form mới: upload thumbnail, droplist pixels, toggle password
        assert_contains('enctype="multipart/form-data"', $form['body'], 'form chưa dùng multipart');
        assert_contains('name="thumbnail" type="file"', $form['body'], 'chưa có upload thumbnail');
        assert_contains('id="pixel-drop"', $form['body'], 'chưa có droplist pixels');
        assert_contains('id="pixel-panel"', $form['body']);
        assert_contains('name="password_enabled"', $form['body'], 'chưa có toggle password');
        assert_false(str_contains($form['body'], 'password_clear'), 'vẫn còn tick xoá mật khẩu');

        // Tạo link đầy đủ
        $t = extract_csrf($form['body']);
        $create = http_request('POST', $base . '/dashboard/link', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'link_type' => 'link', 'target' => 'https://example.com/doi-tac', 'title' => 'Title A',
            'description' => 'Mô tả A', 'pixels' => 'px1, px2', 'utm_campaign' => 'camp',
            'custom_slug' => 'doi-tac', 'password' => 'matkhaumat', 'folder_id' => '', 'csrf_token' => $t,
        ]), false);
        assert_same(302, $create['status']);

        // All Link: title + custom slug hiển thị
        $linksTab = http_request('GET', $base . '/dashboard?tab=links', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('Title A', $linksTab['body']);
        assert_contains('doi-tac', $linksTab['body']);
        assert_contains('px1, px2', $linksTab['body']);
        assert_contains('Tạo Link Mới', $linksTab['body']);

        // QR Designer: đủ controls + preview + download + lưu ý
        foreach (['qr-shape-style', 'qr-corner-style', 'qr-shape-color', 'qr-corner-color', 'qr-canvas', 'qr-save-svg', 'qr-save-png'] as $ctrl) {
            assert_contains('id="' . $ctrl . '"', $linksTab['body'], 'thiếu control QR: ' . $ctrl);
        }
        assert_contains('Shape style', $linksTab['body']);
        assert_contains('Corner style', $linksTab['body']);
        assert_contains('Luôn quét mã QR bằng điện thoại trước khi in', $linksTab['body']);
        assert_contains('Tải về file SVG', $linksTab['body']);
        assert_contains('Tải về file PNG', $linksTab['body']);

        // Thư viện QR + qr.js được phục vụ
        foreach (['assets/js/vendor/qrcode.js', 'assets/js/qr.js'] as $asset) {
            $res = http_request('GET', $base . '/' . $asset);
            assert_same(200, $res['status'], 'asset không phục vụ: ' . $asset);
        }

        // Share menu: 5 mạng xã hội + nút Share mang data-title
        assert_contains('id="share-menu"', $linksTab['body'], 'thiếu share menu');
        foreach (['fb', 'in', 'x', 'msg', 'zalo'] as $net) {
            assert_contains('data-share="' . $net . '"', $linksTab['body'], 'thiếu mạng share: ' . $net);
        }
        foreach (['Facebook', 'Linkedin', 'Messenger', 'Zalo'] as $label) {
            assert_contains($label, $linksTab['body'], 'thiếu nhãn share: ' . $label);
        }
        assert_contains('data-title="Title A"', $linksTab['body'], 'nút Share chưa mang tiêu đề link');

        // Link có mật khẩu -> trang nhập mật khẩu
        $locked = http_request('GET', $base . '/doi-tac', [], null, false);
        assert_same(200, $locked['status']);
        assert_contains('Link này có mật khẩu', $locked['body']);
        $lt = extract_csrf($locked['body']);

        // Unlock sai -> 400; đúng -> 302
        $bad = http_request('POST', $base . '/doi-tac/unlock', extract_cookie($locked) !== null ? ['Cookie: ' . extract_cookie($locked)] : [], http_build_query([
            'password' => 'saisai', 'csrf_token' => $lt,
        ]), false);
        assert_same(400, $bad['status']);

        $good = http_request('POST', $base . '/doi-tac/unlock', extract_cookie($locked) !== null ? ['Cookie: ' . extract_cookie($locked)] : [], http_build_query([
            'password' => 'matkhaumat', 'csrf_token' => $lt,
        ]), false);
        assert_same(302, $good['status']);

        $open = http_request('GET', $base . '/doi-tac', extract_cookie($locked) !== null ? ['Cookie: ' . extract_cookie($locked)] : [], null, false);
        assert_same(301, $open['status']);
        assert_same('https://example.com/doi-tac', $open['location']);

        // Lấy id link từ nút Edit
        preg_match('#/dashboard/link/(\d+)/edit#', $linksTab['body'], $m);
        assert_true(isset($m[1]), 'không tìm thấy nút Edit');
        $id = (int) $m[1];

        // Sửa
        $editPage = http_request('GET', $base . '/dashboard/link/' . $id . '/edit', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $editPage['status']);
        assert_contains('doi-tac', $editPage['body'], 'form sửa chưa prefill custom slug');
        $t2 = extract_csrf($editPage['body']);
        $update = http_request('POST', $base . '/dashboard/link/' . $id . '/update', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'link_type' => 'link', 'target' => 'https://example.com/doi-tac', 'title' => 'Title B',
            'custom_slug' => 'doi-tac', 'folder_id' => '', 'csrf_token' => $t2,
        ]), false);
        assert_same(302, $update['status']);

        $afterUpd = http_request('GET', $base . '/dashboard?tab=links', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('Title B', $afterUpd['body']);
        assert_false(str_contains($afterUpd['body'], 'Title A'), 'title cũ vẫn còn sau khi sửa');

        // Link hết hạn (tạo trực tiếp với ends_at quá khứ qua form)
        $t3 = extract_csrf($afterUpd['body']);
        $expired = http_request('POST', $base . '/dashboard/link', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'link_type' => 'link', 'target' => 'https://example.com/old', 'title' => 'Cũ',
            'custom_slug' => 'hethan', 'ends_at' => '2020-01-01T00:00', 'csrf_token' => $t3,
        ]), false);
        assert_same(302, $expired['status']);

        $blocked = http_request('GET', $base . '/hethan', [], null, false);
        assert_same(410, $blocked['status']);
        assert_contains('đã hết hạn', $blocked['body']);

        // Xoá link
        $t4 = extract_csrf($afterUpd['body']);
        $del = http_request('POST', $base . '/dashboard/link/' . $id . '/delete', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'csrf_token' => $t4,
        ]), false);
        assert_same(302, $del['status']);

        $gone = http_request('GET', $base . '/dashboard?tab=links', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_false(str_contains($gone['body'], 'Title B'), 'link chưa bị xoá');
    });

    $suite->test('[http] tạo link: upload thumbnail + chọn nhiều pixels', function () use ($base, $dbFile): void {
        // Đăng ký + login
        $page = http_request('GET', $base . '/dang-ky');
        $cookie = extract_cookie($page);
        $token = extract_csrf($page['body']);
        $reg = http_request('POST', $base . '/dang-ky', $cookie !== null ? ['Cookie: ' . $cookie] : [], http_build_query([
            'name' => 'Uploader', 'email' => 'upload@vidu.vn', 'password' => 'matkhau123', 'password_confirm' => 'matkhau123',
            'csrf_token' => $token,
        ]), false);
        $cookie2 = extract_cookie($reg);
        assert_true($cookie2 !== null);

        $form = http_request('GET', $base . '/dashboard/link/new', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        $t = extract_csrf($form['body']);

        // Ảnh PNG 1x1 hợp lệ
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true);

        $res = http_request('POST', $base . '/dashboard/link', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], [
            'link_type' => 'link', 'target' => 'https://example.com/co-anh', 'title' => 'Có ảnh',
            'pixels' => 'fb-pixel, ga4', 'custom_slug' => 'co-anh', 'folder_id' => '', 'csrf_token' => $t,
        ], false, ['thumbnail' => ['name' => 'thumb.png', 'type' => 'image/png', 'content' => $png]]);
        assert_same(302, $res['status'], 'tạo link upload thumbnail thất bại');

        // Kiểm tra DB: thumbnail path + pixels JSON (đóng connection trước khi có write tiếp theo)
        $pdo = new PDO('sqlite:' . $dbFile);
        $stmt = $pdo->prepare('SELECT thumbnail, pixels FROM short_links WHERE slug = ?');
        $stmt->execute(['co-anh']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $pdo = null;

        assert_true(is_array($row), 'không tìm thấy link co-anh');
        assert_contains('/uploads/', (string) ($row['thumbnail'] ?? ''), 'thumbnail chưa được lưu');
        $pixels = json_decode((string) ($row['pixels'] ?? ''), true);
        assert_same(['fb-pixel', 'ga4'], $pixels, 'chọn nhiều pixels chưa đúng');

        // File upload tồn tại trên đĩa
        $fileName = basename((string) $row['thumbnail']);
        assert_true(is_file(dirname(__DIR__, 2) . '/uploads/' . $fileName), 'file thumbnail không tồn tại');

        // Bảng All Link hiển thị pixels đã chọn
        $links = http_request('GET', $base . '/dashboard?tab=links', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('fb-pixel, ga4', $links['body'], 'pixels chưa hiển thị trong bảng');
    });
};

function create_sqlite_file(string $file): PDO
{
    if (is_file($file)) {
        @unlink($file);
    }

    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // busy_timeout ngắn để mọi lock-wait fail nhanh thay vì treo 30s mặc định.
    $pdo->exec('PRAGMA busy_timeout = 2000');

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

    $pdo->exec('CREATE TABLE rate_limits (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_hash      TEXT NOT NULL,
        bucket_key   TEXT NOT NULL,
        window_start INTEGER NOT NULL,
        count        INTEGER NOT NULL DEFAULT 0,
        UNIQUE (ip_hash, bucket_key, window_start)
    )');

    $pdo->exec('CREATE TABLE users (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        email            TEXT NOT NULL UNIQUE,
        password_hash    TEXT NOT NULL,
        display_name     TEXT NULL,
        status           TEXT NOT NULL DEFAULT \'active\',
        email_verified_at TEXT NULL,
        last_login_at    TEXT NULL,
        created_at       TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at       TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE plans (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        code          TEXT NOT NULL UNIQUE,
        name          TEXT NOT NULL,
        description   TEXT NULL,
        price_monthly NUMERIC NULL,
        price_yearly  NUMERIC NULL,
        features      TEXT NULL,
        is_active     INTEGER NOT NULL DEFAULT 1,
        sort_order    INTEGER NOT NULL DEFAULT 0,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE user_subscriptions (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        plan_id    INTEGER NOT NULL,
        status     TEXT NOT NULL DEFAULT \'trial\',
        starts_at  TEXT NULL,
        ends_at    TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE folders (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        name       TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE pixels (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        code       TEXT NOT NULL UNIQUE,
        name       TEXT NULL,
        is_active  INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    return $pdo;
}

function build_multipart(array $fields, array $files, string $boundary): string
{
    $body = '';

    foreach ($fields as $key => $value) {
        $body .= '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n"
            . $value . "\r\n";
    }

    foreach ($files as $key => $file) {
        $body .= '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . $key . '"; filename="' . ($file['name'] ?? 'file') . "\"\r\n"
            . 'Content-Type: ' . ($file['type'] ?? 'application/octet-stream') . "\r\n\r\n"
            . $file['content'] . "\r\n";
    }

    $body .= '--' . $boundary . "--\r\n";

    return $body;
}

function wait_for_server(string $base, int $maxTries = 50): void
{
    for ($i = 0; $i < $maxTries; $i++) {
        $res = @http_request('GET', $base . '/');
        if ($res !== null && $res['status'] > 0) {
            return;
        }
        usleep(100000);
    }
    throw new RuntimeException('Server không khởi động kịp: ' . $base);
}

function http_request(string $method, string $url, array $headers = [], $body = null, bool $follow = true, array $files = []): array
{
    $opts = [
        'http' => [
            'method'          => $method,
            'header'          => $headers,
            'ignore_errors'   => true,
            'timeout'         => 10,
            'follow_location' => $follow ? 1 : 0,
        ],
    ];

    if ($files !== []) {
        $boundary = '----usm' . bin2hex(random_bytes(8));
        $body = build_multipart((array) $body, $files, $boundary);
        $opts['http']['content'] = $body;
        $opts['http']['header'] = array_merge($opts['http']['header'], [
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Content-Length: ' . strlen($body),
        ]);
    } elseif ($body !== null) {
        $opts['http']['content'] = $body;
        $opts['http']['header'] = array_merge($opts['http']['header'], [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($body),
        ]);
    }

    $ctx = stream_context_create($opts);

    // Built-in server (Windows) đơn luồng: pace giữa các request + retry có backoff
    // để tránh race accept / cạn cổng tạm (TIME_WAIT) khi bắn request liên tiếp nhanh.
    usleep(50000);

    $backoffs = [0, 250000, 800000];
    $fp = false;
    foreach ($backoffs as $wait) {
        if ($wait > 0) {
            usleep($wait);
        }
        $fp = @fopen($url, 'rb', false, $ctx);
        if ($fp !== false) {
            break;
        }
    }

    if ($fp === false) {
        return ['status' => 0, 'headers' => [], 'body' => '', 'location' => null, 'cookie' => null];
    }

    $meta = stream_get_meta_data($fp);
    $headerLines = $meta['wrapper_data'] ?? [];
    $bodyContent = (string) stream_get_contents($fp);
    fclose($fp);

    $status = 0;
    $location = null;
    $cookie = null;

    foreach ($headerLines as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $m)) {
            $status = (int) $m[1];
        } elseif (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, strlen('Location:')));
        } elseif (stripos($line, 'Set-Cookie:') === 0) {
            $cookie = $cookie ?? trim(substr($line, strlen('Set-Cookie:')));
        }
    }

    return [
        'status'   => $status,
        'headers'  => $headerLines,
        'body'     => $bodyContent,
        'location' => $location,
        'cookie'   => $cookie,
    ];
}

function extract_cookie(array $res): ?string
{
    if ($res['cookie'] === null) {
        return null;
    }

    return explode(';', $res['cookie'])[0];
}

function extract_csrf(string $body): ?string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $m)) {
        return $m[1];
    }

    return null;
}

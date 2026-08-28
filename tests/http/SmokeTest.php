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
        $env['URLSHORTM_DOMAINS_DNS_CHECK'] = '0';

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

        // GĐ 0: mỗi lần mở ghi 1 click_event
        $evPdo = new PDO('sqlite:' . $dbFile);
        $stmt = $evPdo->prepare('SELECT COUNT(*) FROM click_events WHERE link_id = ?');
        $stmt->execute([(int) $row['id']]);
        $events = (int) $stmt->fetchColumn();
        $stmt->closeCursor();
        $evPdo = null;
        assert_same(3, $events, 'click_events phải bằng số lượt mở');

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

    $suite->test('[http] đăng ký -> kích hoạt -> đăng nhập -> link gắn user -> thoát', function () use ($base, $dbFile): void {
        // Đăng ký -> trả về trang "kiểm tra email" (tài khoản PENDING)
        $page = http_request('GET', $base . '/dang-ky');
        $cookie = extract_cookie($page);
        $token = extract_csrf($page['body']);
        assert_true($token !== null, 'thiếu csrf trên trang đăng ký');

        $post = http_request('POST', $base . '/dang-ky', $cookie !== null ? ['Cookie: ' . $cookie] : [], http_build_query([
            'name' => 'Minh Anh', 'email' => 'smoke@vidu.vn', 'password' => 'matkhau123', 'password_confirm' => 'matkhau123',
            'csrf_token' => $token,
        ]), false);
        assert_same(200, $post['status']);
        assert_contains('Kích hoạt', $post['body'], 'đăng ký phải hiện trang chờ kích hoạt');
        assert_null(extract_cookie($post), 'đăng ký KHÔNG được tự đăng nhập');

        // Chưa kích hoạt -> không đăng nhập được
        $loginPage = http_request('GET', $base . '/dang-nhap');
        $lc = extract_cookie($loginPage);
        $lt = extract_csrf($loginPage['body']);
        $try = http_request('POST', $base . '/dang-nhap', $lc !== null ? ['Cookie: ' . $lc] : [], http_build_query([
            'email' => 'smoke@vidu.vn', 'password' => 'matkhau123', 'csrf_token' => $lt,
        ]), false);
        assert_same(400, $try['status']);
        assert_contains('kích hoạt', $try['body'], 'login tài khoản PENDING phải bị chặn');

        // Kích hoạt qua token trong DB -> tự đăng nhập
        $pdo0 = new PDO('sqlite:' . $dbFile);
        $stmt0 = $pdo0->prepare('SELECT activation_token FROM users WHERE email = ?');
        $stmt0->execute(['smoke@vidu.vn']);
        $act = $stmt0->fetchColumn();
        $stmt0 = null;
        $pdo0 = null;
        assert_true($act !== false && $act !== null, 'không có activation token');
        $actResp = http_request('GET', $base . '/kich-hoat?token=' . urlencode((string) $act), [], null, false);
        assert_same(302, $actResp['status']);
        $cookie2 = extract_cookie($actResp);
        assert_true($cookie2 !== null, 'kích hoạt phải tự đăng nhập');

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

    $suite->test('[http] dashboard: guest bị chặn, user xem tab active', function () use ($base, $dbFile): void {
        // Khách -> 302 về đăng nhập
        $guest = http_request('GET', $base . '/dashboard', [], null, false);
        assert_same(302, $guest['status']);

        // Đăng ký user riêng + kích hoạt
        $cookie2 = register_and_activate($base, $dbFile, 'Dash User', 'dash@vidu.vn', 'matkhau123');

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
        // Đăng ký user + kích hoạt
        $cookie2 = register_and_activate($base, $dbFile, 'Folder User', 'folder@vidu.vn', 'matkhau123');

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

    $suite->test('[http] link manager: tạo -> bảng -> mật khẩu -> unlock -> sửa -> xoá -> hết hạn', function () use ($base, $dbFile): void {
        // Đăng ký + kích hoạt
        $cookie2 = register_and_activate($base, $dbFile, 'Link Mgr', 'linkmgr@vidu.vn', 'matkhau123');

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

        // Khung xem trước link (live)
        assert_contains('Xem trước link', $form['body'], 'chưa có khung xem trước');
        assert_contains('id="link-preview"', $form['body']);
        assert_contains('id="link-preview-title"', $form['body']);
        assert_contains('id="link-preview-desc"', $form['body']);
        assert_contains('id="link-preview-url"', $form['body']);
        assert_contains('id="link-preview-thumb"', $form['body']);
        assert_contains('id="link-preview-type"', $form['body']);

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
        assert_same('https://example.com/doi-tac?utm_campaign=camp', $open['location']);

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

    $suite->test('[http] UTM tracking: redirect gắn UTM vào URL đích', function () use ($base, $dbFile): void {
        // Đăng ký + kích hoạt
        $cookie2 = register_and_activate($base, $dbFile, 'Utm User', 'utm@vidu.vn', 'matkhau123');

        $form = http_request('GET', $base . '/dashboard/link/new', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        $t = extract_csrf($form['body']);

        $create = http_request('POST', $base . '/dashboard/link', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'link_type' => 'link', 'target' => 'https://example.com/utm-dich', 'custom_slug' => 'utm-tracking',
            'utm_campaign' => 'thang9', 'utm_medium' => 'social', 'utm_source' => 'fb',
            'utm_term' => 'tu-khoa', 'utm_content' => 'banner', 'csrf_token' => $t,
        ]), false);
        assert_same(302, $create['status']);

        $redir = http_request('GET', $base . '/utm-tracking', [], null, false);
        assert_same(301, $redir['status']);
        assert_contains('utm_campaign=thang9', $redir['location'] ?? '', 'redirect thiếu UTM campaign');
        assert_contains('utm_medium=social', $redir['location'] ?? '');
        assert_contains('utm_source=fb', $redir['location'] ?? '');
        assert_contains('utm_term=tu-khoa', $redir['location'] ?? '');
        assert_contains('utm_content=banner', $redir['location'] ?? '');
    });

    $suite->test('[http] GĐ0 tracking: click_event lưu IP hash + UA + referrer + user', function () use ($base, $dbFile): void {
        $cookie2 = register_and_activate($base, $dbFile, 'Track User', 'track@vidu.vn', 'matkhau123');

        $form = http_request('GET', $base . '/dashboard/link/new', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        $t = extract_csrf($form['body']);
        $create = http_request('POST', $base . '/dashboard/link', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'link_type' => 'link', 'target' => 'https://example.com/track-dich', 'custom_slug' => 'track-link', 'csrf_token' => $t,
        ]), false);
        assert_same(302, $create['status']);

        // Mở link với UA + Referer
        $open = http_request('GET', $base . '/track-link', [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'Referer: https://google.com/search?q=abc',
        ], null, false);
        assert_same(301, $open['status']);

        // Đọc DB sau write (đóng connection)
        $pdo = new PDO('sqlite:' . $dbFile);
        $link = (new UrlRepository($pdo))->findBySlug('track-link');
        assert_true($link !== null);

        $stmt = $pdo->prepare('SELECT user_id, ip_hash, ip_address, user_agent, referrer, device, browser, os, country FROM click_events WHERE link_id = ?');
        $stmt->execute([(int) $link['id']]);
        $ev = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $pdo = null;

        assert_true(is_array($ev), 'không có click_event');
        assert_true((int) $ev['user_id'] > 0, 'click_event thiếu user_id');
        assert_matches('/^[0-9a-f]{64}$/', (string) $ev['ip_hash'], 'ip_hash không phải hash');
        assert_same('127.0.0.1', $ev['ip_address'] ?? null, 'phải lưu IP thật (không mã hoá)');
        assert_contains('iPhone', (string) $ev['user_agent'], 'thiếu user_agent');
        assert_contains('google.com', (string) $ev['referrer'], 'thiếu referrer');
        // GĐ1: parse UA + GeoIP
        assert_same('mobile', $ev['device'], 'device sai');
        assert_same('Safari', $ev['browser'], 'browser sai');
        assert_same('iOS', $ev['os'], 'os sai');
        assert_null($ev['country'], 'IP local không được có quốc gia');

        // GĐ2: trang Báo cáo hiển thị cho user có click
        $report = http_request('GET', $base . '/dashboard?tab=baocao', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $report['status']);
        assert_contains('Báo cáo', $report['body']);
        assert_contains('id="chart-day"', $report['body']);
        assert_contains('id="report-data"', $report['body']);
        assert_contains('Thiết bị', $report['body']);
        assert_contains('Nguồn vào (Referrer)', $report['body']);
        assert_contains('chart.umd.min.js', $report['body'], 'chưa load Chart.js');
        assert_contains('report.js', $report['body']);

        // GĐ3: chi tiết lượt mở + export CSV
        assert_contains('Chi tiết lượt mở', $report['body'], 'thiếu bảng chi tiết');
        assert_contains('Tải CSV', $report['body'], 'thiếu nút tải CSV');
        assert_contains('127.0.0.1', $report['body'], 'bảng chi tiết thiếu IP thật');
        assert_contains('<th>IP</th>', $report['body'], 'bảng chi tiết thiếu cột IP');

        $csv = http_request('GET', $base . '/dashboard/bao-cao/export?tab=baocao', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('text/csv', $csv['headers'] ? implode("\n", $csv['headers']) : '', 'CSV thiếu content-type');
        assert_contains('Thời gian', $csv['body']);
        assert_contains('track-link', $csv['body'], 'CSV thiếu dữ liệu link');
        assert_contains('iOS', $csv['body'], 'CSV thiếu dữ liệu thiết bị');
        assert_contains('127.0.0.1', $csv['body'], 'CSV thiếu cột IP thật');
    });

    $suite->test('[http] tạo link: upload thumbnail + chọn nhiều pixels', function () use ($base, $dbFile): void {
        // Đăng ký + kích hoạt
        $cookie2 = register_and_activate($base, $dbFile, 'Uploader', 'upload@vidu.vn', 'matkhau123');

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

    $suite->test('[http] Cài đặt: sidebar con + pixels/domain/utm hoạt động', function () use ($base, $dbFile): void {
        // Đăng ký + kích hoạt
        $cookie2 = register_and_activate($base, $dbFile, 'Set User', 'set@vidu.vn', 'matkhau123');

        // Sidebar: nhóm Cài đặt với 4 con
        $dash = http_request('GET', $base . '/dashboard', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('>Cài đặt<', $dash['body'], 'chưa có nhóm Cài đặt');
        foreach (['Cài đặt tài khoản', 'Thiết lập Pixels', 'Custom domain', 'UTMs tracking'] as $child) {
            assert_contains($child, $dash['body'], 'thiếu mục con: ' . $child);
        }

        // Tab pixels
        $pixelsPage = http_request('GET', $base . '/dashboard?tab=pixels', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $pixelsPage['status']);
        assert_contains('name="platform"', $pixelsPage['body'], 'chưa có select platform');
        assert_contains('Name of Pixel', $pixelsPage['body']);
        assert_contains('Pixel ID', $pixelsPage['body']);
        assert_contains('Pixels</h2>', $pixelsPage['body']);
        foreach (['Name</th>', 'Platform</th>', 'Value</th>', 'Creation date</th>', 'Action</th>'] as $col) {
            assert_contains($col, $pixelsPage['body'], 'thi?u c?t: ' . $col);
        }
        assert_contains('tro-giup/pixel-id', $pixelsPage['body'], 'thi?u link hu?ng d?n');
        $t = extract_csrf($pixelsPage['body']);
        $mk = http_request('POST', $base . '/dashboard/pixel/create', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'platform' => 'facebook', 'code' => 'mypixel1', 'name' => 'Pixel cua toi', 'csrf_token' => $t,
        ]), false);
        assert_same(302, $mk['status']);

        // Pixel vừa tạo có Action Sửa + Xoá; sửa thành công
        $after = http_request('GET', $base . '/dashboard?tab=pixels', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('>Sửa<', $after['body'], 'thiếu nút Sửa pixel');
        assert_contains('>Xoá<', $after['body'], 'thiếu nút Xoá pixel');
        preg_match('#\?tab=pixels&amp;edit=(\d+)#', $after['body'], $em);
        if (preg_match('#\?tab=pixels&amp;edit=(\d+)#', $after['body'], $em) !== 1) {
            preg_match('#\?tab=pixels&edit=(\d+)#', $after['body'], $em);
        }
        assert_true(isset($em[1]), 'không tìm thấy nút Sửa pixel');
        $pid = (int) $em[1];

        $editPage = http_request('GET', $base . '/dashboard?tab=pixels&edit=' . $pid, $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $editPage['status']);
        assert_contains('Sửa Pixel', $editPage['body']);
        assert_contains('value="mypixel1"', $editPage['body'], 'form sửa chưa prefill code');

        $tU = extract_csrf($editPage['body']);
        $upd = http_request('POST', $base . '/dashboard/pixel/update', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'pixel_id' => $pid, 'platform' => 'tiktok', 'code' => 'mypixel2', 'name' => 'Pixel moi', 'csrf_token' => $tU,
        ]), false);
        assert_same(302, $upd['status']);

        $afterUpd = http_request('GET', $base . '/dashboard?tab=pixels', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('mypixel2', $afterUpd['body'], 'pixel chưa được cập nhật');
        assert_false(str_contains($afterUpd['body'], 'mypixel1'), 'pixel cũ vẫn còn sau khi sửa');

        // Trang hu?ng d?n l?y Pixel ID
        $help = http_request('GET', $base . '/tro-giup/pixel-id');
        assert_same(200, $help['status']);
        assert_contains('hero-title', $help['body']);
        assert_contains('Facebook / Meta', $help['body']);
        assert_contains('Google Ads', $help['body']);
        assert_contains('TikTok', $help['body']);

        // Trang hướng dẫn thêm tên miền
        $helpDomain = http_request('GET', $base . '/tro-giup/custom-domain');
        assert_same(200, $helpDomain['status']);
        assert_contains('Cách thêm tên miền tuỳ chỉnh', $helpDomain['body']);
        assert_contains('CNAME', $helpDomain['body']);
        assert_contains('lan truyền DNS', $helpDomain['body']);

        // Wiki tài liệu + footer có "Trợ giúp"
        $wiki = http_request('GET', $base . '/tro-giup');
        assert_same(200, $wiki['status']);
        assert_contains('Wiki tài liệu UrlShortM', $wiki['body']);
        assert_contains('Mục lục', $wiki['body']);
        assert_contains('Tạo link rút gọn', $wiki['body']);
        assert_contains('Bảo vệ link bằng mật khẩu', $wiki['body']);
        assert_contains('Cách lấy Pixel ID', $wiki['body']);
        assert_contains('Câu hỏi thường gặp', $wiki['body']);

        $homeF = http_request('GET', $base . '/');
        assert_contains('Trợ giúp', $homeF['body'], 'footer thiếu menu Trợ giúp');
        assert_contains('/tro-giup', $homeF['body'], 'footer thiếu link trợ giúp');

        // Tab domains
        $domainsPage = http_request('GET', $base . '/dashboard?tab=domains', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $domainsPage['status']);
        assert_contains('Custom domain', $domainsPage['body']);
        $t2 = extract_csrf($domainsPage['body']);

        // localhost -> tự xác minh (test)
        $addLocal = http_request('POST', $base . '/dashboard/domain/create', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'domain' => 'localhost', 'csrf_token' => $t2,
        ]), false);
        assert_same(302, $addLocal['status']);

        // domain thật -> chưa xác minh, hiển thị TXT + nút Xác minh
        $addFake = http_request('POST', $base . '/dashboard/domain/create', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'domain' => 'link.viducongty.com', 'csrf_token' => $t2,
        ]), false);
        assert_same(302, $addFake['status']);

        // link.mark.test (.test) -> tự xác minh
        $addMark = http_request('POST', $base . '/dashboard/domain/create', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'domain' => 'link.mark.test', 'csrf_token' => $t2,
        ]), false);
        assert_same(302, $addMark['status']);

        $afterDom = http_request('GET', $base . '/dashboard?tab=domains', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $afterDom['status']);
        assert_contains('localhost', $afterDom['body']);
        assert_contains('Đã xác minh', $afterDom['body'], 'localhost phải tự xác minh');
        assert_contains('link.mark.test', $afterDom['body']);
        assert_contains('link.viducongty.com', $afterDom['body']);
        assert_contains('urlshortm-verify=', $afterDom['body'], 'thiếu bản ghi TXT hướng dẫn');
        assert_contains('>Xác minh<', $afterDom['body'], 'thiếu nút Xác minh');
        assert_contains('Làm thế nào để thêm tên miền của bạn', $afterDom['body'], 'thiếu guide add domain');
        assert_contains('tro-giup/custom-domain', $afterDom['body'], 'thiếu link trang hướng dẫn');

        // Bấm Xác minh domain thật (dns_check tắt trong test) -> báo lỗi, không verified
        $tV = extract_csrf($afterDom['body']);
        $vid = 0;
        if (preg_match('#/dashboard/domain/verify".{0,400}?name="domain_id" value="(\d+)"#s', $afterDom['body'], $dm) === 1) {
            $vid = (int) $dm[1];
        }
        assert_true($vid > 0, 'không tìm thấy domain_id để xác minh');
        $verify = http_request('POST', $base . '/dashboard/domain/verify', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'domain_id' => $vid, 'csrf_token' => $tV,
        ]), false);
        assert_same(302, $verify['status']);
        $afterVerify = http_request('GET', $base . '/dashboard?tab=domains', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('Chưa tìm thấy bản ghi TXT', $afterVerify['body'], 'verify thất bại phải báo lỗi TXT');

        // Form tạo link: chỉ domain đã xác minh (localhost, link.mark.test) xuất hiện
        $form2 = http_request('GET', $base . '/dashboard/link/new', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('value="localhost"', $form2['body'], 'localhost chưa vào select domain');
        assert_contains('value="link.mark.test"', $form2['body'], 'link.mark.test chưa vào select domain');
        assert_false(str_contains($form2['body'], 'value="link.viducongty.com"'), 'domain chưa xác minh không được chọn');

        // Tab utms + tạo profile
        $utmsPage = http_request('GET', $base . '/dashboard?tab=utms', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $utmsPage['status']);
        assert_contains('UTMs tracking', $utmsPage['body']);
        assert_contains('utm-help', $utmsPage['body'], 'thiếu khung giải thích UTM');
        assert_contains('UTM tags là gì?', $utmsPage['body']);
        assert_contains('UTM Campaign', $utmsPage['body']);
        assert_contains('UTM Medium', $utmsPage['body']);
        assert_contains('UTM Source', $utmsPage['body']);
        assert_contains('utm-layout', $utmsPage['body'], 'thiếu layout 2 cột UTM');
        $t3 = extract_csrf($utmsPage['body']);
        $utm = http_request('POST', $base . '/dashboard/utm/store', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'name' => 'Quảng cáo FB', 'utm_campaign' => 'camp', 'utm_medium' => 'social',
            'utm_source' => 'fb', 'utm_term' => '', 'utm_content' => '', 'csrf_token' => $t3,
        ]), false);
        assert_same(302, $utm['status']);

        // Form tạo link: pixel + domain + profile UTM xuất hiện
        $form = http_request('GET', $base . '/dashboard/link/new', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $form['status']);
        assert_contains('value="mypixel2"', $form['body'], 'pixel của user chưa vào droplist');
        assert_contains('value="localhost"', $form['body'], 'domain đã xác minh chưa vào select');
        assert_contains('id="utm-profile"', $form['body'], 'chưa có select profile UTM');
        assert_contains('data-campaign="camp"', $form['body'], 'profile UTM chưa vào select');

        // Tab demographics + lưu cấu hình Meta (không gọi API thật trong smoke)
        $demoPage = http_request('GET', $base . '/dashboard?tab=demographics', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $demoPage['status']);
        assert_contains('Nhân khẩu học (Meta)', $demoPage['body']);
        assert_contains('id="meta-ad"', $demoPage['body'], 'thiếu input Ad Account');
        assert_contains('id="meta-token"', $demoPage['body'], 'thiếu input Access Token');
        assert_contains('Chưa có dữ liệu', $demoPage['body'], 'chưa có snapshot phải hiện trạng thái rỗng');
        $t4 = extract_csrf($demoPage['body']);
        $demoSave = http_request('POST', $base . '/dashboard/demographics/save', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'meta_ad_account' => 'act_1234567890', 'meta_token' => 'EAAsecretxyz', 'csrf_token' => $t4,
        ]), false);
        assert_same(302, $demoSave['status']);
        $demoPage2 = http_request('GET', $base . '/dashboard?tab=demographics', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('act_1234567890', $demoPage2['body'], 'Ad Account ID chưa được lưu');
        assert_contains('••••txyz', $demoPage2['body'], 'token phải được che dấu (chỉ lộ 4 ký tự cuối)');
        if (str_contains($demoPage2['body'], 'EAAsecret')) {
            fail_test('token tuyệt đối không được lộ ra trong HTML');
        }
    });

    $suite->test('[http] tài khoản: đổi mật khẩu + vô hiệu hoá (soft, không xoá)', function () use ($base, $dbFile): void {
        // Đăng ký + kích hoạt
        $cookie2 = register_and_activate($base, $dbFile, 'Tai Khoan', 'taikhoan@vidu.vn', 'matkhau123');

        // Tab tài khoản có form đổi mật khẩu + vô hiệu hoá
        $acc = http_request('GET', $base . '/dashboard?tab=tai-khoan', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $acc['status']);
        assert_contains('Đổi mật khẩu', $acc['body']);
        assert_contains('Vô hiệu hoá tài khoản', $acc['body']);
        assert_contains('id="pw-current"', $acc['body'], 'thiếu ô mật khẩu hiện tại');
        assert_contains('Gói & giới hạn sử dụng', $acc['body'], 'thiếu panel gói & giới hạn');
        assert_contains('Miễn phí', $acc['body'], 'user chưa mua gói phải hiện Miễn phí');

        // Đổi mật khẩu sai mật khẩu cũ -> 302 kèm error, vẫn login bằng mật khẩu cũ
        $t = extract_csrf($acc['body']);
        $bad = http_request('POST', $base . '/dashboard/account/password', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'current_password' => 'saimatkhau', 'new_password' => 'matkhauMoi456', 'new_password_confirm' => 'matkhauMoi456', 'csrf_token' => $t,
        ]), false);
        assert_same(302, $bad['status']);
        assert_contains('error', (string) ($bad['location'] ?? ''), 'sai mật khẩu cũ phải redirect kèm error');

        // Đổi mật khẩu đúng -> 302 ok=1
        $ok = http_request('POST', $base . '/dashboard/account/password', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'current_password' => 'matkhau123', 'new_password' => 'matkhauMoi456', 'new_password_confirm' => 'matkhauMoi456', 'csrf_token' => $t,
        ]), false);
        assert_same(302, $ok['status']);
        assert_contains('ok=1', (string) ($ok['location'] ?? ''), 'đổi mật khẩu đúng phải redirect ok');

        // Vô hiệu hoá bằng mật khẩu MỚI -> 302 về /dang-nhap?disabled=1 (soft, không xoá)
        $acc2 = http_request('GET', $base . '/dashboard?tab=tai-khoan', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        $t2 = extract_csrf($acc2['body']);
        $deact = http_request('POST', $base . '/dashboard/account/deactivate', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'current_password' => 'matkhauMoi456', 'csrf_token' => $t2,
        ]), false);
        assert_same(302, $deact['status']);
        assert_contains('disabled=1', (string) ($deact['location'] ?? ''), 'vô hiệu hoá phải redirect về đăng nhập kèm disabled=1');

        // Trang đăng nhập hiện thông báo đã vô hiệu hoá
        $loginNotice = http_request('GET', $base . '/dang-nhap?disabled=1');
        assert_contains('đã được vô hiệu hoá', $loginNotice['body']);

        // Đăng nhập bị từ chối (ACCOUNT_DISABLED -> 400 + thông báo khoá)
        $loginPage = http_request('GET', $base . '/dang-nhap');
        $lc = extract_cookie($loginPage);
        $lt = extract_csrf($loginPage['body']);
        $try = http_request('POST', $base . '/dang-nhap', $lc !== null ? ['Cookie: ' . $lc] : [], http_build_query([
            'email' => 'taikhoan@vidu.vn', 'password' => 'matkhauMoi456', 'csrf_token' => $lt,
        ]), false);
        assert_same(400, $try['status']);
        assert_contains('khoá', $try['body'], 'login tài khoản disabled phải bị từ chối');
    });

    $suite->test('[http] cài đặt: hồ sơ + hoá đơn (mã số thuế) lưu và hiển thị', function () use ($base, $dbFile): void {
        // Đăng ký + kích hoạt
        $cookie2 = register_and_activate($base, $dbFile, 'Hoa Don', 'hoadon@vidu.vn', 'matkhau123');

        // Form cài đặt có các trường mới
        $set = http_request('GET', $base . '/dashboard?tab=cai-dat', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_same(200, $set['status']);
        assert_contains('id="phone"', $set['body']);
        assert_contains('id="tax_type"', $set['body']);
        assert_contains('id="tax_id"', $set['body']);
        assert_contains('id="invoice_name"', $set['body']);
        assert_contains('Thông tin xuất hoá đơn', $set['body']);
        $t = extract_csrf($set['body']);

        // Mã số thuế sai -> 302 kèm error, không lưu
        $bad = http_request('POST', $base . '/dashboard/settings', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'display_name' => 'Hoa Don', 'phone' => '0901234567', 'address' => '12 Lê Lợi', 'city' => 'Hồ Chí Minh',
            'tax_type' => 'business', 'company_name' => 'CTY Demo', 'invoice_name' => 'CTY Demo',
            'tax_id' => 'abc123', 'csrf_token' => $t,
        ]), false);
        assert_same(302, $bad['status']);
        assert_contains('error', (string) ($bad['location'] ?? ''), 'mã số thuế sai phải redirect kèm error');

        // Lưu hợp lệ -> 302 ok=1
        $ok = http_request('POST', $base . '/dashboard/settings', $cookie2 !== null ? ['Cookie: ' . $cookie2] : [], http_build_query([
            'display_name' => 'Hoa Don', 'phone' => '0901234567', 'address' => '12 Lê Lợi', 'city' => 'Hồ Chí Minh',
            'tax_type' => 'business', 'company_name' => 'Công ty TNHH Demo', 'invoice_name' => 'Công ty TNHH Demo',
            'tax_id' => '0312345678', 'csrf_token' => $t,
        ]), false);
        assert_same(302, $ok['status']);
        assert_contains('ok=1', (string) ($ok['location'] ?? ''));

        // Form giữ lại giá trị đã lưu
        $set2 = http_request('GET', $base . '/dashboard?tab=cai-dat', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('0901234567', $set2['body'], 'chưa giữ số điện thoại');
        assert_contains('0312345678', $set2['body'], 'chưa giữ mã số thuế');
        assert_contains('Công ty TNHH Demo', $set2['body'], 'chưa giữ tên công ty');

        // Tab Tài khoản hiển thị thông tin
        $acc = http_request('GET', $base . '/dashboard?tab=tai-khoan', $cookie2 !== null ? ['Cookie: ' . $cookie2] : []);
        assert_contains('0901234567', $acc['body'], 'thiếu số điện thoại trong thông tin tài khoản');
        assert_contains('0312345678', $acc['body'], 'thiếu mã số thuế trong thông tin tài khoản');
        assert_contains('Doanh nghiệp', $acc['body'], 'thiếu loại khách hàng');
    });

    $suite->test('[http] admin: đăng nhập -> vào trang quản trị, logout', function () use ($base): void {
        // Chưa đăng nhập -> chuyển về trang đăng nhập admin
        $anon = http_request('GET', $base . '/admin', [], null, false);
        assert_same(302, $anon['status']);
        assert_contains('admin/dang-nhap', (string) ($anon['location'] ?? ''), 'chưa đăng nhập phải về trang đăng nhập admin');

        // Trang đăng nhập admin
        $login = http_request('GET', $base . '/admin/dang-nhap');
        assert_same(200, $login['status']);
        assert_contains('Quản trị hệ thống', $login['body']);
        assert_contains('id="alogin-email"', $login['body']);
        $cookie = extract_cookie($login);
        $token = extract_csrf($login['body']);

        // Sai mật khẩu -> 401
        $bad = http_request('POST', $base . '/admin/dang-nhap', $cookie !== null ? ['Cookie: ' . $cookie] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'SaiMatKhau', 'csrf_token' => $token,
        ]), false);
        assert_same(401, $bad['status']);
        assert_contains('không đúng', $bad['body']);

        // Đăng nhập đúng -> 302 về /admin
        $ok = http_request('POST', $base . '/admin/dang-nhap', $cookie !== null ? ['Cookie: ' . $cookie] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $token,
        ]), false);
        assert_same(302, $ok['status']);
        assert_contains('admin', (string) ($ok['location'] ?? ''), 'đăng nhập thành công phải về /admin');
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        // Trang quản trị hiển thị
        $dash = http_request('GET', $base . '/admin', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $dash['status']);
        assert_contains('Tổng quan', $dash['body']);
        assert_contains('Quản lý người dùng', $dash['body']);
        assert_contains('Quản lý Link', $dash['body']);
        assert_contains('Cài đặt Website', $dash['body']);
        assert_contains('admin-chart-data', $dash['body'], 'thiếu data biểu đồ admin');
        assert_contains('Quản trị hệ thống', $dash['body']);

        // Đăng xuất -> về trang đăng nhập, /admin bị chặn
        $lg = http_request('GET', $base . '/admin', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        $t2 = extract_csrf($lg['body']);
        $out = http_request('POST', $base . '/admin/dang-xuat', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $t2,
        ]), false);
        assert_same(302, $out['status']);
        assert_contains('admin/dang-nhap', (string) ($out['location'] ?? ''), 'logout phải về trang đăng nhập admin');

        $after = http_request('GET', $base . '/admin', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], null, false);
        assert_same(302, $after['status']);
        assert_contains('admin/dang-nhap', (string) ($after['location'] ?? ''), 'sau logout phải bị chặn');
    });

    $suite->test('[http] admin users: danh sách + modal + sửa full/gói/trạng thái', function () use ($base, $dbFile): void {
        // Dùng user có sẵn trong DB (không đăng ký mới để tránh rate limit register)
        $pdo = new PDO('sqlite:' . $dbFile);
        $users = $pdo->query('SELECT id, email, display_name FROM users ORDER BY id LIMIT 2')->fetchAll();
        $pdo = null;
        assert_same(2, count($users), 'cần ít nhất 2 user có sẵn');
        $target = $users[count($users) - 1];
        $otherEmail = $users[0]['email'];
        $targetEmail = $target['email'];

        // Đăng nhập admin
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        // Danh sách: đủ cột + user có sẵn + data JSON cho modal
        $list = http_request('GET', $base . '/admin/users', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $list['status']);
        assert_contains('<th>Username</th>', $list['body']);
        assert_contains('<th>Ngày mua</th>', $list['body']);
        assert_contains('<th>Ngày hết hạn</th>', $list['body']);
        assert_contains('<th>Trạng thái</th>', $list['body']);
        assert_contains($targetEmail, $list['body'], 'user có sẵn phải có trong danh sách');
        assert_contains('admin-users-data', $list['body'], 'thiếu data JSON cho modal');
        assert_contains('a-modal-info', $list['body'], 'thiếu modal thông tin');
        assert_contains('a-modal-edit', $list['body'], 'thiếu modal sửa');
        $ut = extract_csrf($list['body']);

        // Sửa user: gói Starter + ngày + vô hiệu hoá
        $upd = http_request('POST', $base . '/admin/users/update', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'user_id' => (int) $target['id'], 'csrf_token' => $ut,
            'display_name' => 'User Quan Ly', 'email' => $targetEmail,
            'phone' => '0912345678', 'address' => 'Số 1', 'city' => 'Hà Nội',
            'tax_type' => 'individual', 'company_name' => '', 'tax_id' => '0123456789', 'invoice_name' => 'User Quan Ly',
            'plan_id' => 2, 'sub_start' => '2026-08-01', 'sub_end' => '2026-09-01',
            'status' => 'disabled',
        ]), false);
        assert_same(302, $upd['status']);
        assert_contains('ok=1', (string) ($upd['location'] ?? ''), 'sửa thành công phải redirect ok');

        // Danh sách phản ánh: badge Starter + Bị vô hiệu
        $list2 = http_request('GET', $base . '/admin/users', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('Starter', $list2['body'], 'chưa đổi gói sang Starter');
        assert_contains('Bị vô hiệu', $list2['body'], 'chưa vô hiệu hoá user');
        assert_contains('2026-08-01', $list2['body'], 'thiếu ngày mua');
        assert_contains('2026-09-01', $list2['body'], 'thiếu ngày hết hạn');

        // Sửa sai (email trùng user khác) -> error, không lưu
        $bad = http_request('POST', $base . '/admin/users/update', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'user_id' => (int) $target['id'], 'csrf_token' => $ut,
            'display_name' => 'X', 'email' => $otherEmail, 'status' => 'active', 'plan_id' => 0,
        ]), false);
        assert_same(302, $bad['status']);
        assert_contains('error', (string) ($bad['location'] ?? ''), 'email trùng phải báo lỗi');
    });

    $suite->test('[http] admin packages: list + thêm + sửa + toggle + xoá (chặn khi đang dùng)', function () use ($base, $dbFile): void {
        // Đăng nhập admin
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        // Danh sách gói
        $list = http_request('GET', $base . '/admin/packages', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $list['status']);
        assert_contains('<th>Tên gói</th>', $list['body']);
        assert_contains('<th>Chu kỳ</th>', $list['body']);
        assert_contains('<th>Số link</th>', $list['body']);
        assert_contains('<th>Trạng thái</th>', $list['body']);
        assert_contains('Starter', $list['body']);
        assert_contains('Phổ biến', $list['body'], 'Starter phải có label Phổ biến');
        assert_contains('admin/packages/new', $list['body'], 'thiếu nút Thêm gói mới');
        $pt = extract_csrf($list['body']);

        // Form thêm
        $new = http_request('GET', $base . '/admin/packages/new', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $new['status']);
        assert_contains('id="pkg-name"', $new['body']);
        assert_contains('id="pkg-code"', $new['body']);
        assert_contains('name="max_links"', $new['body']);
        assert_contains('name="has_analytics"', $new['body']);
        assert_contains('name="is_popular"', $new['body']);
        $ft = extract_csrf($new['body']);

        // Thêm gói Enterprise
        $store = http_request('POST', $base . '/admin/packages/store', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $ft, 'name' => 'Enterprise', 'code' => 'enterprise',
            'description' => 'Gói doanh nghiệp', 'price' => '1999000', 'currency' => 'VND',
            'billing_period' => 'monthly', 'max_links' => '-1', 'max_clicks' => '-1',
            'max_custom_domains' => '50', 'max_pixels' => '-1', 'max_users' => '50', 'sort_order' => '50',
            'has_analytics' => '1', 'has_qr_code' => '1', 'has_password_protection' => '1',
            'has_link_expiration' => '1', 'has_utm_builder' => '1', 'has_api_access' => '1',
            'is_popular' => '1', 'is_active' => '1',
        ]), false);
        assert_same(302, $store['status']);
        assert_contains('ok=1', (string) ($store['location'] ?? ''));

        $list2 = http_request('GET', $base . '/admin/packages', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('Enterprise', $list2['body'], 'chưa thấy gói mới trong danh sách');
        assert_contains('1.999.000 VND', $list2['body'], 'giá định dạng chưa đúng');

        // Sửa gói Enterprise -> đổi tên + tắt active
        preg_match_all('#admin/packages/(\d+)/edit#', $list2['body'], $edits);
        $enterpriseId = (int) end($edits[1]);
        assert_true($enterpriseId > 0, 'không tìm thấy id gói Enterprise');

        $editPage = http_request('GET', $base . '/admin/packages/' . $enterpriseId . '/edit', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $editPage['status']);
        assert_contains('value="Enterprise"', $editPage['body']);
        $et = extract_csrf($editPage['body']);
        $upd = http_request('POST', $base . '/admin/packages/' . $enterpriseId . '/update', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $et, 'name' => 'Enterprise Pro', 'code' => 'enterprise',
            'description' => 'x', 'price' => '1999000', 'currency' => 'VND', 'billing_period' => 'monthly',
            'max_links' => '-1', 'max_clicks' => '-1', 'max_custom_domains' => '50', 'max_pixels' => '-1', 'max_users' => '50',
            'sort_order' => '50', 'is_active' => '0',
        ]), false);
        assert_same(302, $upd['status']);
        $list3 = http_request('GET', $base . '/admin/packages', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('Enterprise Pro', $list3['body']);
        assert_contains('>Tắt<', $list3['body'], 'gói tắt phải hiện trạng thái Tắt');

        // Toggle -> bật lại
        $tg = http_request('POST', $base . '/admin/packages/' . $enterpriseId . '/toggle', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $et,
        ]), false);
        assert_same(302, $tg['status']);
        $list4 = http_request('GET', $base . '/admin/packages', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('Hoạt động', $list4['body'], 'sau toggle phải hoạt động lại');

        // Xoá gói đang được dùng (starter có sub active từ test users) -> bị chặn
        $delInUse = http_request('POST', $base . '/admin/packages/2/delete', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $et,
        ]), false);
        assert_same(302, $delInUse['status']);
        assert_contains('error', (string) ($delInUse['location'] ?? ''), 'xoá gói đang dùng phải báo lỗi');
        $list5 = http_request('GET', $base . '/admin/packages', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('Starter', $list5['body'], 'gói đang dùng không được xoá');

        // Xoá Enterprise (không ai dùng) -> OK
        $del = http_request('POST', $base . '/admin/packages/' . $enterpriseId . '/delete', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $et,
        ]), false);
        assert_same(302, $del['status']);
        $list6 = http_request('GET', $base . '/admin/packages', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        if (str_contains($list6['body'], 'Enterprise Pro')) {
            fail_test('gói không ai dùng phải bị xoá');
        }
    });

    $suite->test('[http] đơn hàng: chọn gói -> thanh toán (mock) -> success -> hoá đơn -> gói active + admin cổng thanh toán', function () use ($base, $dbFile): void {
        // Reset rate limit + chuẩn bị hồ sơ hoá đơn cho user mới (trực tiếp DB)
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->exec('DELETE FROM rate_limits');
        $pdo = null;

        $userCookie = register_and_activate($base, $dbFile, 'Nguoi Mua', 'checkout@vidu.vn', 'matkhau123');

        // Gán thông tin hoá đơn cho user (trực tiếp DB)
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->prepare("UPDATE users SET tax_id = ?, invoice_name = ?, company_name = ?, address = ?, city = ? WHERE email = ?")
            ->execute(['0123456789', 'Nguoi Mua', '', '12 Le Loi', 'HCM', 'checkout@vidu.vn']);
        $pdo = null;

        // Trang chọn gói
        $pick = http_request('GET', $base . '/thanh-toan', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        assert_same(200, $pick['status']);
        assert_contains('Nâng cấp gói dịch vụ', $pick['body']);
        assert_contains('Starter', $pick['body']);
        assert_contains('thanh-toan?plan=2', $pick['body'], 'thiếu nút chọn gói');

        // Checkout gói Starter
        $checkout = http_request('GET', $base . '/thanh-toan?plan=2', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        assert_same(200, $checkout['status']);
        assert_contains('Thanh toán đơn hàng', $checkout['body']);
        assert_contains('PayPal', $checkout['body']);
        assert_contains('chế độ test (giả lập)', $checkout['body'], 'chưa cấu hình cổng phải báo mock');
        assert_contains('0123456789', $checkout['body'], 'thiếu MST trong thông tin hoá đơn');
        $ct = extract_csrf($checkout['body']);

        // Thanh toán -> mock -> success
        $pay = http_request('POST', $base . '/thanh-toan/pay', $userCookie !== null ? ['Cookie: ' . $userCookie] : [], http_build_query([
            'plan_id' => 2, 'csrf_token' => $ct,
        ]), false);
        assert_same(302, $pay['status']);
        $loc = (string) ($pay['location'] ?? '');
        assert_contains('thanh-cong', $loc);
        assert_contains('mock=1', $loc, 'chưa cấu hình phải redirect mock');
        preg_match('#order=(DH-[A-Z0-9]+)#', $loc, $om);
        $orderCode = $om[1] ?? '';
        assert_true($orderCode !== '', 'thiếu mã đơn');

        $success = http_request('GET', $base . '/thanh-toan/thanh-cong?order=' . $orderCode . '&mock=1', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        assert_same(200, $success['status']);
        assert_contains('Thanh toán thành công', $success['body']);
        assert_contains('Starter', $success['body']);
        assert_contains('hoa-don/' . $orderCode, $success['body'], 'thiếu nút xem hoá đơn');

        // Gói đã kích hoạt cho user + đơn paid
        $pdo = new PDO('sqlite:' . $dbFile);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute(['checkout@vidu.vn']);
        $uid = (int) $stmt->fetchColumn();
        $stmt2 = $pdo->prepare("SELECT p.name, s.status FROM user_subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? AND s.status IN ('active','trial')");
        $stmt2->execute([$uid]);
        $sub = $stmt2->fetch();
        assert_true($sub !== false, 'phải có subscription active');
        assert_same('Starter', $sub['name']);
        $stmt3 = $pdo->prepare('SELECT status FROM orders WHERE order_code = ?');
        $stmt3->execute([$orderCode]);
        assert_same('paid', $stmt3->fetchColumn());
        $stmt = $stmt2 = $stmt3 = null;
        $pdo = null;
        usleep(80000);

        // Hoá đơn chuẩn Việt Nam
        $inv = http_request('GET', $base . '/hoa-don/' . $orderCode, $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        assert_same(200, $inv['status']);
        assert_contains('HOÁ ĐƠN GIÁ TRỊ GIA TĂNG', $inv['body']);
        assert_contains('Người mua hàng', $inv['body']);
        assert_contains('Nguoi Mua', $inv['body']);
        assert_contains('0123456789', $inv['body'], 'thiếu MST người mua trên hoá đơn');
        assert_contains('Tổng cộng tiền thanh toán', $inv['body']);
        assert_contains('Thuế suất GTGT', $inv['body']);

        // Admin: trang Cổng thanh toán + lưu cấu hình PayPal (cùng session cookie user)
        $login = http_request('GET', $base . '/admin/dang-nhap', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $userCookie !== null ? ['Cookie: ' . $userCookie] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        assert_same(302, $ok['status']);
        // session_regenerate_id -> phải dùng cookie mới
        $newSession = extract_cookie($ok);
        if ($newSession !== null) {
            $userCookie = $newSession;
        }
        $payments = http_request('GET', $base . '/admin/payments', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        assert_same(200, $payments['status']);
        assert_contains('PayPal', $payments['body']);
        assert_contains('id="pp-client"', $payments['body']);
        assert_contains('Sandbox', $payments['body']);
        $pt = extract_csrf($payments['body']);
        $save = http_request('POST', $base . '/admin/payments/save', $userCookie !== null ? ['Cookie: ' . $userCookie] : [], http_build_query([
            'paypal_client_id' => 'test-client-id', 'paypal_secret' => 'test-secret', 'paypal_mode' => 'sandbox', 'csrf_token' => $pt,
        ]), false);
        assert_same(302, $save['status']);
        assert_contains('ok=1', (string) ($save['location'] ?? ''));

        // Sau khi cấu hình -> checkout không còn báo mock
        $checkout2 = http_request('GET', $base . '/thanh-toan?plan=2', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        if (str_contains($checkout2['body'], 'chế độ test (giả lập)')) {
            fail_test('sau khi cấu hình PayPal không được còn mock');
        }
    });

    $suite->test('[http] bảng giá: trang public + menu header + nút Mua ngay', function () use ($base): void {
        $pg = http_request('GET', $base . '/bang-gia');
        assert_same(200, $pg['status']);
        assert_contains('Bảng giá', $pg['body']);
        assert_contains('Starter', $pg['body']);
        assert_contains('Pro', $pg['body']);
        assert_contains('Team', $pg['body']);
        assert_contains('Mua ngay', $pg['body'], 'thiếu nút Mua ngay');
        assert_contains('thanh-toan?plan=', $pg['body'], 'thiếu link chọn gói');
        assert_contains('bang-gia', $pg['body'], 'menu header phải có link Bảng giá');
        assert_contains('Được chọn nhiều', $pg['body'], 'thiếu badge gói phổ biến');

        // Trang tính năng (landing bán hàng) + chọn gói tích hợp
        $ft = http_request('GET', $base . '/tinh-nang');
        assert_same(200, $ft['status']);
        assert_contains('Mọi thứ bạn cần', $ft['body'], 'thiếu hero');
        assert_contains('Tính năng giúp bạn bán hàng tốt hơn', $ft['body']);
        assert_contains('Custom domain thương hiệu', $ft['body'], 'thiếu feature card');
        assert_contains('Sẵn sàng biến link thành kênh bán hàng', $ft['body'], 'thiếu CTA chốt');
        assert_contains('Starter', $ft['body']);
        assert_contains('Team', $ft['body']);
        assert_contains('Mua ngay', $ft['body'], 'thiếu nút mua trong phần chọn gói');
        assert_contains('thanh-toan?plan=', $ft['body'], 'thiếu link mua gói');
    });

    $suite->test('[http] admin: danh sách đơn hàng + lịch sử thanh toán + cập nhật trạng thái', function () use ($base, $dbFile): void {
        // Seed đơn hàng trực tiếp cho user có sẵn
        $pdo = new PDO('sqlite:' . $dbFile);
        $stmt = $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1');
        $uid = (int) $stmt->fetchColumn();
        $stmt = null;
        assert_true($uid > 0);
        $ins = $pdo->prepare("INSERT INTO orders (order_code, user_id, plan_id, plan_name, billing_period, amount, currency, status, payment_method) VALUES (?,?,?,?,?,?,?,?,?)");
        $ins->execute(['DH-ORDTEST1', $uid, 2, 'Starter', 'monthly', 149000, 'VND', 'pending', 'paypal']);
        $ins->execute(['DH-ORDTEST2', $uid, 3, 'Pro', 'monthly', 399000, 'VND', 'paid', 'paypal']);
        $pdo->prepare("UPDATE orders SET paid_at = ?, payer = ? WHERE order_code = 'DH-ORDTEST2'")->execute([date('Y-m-d H:i:s'), 'payer@example.com']);
        $ins = null;
        $pdo = null;

        // Admin login (jar mới)
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        // Danh sách đơn hàng
        $list = http_request('GET', $base . '/admin/orders', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $list['status']);
        assert_contains('<th>Mã đơn</th>', $list['body']);
        assert_contains('<th>Trạng thái</th>', $list['body']);
        assert_contains('DH-ORDTEST1', $list['body']);
        assert_contains('DH-ORDTEST2', $list['body']);
        assert_contains('Chờ thanh toán', $list['body']);
        assert_contains('Cập nhật', $list['body']);
        $ot = extract_csrf($list['body']);

        // Lịch sử thanh toán đã bỏ khỏi menu -> route không còn
        $hist = http_request('GET', $base . '/admin/payment-history', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], null, false);
        assert_same(404, $hist['status'], 'route payment-history phải bị bỏ');
        if (str_contains($hist['body'], 'Người thanh toán')) {
            fail_test('không còn trang lịch sử thanh toán');
        }

        // Cập nhật đơn pending -> paid -> kích hoạt gói cho user
        $stmt2 = (new PDO('sqlite:' . $dbFile))->prepare('SELECT id FROM orders WHERE order_code = ?');
        $stmt2->execute(['DH-ORDTEST1']);
        $orderId = (int) $stmt2->fetchColumn();
        $stmt2 = null;
        $upd = http_request('POST', $base . '/admin/orders/' . $orderId . '/status', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'status' => 'paid', 'payer' => 'admin-manual', 'csrf_token' => $ot,
        ]), false);
        assert_same(302, $upd['status']);
        assert_contains('ok=1', (string) ($upd['location'] ?? ''));

        $pdo = new PDO('sqlite:' . $dbFile);
        $stmt3 = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
        $stmt3->execute([$orderId]);
        assert_same('paid', $stmt3->fetchColumn());
        $stmt3 = null;
        $stmt4 = $pdo->prepare("SELECT p.name FROM user_subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? AND s.status IN ('active','trial')");
        $stmt4->execute([$uid]);
        $planName = $stmt4->fetchColumn();
        $stmt4 = null;
        $pdo = null;
        assert_same('Starter', $planName, 'cập nhật paid phải kích hoạt gói cho user');
    });

    $suite->test('[http] admin links: bảng + tự xoá link khách 15 ngày + vô hiệu + sửa', function () use ($base, $dbFile): void {
        // Seed: link khách cũ (20 ngày), link khách mới, link của user
        $pdo = new PDO('sqlite:' . $dbFile);
        $stmt = $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1');
        $uid = (int) $stmt->fetchColumn();
        $stmt = null;
        assert_true($uid > 0);
        $ins = $pdo->prepare('INSERT INTO short_links (slug, target_url, user_id, created_at) VALUES (?,?,?,?)');
        $ins->execute(['gggggg', 'https://old-guest.com', null, date('Y-m-d H:i:s', strtotime('-20 days'))]);
        $ins->execute(['hhhhhh', 'https://new-guest.com', null, date('Y-m-d H:i:s')]);
        $pdo->prepare('INSERT INTO short_links (slug, target_url, user_id) VALUES (?,?,?)')->execute(['kkkkkk', 'https://user-link.com', $uid]);
        $ins = null;
        $pdo = null;

        // Admin login
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        // Bảng quản lý link + tự xoá link khách cũ
        $list = http_request('GET', $base . '/admin/links', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $list['status']);
        assert_contains('<th>User</th>', $list['body']);
        assert_contains('<th>URL Short</th>', $list['body']);
        assert_contains('<th>Time Create</th>', $list['body']);
        assert_contains('<th>Time End</th>', $list['body']);
        assert_contains('<th>QR Code</th>', $list['body']);
        assert_contains('Khách (ẩn danh)', $list['body'], 'thiếu nhãn link khách');
        assert_contains('Đã tự xoá <b>1</b> link khách', $list['body'], 'chưa tự xoá link khách 15 ngày');
        assert_contains('hhhhhh', $list['body'], 'link khách mới phải còn');
        if (str_contains($list['body'], 'old-guest.com')) {
            fail_test('link khách cũ phải bị tự xoá');
        }
        $lt = extract_csrf($list['body']);

        // Tìm theo slug
        $find = http_request('GET', $base . '/admin/links?q=kkkkkk', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('kkkkkk', $find['body']);
        if (str_contains($find['body'], 'hhhhhh')) {
            fail_test('tìm theo slug phải lọc đúng');
        }

        // Vô hiệu link -> mở link bị chặn
        $stmt2 = (new PDO('sqlite:' . $dbFile))->prepare('SELECT id FROM short_links WHERE slug = ?');
        $stmt2->execute(['kkkkkk']);
        $linkId = (int) $stmt2->fetchColumn();
        $stmt2 = null;
        $tg = http_request('POST', $base . '/admin/links/' . $linkId . '/toggle', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $lt,
        ]), false);
        assert_same(302, $tg['status']);
        $open = http_request('GET', $base . '/kkkkkk', [], null, false);
        assert_same(410, $open['status']);
        assert_contains('vô hiệu hoá', $open['body'], 'link bị vô hiệu phải chặn');

        // Sửa link qua admin
        $upd = http_request('POST', $base . '/admin/links/' . $linkId . '/update', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $lt, 'target_url' => 'https://edited.com', 'title' => 'Sửa bởi admin',
            'description' => '', 'ends_at' => '2026-12-31', 'is_active' => '1',
        ]), false);
        assert_same(302, $upd['status']);
        assert_contains('ok=1', (string) ($upd['location'] ?? ''));
        $list2 = http_request('GET', $base . '/admin/links', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('edited.com', $list2['body'], 'chưa lưu URL đích mới');
    });

    $suite->test('[http] voucher: admin tạo + bảng + áp dụng vào đơn hàng (giảm giá + ghi nhận)', function () use ($base, $dbFile): void {
        // Reset rate limit + về mock (xoá cấu hình PayPal của test trước) + đăng ký user
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->exec('DELETE FROM rate_limits');
        $pdo->exec("DELETE FROM settings WHERE skey LIKE 'paypal_%'");
        $pdo = null;
        $userCookie = register_and_activate($base, $dbFile, 'Voucher User', 'voucher@vidu.vn', 'matkhau123');

        // Admin tạo voucher 10%
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        $vlist = http_request('GET', $base . '/admin/vouchers', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $vlist['status']);
        assert_contains('<th>Mã Voucher</th>', $vlist['body']);
        assert_contains('<th>Đơn hàng áp dụng</th>', $vlist['body']);
        assert_contains('<th>Giá giảm sau voucher</th>', $vlist['body']);
        assert_contains('<th>Trạng thái áp dụng</th>', $vlist['body']);
        $vt = extract_csrf($vlist['body']);

        $store = http_request('POST', $base . '/admin/vouchers/store', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $vt, 'campaign_name' => 'KM 8.8', 'code' => 'GIAM10',
            'usage_limit' => '100', 'per_user' => 'once', 'discount_type' => 'percent',
            'discount_value' => '10', 'starts_at' => '', 'ends_at' => '', 'note' => 'test', 'is_active' => '1',
        ]), false);
        assert_same(302, $store['status']);
        $vlist2 = http_request('GET', $base . '/admin/vouchers', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('GIAM10', $vlist2['body'], 'chưa thấy voucher mới');

        // Áp dụng voucher trên trang checkout: giảm 10% (149000 -> 134100)
        $checkout = http_request('GET', $base . '/thanh-toan?plan=2&voucher=GIAM10', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        assert_same(200, $checkout['status']);
        assert_contains('Phải thanh toán', $checkout['body']);
        assert_contains('134.100', $checkout['body'], 'chưa giảm giá đúng 10%');
        $ct = extract_csrf($checkout['body']);

        // Thanh toán mock với voucher -> đơn giá sau giảm + ghi nhận usage
        $pay = http_request('POST', $base . '/thanh-toan/pay', $userCookie !== null ? ['Cookie: ' . $userCookie] : [], http_build_query([
            'plan_id' => 2, 'voucher' => 'GIAM10', 'csrf_token' => $ct,
        ]), false);
        if (!str_contains((string) ($pay['location'] ?? ''), 'thanh-cong')) {
            file_put_contents('C:/Users/nguye/AppData/Local/Temp/opencode/v_err.txt', 'PAY:' . $pay['status'] . ' LOC:' . (string) ($pay['location'] ?? '') . "\n");
        }
        assert_same(302, $pay['status']);
        $loc = (string) ($pay['location'] ?? '');
        preg_match('#order=(DH-[A-Z0-9]+)#', $loc, $om);
        $orderCode = $om[1] ?? '';
        assert_true($orderCode !== '', 'thiếu mã đơn sau thanh toán voucher');
        $success = http_request('GET', $base . '/thanh-toan/thanh-cong?order=' . $orderCode . '&mock=1', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        assert_same(200, $success['status']);

        // Đơn amount = 134100 + voucher used_count=1 + usage row
        $pdo = new PDO('sqlite:' . $dbFile);
        $st = $pdo->prepare('SELECT amount FROM orders WHERE order_code = ?');
        $st->execute([$orderCode]);
        assert_same(134100.0, (float) $st->fetchColumn());
        $st = null;
        $st2 = $pdo->prepare('SELECT used_count FROM vouchers WHERE code = ?');
        $st2->execute(['GIAM10']);
        assert_same(1, (int) $st2->fetchColumn());
        $st2 = null;
        $st3 = $pdo->query('SELECT status, amount_before, amount_after FROM voucher_usages LIMIT 1')->fetch();
        assert_same('success', $st3['status']);
        assert_same(149000.0, (float) $st3['amount_before']);
        assert_same(134100.0, (float) $st3['amount_after']);
        $st3 = null;
        $pdo = null;

        // Bảng voucher admin phản ánh đơn áp dụng + giá
        $vlist3 = http_request('GET', $base . '/admin/vouchers', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains($orderCode, $vlist3['body'], 'chưa hiện đơn hàng áp dụng');
        assert_contains('134.100', $vlist3['body'], 'chưa hiện giá sau giảm');

        // Ngừng chạy voucher
        preg_match('#admin/vouchers/(\d+)/toggle#', $vlist3['body'], $vm);
        $vid = (int) ($vm[1] ?? 0);
        assert_true($vid > 0, 'không tìm thấy nút toggle voucher');
        $tg = http_request('POST', $base . '/admin/vouchers/' . $vid . '/toggle', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $vt,
        ]), false);
        assert_same(302, $tg['status']);
        $vlist4 = http_request('GET', $base . '/admin/vouchers', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('>Chạy<', $vlist4['body'], 'sau ngừng phải có nút Chạy');
    });

    $suite->test('[http] admin domains: hệ thống (default/toggle/add) + user (bảng/tạm dừng) + link dùng domain mặc định', function () use ($base, $dbFile): void {
        // Seed domain user cho user có sẵn
        $pdo = new PDO('sqlite:' . $dbFile);
        $stmt = $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1');
        $uid = (int) $stmt->fetchColumn();
        $stmt = null;
        assert_true($uid > 0);
        $pdo->prepare('INSERT INTO domains (user_id, domain, is_verified) VALUES (?,?,?)')->execute([$uid, 'link.user.test', 1]);
        $pdo = null;

        // Admin login
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        $list = http_request('GET', $base . '/admin/domains', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        if ($list['status'] !== 200) {
            file_put_contents('C:/Users/nguye/AppData/Local/Temp/opencode/dm_err.html', $list['body']);
        }
        assert_same(200, $list['status']);
        assert_contains('Domain Hệ Thống', $list['body']);
        assert_contains('Domain của Users', $list['body']);
        assert_contains('<th>Username</th>', $list['body']);
        assert_contains('<th>Ngày thêm</th>', $list['body']);
        assert_contains('<th>Số lượng</th>', $list['body']);
        assert_contains('<th>Trạng thái</th>', $list['body']);
        assert_contains('link.user.test', $list['body']);
        assert_contains('urlshortm.test', $list['body'], 'thiếu gợi ý Laragon');
        $dt = extract_csrf($list['body']);

        // Thêm domain hệ thống (tự thành mặc định vì chưa có)
        $add = http_request('POST', $base . '/admin/domains/system/add', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'domain' => 'urlshortm.test', 'csrf_token' => $dt,
        ]), false);
        assert_same(302, $add['status']);
        $list2 = http_request('GET', $base . '/admin/domains', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('Mặc định', $list2['body'], 'domain đầu tiên phải tự mặc định');

        // Tạm dừng domain user
        preg_match('#admin/domains/user/(\d+)/toggle#', $list2['body'], $um);
        $userDomainId = (int) ($um[1] ?? 0);
        assert_true($userDomainId > 0);
        $tg = http_request('POST', $base . '/admin/domains/user/' . $userDomainId . '/toggle', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'csrf_token' => $dt,
        ]), false);
        assert_same(302, $tg['status']);
        $list3 = http_request('GET', $base . '/admin/domains', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('Tạm dừng', $list3['body'], 'chưa tạm dừng domain user');

        // Link không có custom domain -> dùng domain hệ thống mặc định
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->exec('DELETE FROM rate_limits');
        $pdo = null;
        $userCookie = register_and_activate($base, $dbFile, 'Dom User', 'domuser@vidu.vn', 'matkhau123');
        $form = http_request('GET', $base . '/dashboard/link/new', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        $ft = extract_csrf($form['body']);
        $create = http_request('POST', $base . '/dashboard/link', $userCookie !== null ? ['Cookie: ' . $userCookie] : [], http_build_query([
            'link_type' => 'link', 'target' => 'https://example.com/dom-test', 'csrf_token' => $ft,
        ]), false);
        assert_same(302, $create['status']);
        $links = http_request('GET', $base . '/dashboard?tab=links', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        assert_contains('http://urlshortm.test/', $links['body'], 'link phải dùng domain hệ thống mặc định');
    });

    $suite->test('[http] cài đặt website: Thông tin website + tự nhận diện domain đang chạy', function () use ($base): void {
        // Admin login
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        // Menu con "Thông tin hệ thống" (chỉ xem)
        $page = http_request('GET', $base . '/admin/settings', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $page['status']);
        assert_contains('Thông tin hệ thống', $page['body']);
        assert_contains('Tên miền', $page['body']);
        assert_contains('Base URL hệ thống', $page['body']);
        assert_contains('Máy chủ', $page['body']);
        assert_contains('Database', $page['body']);
        assert_contains('Hoạt động', $page['body']);
        assert_contains('Chỉ xem', $page['body']);

        // Hệ thống luôn tự dùng host hiện tại làm base (không có cấu hình URL)
        $home = http_request('GET', $base . '/', [], null, false);
        $host = (string) parse_url($base, PHP_URL_HOST);
        assert_contains($host, $home['body'], 'hệ thống phải tự nhận diện host đang chạy');
    });

    $suite->test('[http] cài đặt website: 6 submenu + lưu SEO + inject head', function () use ($base): void {
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        // Thông tin hệ thống (read-only)
        $sys = http_request('GET', $base . '/admin/settings', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        if ($sys['status'] !== 200) {
            file_put_contents('C:/Users/nguye/AppData/Local/Temp/opencode/st_err.html', $sys['body']);
        }
        assert_same(200, $sys['status']);
        foreach (['Thông tin hệ thống', 'Tên miền', 'Base URL hệ thống', 'Máy chủ', 'Database', 'Hoạt động', 'Chỉ xem'] as $n) {
            assert_contains($n, $sys['body']);
        }

        // Thông tin website
        $web = http_request('GET', $base . '/admin/settings/website', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('Tên website', $web['body']);
        assert_contains('Giới thiệu website', $web['body']);
        assert_contains('Logo', $web['body']);
        $wt = extract_csrf($web['body']);

        // Hoá đơn + SMTP
        $inv = http_request('GET', $base . '/admin/settings/invoice', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('Loại doanh nghiệp', $inv['body']);
        assert_contains('Mã số thuế', $inv['body']);
        $smtp = http_request('GET', $base . '/admin/settings/smtp', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_contains('Máy chủ SMTP', $smtp['body']);
        assert_contains('smtp.gmail.com', $smtp['body']);

        // Media
        $med = http_request('GET', $base . '/admin/settings/media', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        foreach (['Định dạng ảnh', 'Nén ảnh', 'WebP', 'AVIF', 'Quản lý Media', 'Thumbnail (100x100)'] as $n) {
            assert_contains($n, $med['body']);
        }

        // SEO + lưu
        $seo = http_request('GET', $base . '/admin/settings/seo', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        foreach (['SEO Cơ bản', 'Site Title', 'Meta Description', 'og:site_name', 'Google Analytics 4', 'Custom Head Code', 'AI Meta', 'Verification'] as $n) {
            assert_contains($n, $seo['body']);
        }
        $st = extract_csrf($seo['body']);
        $save = http_request('POST', $base . '/admin/settings/seo/save', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'site_title' => 'UrlShortM — Rút gọn link', 'meta_description' => 'Mô tả SEO demo', 'meta_keywords' => 'url,shortener',
            'canonical_url' => '', 'robots_meta' => 'index, follow', 'og_title' => 'UrlShortM', 'og_description' => 'OG demo',
            'og_image' => '', 'og_type' => 'website', 'gsc' => '', 'bing' => '', 'yandex' => '', 'baidu' => '',
            'ga4' => 'G-DEMO123', 'gtm' => '', 'meta_pixel' => '', 'tiktok_pixel' => '', 'indexnow_key' => '',
            'sitemap_url' => '', 'robots_txt' => '', 'hreflang' => 'vi', 'head_code' => '<meta name="demo-custom" content="1">',
            'body_code' => '', 'footer_code' => '', 'ai_meta' => '1', 'csrf_token' => $st,
        ]), false);
        assert_same(302, $save['status']);

        // Head được inject ra trang công khai
        $home = http_request('GET', $base . '/', [], null, false);
        assert_contains('Mô tả SEO demo', $home['body'], 'chưa inject meta description');
        assert_contains('og:site_name', $home['body'], 'chưa inject og');
        assert_contains('G-DEMO123', $home['body'], 'chưa inject GA4');
        assert_contains('demo-custom', $home['body'], 'chưa inject custom head code');
        assert_contains('<html lang="vi"', $home['body'], 'chưa inject hreflang/lang');
    });

    $suite->test('[http] sitemap.xml + robots.txt tự tạo + SEO robots select/og value', function () use ($base): void {
        // sitemap.xml
        $sitemap = http_request('GET', $base . '/sitemap.xml');
        assert_same(200, $sitemap['status']);
        assert_contains('<?xml', $sitemap['body']);
        assert_contains('<urlset', $sitemap['body']);
        assert_contains('<loc>', $sitemap['body']);
        assert_contains('/tinh-nang', $sitemap['body']);

        // robots.txt
        $robots = http_request('GET', $base . '/robots.txt');
        assert_same(200, $robots['status']);
        assert_contains('User-agent: *', $robots['body']);
        assert_contains('Disallow: /admin', $robots['body']);
        assert_contains('Sitemap:', $robots['body']);
        assert_contains('sitemap.xml', $robots['body']);

        // Admin login -> SEO: robots select + og:url/og:site_name hiển thị giá trị
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);
        $seo = http_request('GET', $base . '/admin/settings/seo', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $seo['status']);
        assert_contains('Noindex / Nofollow', $seo['body'], 'thiếu lựa chọn robots noindex/nofollow');
        assert_contains('Noindex / Follow', $seo['body']);
        assert_contains('Index / Nofollow', $seo['body']);
        $host = (string) parse_url($base, PHP_URL_HOST);
        assert_contains('sitemap.xml', $seo['body'], 'thiếu link sitemap tự tạo');
        assert_contains('robots.txt', $seo['body'], 'thiếu link robots.txt tự tạo');
        assert_contains('Robots.txt nội dung', $seo['body'], 'thiếu trường nhập nội dung robots.txt');
        assert_contains('Disallow: /admin', $seo['body'], 'thiếu nội dung robots hệ thống điền sẵn');

        // Lưu robots = noindex, nofollow -> inject ra trang công khai
        $st = extract_csrf($seo['body']);
        $save = http_request('POST', $base . '/admin/settings/seo/save', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'site_title' => 'T', 'meta_description' => '', 'meta_keywords' => '', 'canonical_url' => '',
            'robots_meta' => 'noindex, nofollow', 'og_title' => '', 'og_description' => '', 'og_image' => '', 'og_type' => 'website',
            'gsc' => '', 'bing' => '', 'yandex' => '', 'baidu' => '', 'ga4' => '', 'gtm' => '', 'meta_pixel' => '',
            'tiktok_pixel' => '', 'indexnow_key' => '', 'hreflang' => '', 'head_code' => '', 'body_code' => '',
            'footer_code' => '', 'ai_meta' => '1', 'csrf_token' => $st,
        ]), false);
        assert_same(302, $save['status']);
        $home = http_request('GET', $base . '/', [], null, false);
        assert_contains('name="robots" content="noindex, nofollow"', $home['body'], 'chưa inject robots meta');
    });

    $suite->test('[http] SEO áp dụng toàn bộ thiết lập lên website', function () use ($base): void {
        // Admin login + lưu đầy đủ SEO
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        $seo = http_request('GET', $base . '/admin/settings/seo', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        $st = extract_csrf($seo['body']);
        $save = http_request('POST', $base . '/admin/settings/seo/save', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'site_title' => 'SEO TITLE ABC', 'meta_description' => 'SEO DESC ABC', 'meta_keywords' => 'url,short,seo',
            'canonical_url' => '', 'robots_meta' => 'index, follow', 'og_title' => 'OG TITLE ABC',
            'og_description' => 'OG DESC ABC', 'og_image' => 'https://img.example.com/og.png', 'og_type' => 'website',
            'gsc' => 'GSC-CODE', 'bing' => 'BING-CODE', 'yandex' => 'YANDEX-CODE', 'baidu' => 'BAIDU-CODE',
            'ga4' => 'G-GA4CODE', 'gtm' => 'GTM-GTMCODE', 'meta_pixel' => 'PIXEL123', 'tiktok_pixel' => 'TT123',
            'indexnow_key' => 'KEY123', 'robots_txt_content' => "User-agent: *\nDisallow: /abc", 'hreflang' => 'vi-VN',
            'head_code' => '<meta name="head-marker" content="1">', 'body_code' => '<div id="body-marker"></div>',
            'footer_code' => '<div id="footer-marker"></div>', 'ai_meta' => '1', 'csrf_token' => $st,
        ]), false);
        assert_same(302, $save['status']);

        $home = http_request('GET', $base . '/', [], null, false);
        $checks = [
            '<title>SEO TITLE ABC</title>' => 'site title',
            'name="description" content="SEO DESC ABC"' => 'meta description',
            'name="keywords" content="url,short,seo"' => 'keywords',
            'name="robots" content="index, follow"' => 'robots',
            'rel="canonical"' => 'canonical',
            'property="og:title" content="OG TITLE ABC"' => 'og:title',
            'property="og:description" content="OG DESC ABC"' => 'og:description',
            'property="og:image" content="https://img.example.com/og.png"' => 'og:image',
            'property="og:url"' => 'og:url',
            'property="og:site_name"' => 'og:site_name',
            'name="google-site-verification" content="GSC-CODE"' => 'gsc',
            'name="msvalidate.01" content="BING-CODE"' => 'bing',
            'name="yandex-verification" content="YANDEX-CODE"' => 'yandex',
            'name="baidu-site-verification" content="BAIDU-CODE"' => 'baidu',
            'gtag/js?id=G-GA4CODE' => 'ga4',
            'googletagmanager.com/gtm.js?id="+i+dl' => 'gtm head',
            'ns.html?id=GTM-GTMCODE' => 'gtm noscript',
            'connect.facebook.net/en_US/fbevents.js' => 'meta pixel',
            'analytics.tiktok.com/i18n/pixel/events.js' => 'tiktok pixel',
            'name="indexnow-key" content="KEY123"' => 'indexnow',
            'rel="sitemap"' => 'sitemap link',
            'name="head-marker"' => 'custom head',
            'id="body-marker"' => 'custom body',
            'id="footer-marker"' => 'custom footer',
            'rel="alternate" hreflang="vi-VN"' => 'hreflang',
            '<html lang="vi-VN"' => 'html lang',
        ];
        foreach ($checks as $needle => $label) {
            assert_contains($needle, $home['body'], 'chưa áp dụng: ' . $label);
        }
        assert_contains('SEO TITLE ABC', $home['body'], 'chưa áp dụng site title lên trang chủ');

        // robots.txt tuỳ chỉnh áp dụng
        $robots = http_request('GET', $base . '/robots.txt');
        assert_contains('Disallow: /abc', $robots['body'], 'chưa áp dụng nội dung robots tuỳ chỉnh');
    });

    $suite->test('[http] hoá đơn dùng thông tin cài đặt "Hoá đơn" (người bán)', function () use ($base, $dbFile): void {
        // Admin set thông tin hoá đơn
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);
        $invPage = http_request('GET', $base . '/admin/settings/invoice', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        $it = extract_csrf($invPage['body']);
        $invSave = http_request('POST', $base . '/admin/settings/invoice/save', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'invoice_name' => 'CTY BÁN HÀNG DEMO', 'invoice_tax_type' => 'business', 'invoice_address' => '456 Nguyễn Huệ, Q1',
            'invoice_phone' => '0911222333', 'invoice_tax_id' => '0311223344', 'csrf_token' => $it,
        ]), false);
        assert_same(302, $invSave['status']);

        // Reset rate + về mock + đăng ký user mua gói
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->exec('DELETE FROM rate_limits');
        $pdo->exec("DELETE FROM settings WHERE skey LIKE 'paypal_%'");
        $pdo = null;
        $userCookie = register_and_activate($base, $dbFile, 'Nguoi Mua Hoa Don', 'hoadonseller@vidu.vn', 'matkhau123');
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->prepare("UPDATE users SET invoice_name='Nguoi Mua', tax_id='0123456789', address='1 Le Loi', city='HCM' WHERE email='hoadonseller@vidu.vn'")->execute();
        $pdo = null;

        $ch = http_request('GET', $base . '/thanh-toan?plan=2', $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        $ct = extract_csrf($ch['body']);
        $pay = http_request('POST', $base . '/thanh-toan/pay', $userCookie !== null ? ['Cookie: ' . $userCookie] : [], http_build_query([
            'plan_id' => 2, 'csrf_token' => $ct,
        ]), false);
        preg_match('#order=(DH-[A-Z0-9]+)#', (string) ($pay['location'] ?? ''), $om);
        $orderCode = $om[1] ?? '';
        assert_true($orderCode !== '');
        $inv = http_request('GET', $base . '/hoa-don/' . $orderCode, $userCookie !== null ? ['Cookie: ' . $userCookie] : []);
        assert_same(200, $inv['status']);
        assert_contains('CTY BÁN HÀNG DEMO', $inv['body'], 'thiếu tên người bán từ cài đặt');
        assert_contains('0311223344', $inv['body'], 'thiếu MST người bán từ cài đặt');
        assert_contains('456 Nguyễn Huệ', $inv['body'], 'thiếu địa chỉ người bán từ cài đặt');
        assert_contains('0911222333', $inv['body'], 'thiếu điện thoại người bán từ cài đặt');
        assert_contains('(Doanh nghiệp)', $inv['body'], 'thiếu loại doanh nghiệp trên hoá đơn');
    });

    $suite->test('[http] email SMTP: trang có gửi thử + lỗi khi chưa cấu hình', function () use ($base): void {
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        // Trang SMTP có form "Gửi thử email"
        $smtp = http_request('GET', $base . '/admin/settings/smtp', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $smtp['status']);
        assert_contains('Gửi thử email', $smtp['body']);
        assert_contains('id="test-to"', $smtp['body']);
        assert_contains('id="test-subject"', $smtp['body']);
        assert_contains('id="test-body"', $smtp['body']);
        assert_contains('Chưa cấu hình', $smtp['body'], 'thiếu trạng thái chưa cấu hình SMTP');
        $st = extract_csrf($smtp['body']);

        // Gửi thử khi chưa cấu hình -> lỗi rõ ràng (không gọi mạng)
        $test = http_request('POST', $base . '/admin/settings/smtp/test', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'test_to' => 'admin@vidu.vn', 'test_subject' => 'Test', 'test_body' => 'Hello', 'csrf_token' => $st,
        ]), false);
        assert_same(302, $test['status']);
        $loc = (string) ($test['location'] ?? '');
        assert_contains('error=', $loc, 'gửi thử khi chưa cấu hình phải báo lỗi');
        assert_contains('Chưa cấu hình SMTP', urldecode($loc));
    });

    $suite->test('[http] email template: trang liệt kê 5 mẫu + preview + gửi thử', function () use ($base): void {
        $login = http_request('GET', $base . '/admin/dang-nhap');
        $ac = extract_cookie($login);
        $at = extract_csrf($login['body']);
        $ok = http_request('POST', $base . '/admin/dang-nhap', $ac !== null ? ['Cookie: ' . $ac] : [], http_build_query([
            'email' => 'admin@vidu.vn', 'password' => 'Admin@123', 'csrf_token' => $at,
        ]), false);
        $adminCookie = extract_cookie($ok);
        assert_true($adminCookie !== null);

        $em = http_request('GET', $base . '/admin/emails', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : []);
        assert_same(200, $em['status']);
        foreach (['Mua hàng thành công', 'Gửi hoá đơn cho khách', 'Thông báo đăng ký thành công', 'Thông báo lấy lại mật khẩu', 'Kích hoạt tài khoản'] as $n) {
            assert_contains($n, $em['body']);
        }
        assert_contains('admin-emails-data', $em['body'], 'thiếu data preview');
        assert_contains('Xem trước', $em['body']);
        assert_contains('Email Template', $em['body']);
        $et = extract_csrf($em['body']);

        // Gửi thử khi chưa cấu hình SMTP -> lỗi rõ
        $send = http_request('POST', $base . '/admin/emails/send', $adminCookie !== null ? ['Cookie: ' . $adminCookie] : [], http_build_query([
            'template' => 'purchase_success', 'test_to' => 'admin@vidu.vn', 'csrf_token' => $et,
        ]), false);
        assert_same(302, $send['status']);
        assert_contains('Chưa cấu hình SMTP', urldecode((string) ($send['location'] ?? '')));
    });

    $suite->test('[http] quên mật khẩu -> đặt lại qua token (30 phút)', function () use ($base, $dbFile): void {
        // Đăng ký + kích hoạt user
        $cookie = register_and_activate($base, $dbFile, 'Reset User', 'reset@vidu.vn', 'matkhau123');

        // Trang quên mật khẩu + gửi yêu cầu
        $forgot = http_request('GET', $base . '/quen-mat-khau');
        assert_same(200, $forgot['status']);
        assert_contains('Quên mật khẩu', $forgot['body']);
        $fc = extract_cookie($forgot);
        $ft = extract_csrf($forgot['body']);
        $req = http_request('POST', $base . '/quen-mat-khau', $fc !== null ? ['Cookie: ' . $fc] : [], http_build_query([
            'email' => 'reset@vidu.vn', 'csrf_token' => $ft,
        ]), false);
        assert_same(200, $req['status']);
        assert_contains('đã gửi liên kết', $req['body'], 'phải báo đã gửi liên kết');

        // Lấy reset token từ DB
        $pdo = new PDO('sqlite:' . $dbFile);
        $stmt = $pdo->prepare('SELECT reset_token FROM users WHERE email = ?');
        $stmt->execute(['reset@vidu.vn']);
        $rtoken = $stmt->fetchColumn();
        $stmt = null;
        $pdo = null;
        assert_true($rtoken !== false && $rtoken !== null, 'không có reset_token');

        // Token sai -> báo lỗi
        $bad = http_request('GET', $base . '/dat-lai-mat-khau?token=SAI', [], null, false);
        assert_same(400, $bad['status']);
        assert_contains('hết hạn', $bad['body']);

        // Mở trang đặt lại + đặt mật khẩu mới
        $resetPage = http_request('GET', $base . '/dat-lai-mat-khau?token=' . urlencode((string) $rtoken));
        assert_same(200, $resetPage['status']);
        assert_contains('Đặt lại mật khẩu', $resetPage['body']);
        $rc = extract_cookie($resetPage);
        $rt = extract_csrf($resetPage['body']);
        $do = http_request('POST', $base . '/dat-lai-mat-khau', $rc !== null ? ['Cookie: ' . $rc] : [], http_build_query([
            'token' => (string) $rtoken, 'password' => 'matkhauMoi789', 'password_confirm' => 'matkhauMoi789', 'csrf_token' => $rt,
        ]), false);
        assert_same(200, $do['status']);
        assert_contains('thành công', $do['body'], 'chưa báo đặt lại thành công');

        // Token đã dùng -> không dùng lại được
        $reuse = http_request('GET', $base . '/dat-lai-mat-khau?token=' . urlencode((string) $rtoken), [], null, false);
        assert_same(400, $reuse['status']);

        // Đăng nhập bằng mật khẩu mới
        $loginPage = http_request('GET', $base . '/dang-nhap');
        $lc = extract_cookie($loginPage);
        $lt = extract_csrf($loginPage['body']);
        $ok = http_request('POST', $base . '/dang-nhap', $lc !== null ? ['Cookie: ' . $lc] : [], http_build_query([
            'email' => 'reset@vidu.vn', 'password' => 'matkhauMoi789', 'csrf_token' => $lt,
        ]), false);
        assert_same(302, $ok['status']);
        assert_contains('/dashboard', (string) ($ok['location'] ?? ''));

        // Token hết hạn (30 phút) -> bị từ chối
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE email = ?')
            ->execute(['expiredtoken', date('Y-m-d H:i:s', strtotime('-1 hour')), 'reset@vidu.vn']);
        $pdo = null;
        $expired = http_request('GET', $base . '/dat-lai-mat-khau?token=expiredtoken', [], null, false);
        assert_same(400, $expired['status']);
        assert_contains('hết hạn', $expired['body']);
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
        is_active     INTEGER NOT NULL DEFAULT 1,
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
        activation_token TEXT NULL,
        activation_expires_at TEXT NULL,
        reset_token      TEXT NULL,
        reset_expires_at TEXT NULL,
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

    $pdo->exec('CREATE TABLE admins (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        email         TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        display_name  TEXT NULL,
        role          TEXT NOT NULL DEFAULT \'admin\',
        permissions   TEXT NULL,
        status        TEXT NOT NULL DEFAULT \'active\',
        last_login_at TEXT NULL,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at    TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    // Admin mặc định để smoke test đăng nhập.
    $pdo->prepare('INSERT INTO admins (email, password_hash, display_name, role) VALUES (?, ?, ?, ?)')
        ->execute(['admin@vidu.vn', password_hash('Admin@123', PASSWORD_DEFAULT), 'Quản trị hệ thống', 'super_admin']);

    $pdo->exec('CREATE TABLE plans (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        code          TEXT NOT NULL UNIQUE,
        name          TEXT NOT NULL,
        description   TEXT NULL,
        price         NUMERIC NOT NULL DEFAULT 0,
        price_monthly NUMERIC NULL,
        price_yearly  NUMERIC NULL,
        currency      TEXT NOT NULL DEFAULT \'VND\',
        billing_period TEXT NOT NULL DEFAULT \'monthly\',
        max_links     INTEGER NOT NULL DEFAULT 0,
        max_clicks    INTEGER NOT NULL DEFAULT 0,
        max_custom_domains INTEGER NOT NULL DEFAULT 0,
        max_pixels    INTEGER NOT NULL DEFAULT 0,
        max_users     INTEGER NOT NULL DEFAULT 1,
        has_analytics INTEGER NOT NULL DEFAULT 0,
        has_qr_code   INTEGER NOT NULL DEFAULT 0,
        has_password_protection INTEGER NOT NULL DEFAULT 0,
        has_link_expiration INTEGER NOT NULL DEFAULT 0,
        has_utm_builder INTEGER NOT NULL DEFAULT 0,
        has_api_access INTEGER NOT NULL DEFAULT 0,
        is_popular    INTEGER NOT NULL DEFAULT 0,
        features      TEXT NULL,
        is_active     INTEGER NOT NULL DEFAULT 1,
        sort_order    INTEGER NOT NULL DEFAULT 0,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->prepare('INSERT INTO plans (code, name, price, billing_period, max_links, max_clicks, max_custom_domains, max_pixels, max_users, is_popular, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,1,?), (?,?,?,?,?,?,?,?,?,?,1,?), (?,?,?,?,?,?,?,?,?,?,1,?), (?,?,?,?,?,?,?,?,?,?,1,?)')
        ->execute(['free', 'Miễn phí', 0, 'monthly', 20, 10000, 5, 5, 1, 0, 10, 'starter', 'Starter', 149000, 'monthly', 500, 50000, 3, 5, 1, 1, 20, 'pro', 'Pro', 399000, 'monthly', -1, -1, 10, -1, 3, 0, 30, 'team', 'Team', 899000, 'monthly', -1, -1, 20, -1, 10, 0, 40]);

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

    $pdo->exec('CREATE TABLE settings (
        skey       TEXT NOT NULL,
        svalue     TEXT NULL,
        updated_at TEXT NULL,
        PRIMARY KEY (skey)
    )');

    $pdo->exec('CREATE TABLE orders (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        order_code       TEXT NOT NULL UNIQUE,
        user_id          INTEGER NOT NULL,
        plan_id          INTEGER NOT NULL,
        plan_name        TEXT NOT NULL,
        billing_period   TEXT NOT NULL DEFAULT \'monthly\',
        amount           NUMERIC NOT NULL,
        currency         TEXT NOT NULL DEFAULT \'VND\',
        status           TEXT NOT NULL DEFAULT \'pending\',
        payment_method   TEXT NOT NULL DEFAULT \'paypal\',
        gateway_order_id TEXT NULL,
        payer            TEXT NULL,
        paid_at          TEXT NULL,
        created_at       TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE vouchers (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        code           TEXT NOT NULL UNIQUE,
        campaign_name  TEXT NULL,
        discount_type  TEXT NOT NULL DEFAULT \'percent\',
        discount_value NUMERIC NOT NULL DEFAULT 0,
        usage_limit    INTEGER NOT NULL DEFAULT 1,
        used_count     INTEGER NOT NULL DEFAULT 0,
        per_user       TEXT NOT NULL DEFAULT \'once\',
        starts_at      TEXT NULL,
        ends_at        TEXT NULL,
        note           TEXT NULL,
        is_active      INTEGER NOT NULL DEFAULT 1,
        created_at     TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at     TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE voucher_usages (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        voucher_id    INTEGER NOT NULL,
        order_id      INTEGER NULL,
        user_id       INTEGER NULL,
        status        TEXT NOT NULL DEFAULT \'success\',
        amount_before NUMERIC NOT NULL DEFAULT 0,
        amount_after  NUMERIC NOT NULL DEFAULT 0,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE media (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        filename      TEXT NOT NULL UNIQUE,
        original_name TEXT NOT NULL,
        path          TEXT NOT NULL,
        mime          TEXT NULL,
        size          INTEGER NOT NULL DEFAULT 0,
        created_at    TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE system_domains (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        domain     TEXT NOT NULL UNIQUE,
        is_default INTEGER NOT NULL DEFAULT 0,
        is_active  INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');

    $pdo->exec('CREATE TABLE demographic_snapshots (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        payload    TEXT NULL,
        fetched_at TEXT NOT NULL,
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

/**
 * Đăng ký (trả về trang "kiểm tra email") rồi kích hoạt tài khoản qua token trong DB.
 * Trả về session cookie sau kích hoạt (tự đăng nhập).
 */
function register_and_activate(string $base, string $dbFile, string $name, string $email, string $pass): string
{
    $page = http_request('GET', $base . '/dang-ky');
    $cookie = extract_cookie($page);
    $token = extract_csrf($page['body']);
    $reg = http_request('POST', $base . '/dang-ky', $cookie !== null ? ['Cookie: ' . $cookie] : [], http_build_query([
        'name' => $name, 'email' => $email, 'password' => $pass, 'password_confirm' => $pass, 'csrf_token' => $token,
    ]), false);
    if ($reg['status'] !== 200) {
        throw new RuntimeException('register failed: ' . $reg['status']);
    }

    $pdo = new PDO('sqlite:' . $dbFile);
    $stmt = $pdo->prepare('SELECT activation_token FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $act = $stmt->fetchColumn();
    $stmt = null;
    $pdo = null;
    if ($act === false || $act === null || $act === '') {
        throw new RuntimeException('không có activation_token');
    }

    $resp = http_request('GET', $base . '/kich-hoat?token=' . urlencode((string) $act), [], null, false);
    $cookie = extract_cookie($resp);
    if ($cookie === null) {
        throw new RuntimeException('kích hoạt thất bại');
    }

    return $cookie;
}

function extract_csrf(string $body): ?string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $m)) {
        return $m[1];
    }

    return null;
}

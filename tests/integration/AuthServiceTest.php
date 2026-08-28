<?php
declare(strict_types=1);

use App\Repository\RateLimitRepository;
use App\Repository\UserRepository;
use App\Service\AuthException;
use App\Service\AuthService;

return function (TestSuite $suite): void {
    $make = function () {
        $pdo = make_sqlite();
        return new AuthService(
            new UserRepository($pdo),
            new AlwaysAllowLimiter(new RateLimitRepository($pdo))
        );
    };

    $makeDeny = function () {
        $pdo = make_sqlite();
        return new AuthService(
            new UserRepository($pdo),
            new AlwaysDenyLimiter(new RateLimitRepository($pdo))
        );
    };

    // Kích hoạt user vừa đăng ký (PENDING) qua token.
    $activate = function (PDO $pdo, AuthService $svc, string $email): void {
        $stmt = $pdo->prepare('SELECT activation_token FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $token = $stmt->fetchColumn();
        $svc->activate((string) $token);
    };

    $suite->test('register tạo user PENDING + activation token, KHÔNG tự đăng nhập', function (): void {
        $_SESSION = [];
        $pdo = make_sqlite();
        $svc = new AuthService(
            new UserRepository($pdo),
            new AlwaysAllowLimiter(new RateLimitRepository($pdo))
        );
        $user = $svc->register('Ban@Vidu.Com', 'matkhau123', 'matkhau123', 'Minh Anh', '127.0.0.1');

        assert_same('ban@vidu.com', $user['email']);
        assert_same('Minh Anh', $user['display_name']);
        assert_false(isset($_SESSION['user_id']), 'register không được tự đăng nhập');

        $userRow = (new UserRepository($pdo))->findByEmail('ban@vidu.com');
        assert_same('pending', $userRow['status'], 'tài khoản mới phải PENDING');
        assert_true($userRow['activation_token'] !== null, 'phải có activation token');

        // mật khẩu lưu dạng hash, verify được
        assert_true(password_verify('matkhau123', $userRow['password_hash']));
        assert_false(str_contains($userRow['password_hash'], 'matkhau123'));
    });

    $suite->test('register từ chối email rác / thiếu', function () use ($make): void {
        $_SESSION = [];
        $svc = $make();
        $thrown = null;
        try {
            $svc->register('khong-phai-email', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_true($thrown !== null);
        assert_same(AuthException::INVALID_INPUT, $thrown->reason());

        $thrown = null;
        try {
            $svc->register('', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_true($thrown !== null);
    });

    $suite->test('register từ chối mật khẩu ngắn và không khớp', function () use ($make): void {
        $_SESSION = [];
        $svc = $make();

        $thrown = null;
        try {
            $svc->register('a@b.vn', 'ngan', 'ngan', '', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::INVALID_INPUT, $thrown->reason());

        $thrown = null;
        try {
            $svc->register('a@b.vn', 'matkhau123', 'khacnhau99', '', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::INVALID_INPUT, $thrown->reason());
    });

    $suite->test('register email trùng -> EMAIL_EXISTS', function () use ($make): void {
        $_SESSION = [];
        $svc = $make();
        $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        $_SESSION = [];

        $thrown = null;
        try {
            $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::EMAIL_EXISTS, $thrown->reason());
    });

    $suite->test('register bị rate limit -> RATE_LIMITED', function () use ($makeDeny): void {
        $_SESSION = [];
        $svc = $makeDeny();
        $thrown = null;
        try {
            $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::RATE_LIMITED, $thrown->reason());
    });

    $suite->test('login đúng -> user + session + last_login', function () use ($activate): void {
        $_SESSION = [];
        $pdo = make_sqlite();
        $svc = new AuthService(new UserRepository($pdo), new AlwaysAllowLimiter(new RateLimitRepository($pdo)));
        $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        $activate($pdo, $svc, 'a@b.vn');
        $_SESSION = [];

        $user = $svc->login('a@b.vn', 'matkhau123', '127.0.0.1');
        assert_same('a@b.vn', $user['email']);
        assert_same((int) $user['id'], (int) ($_SESSION['user_id'] ?? 0));
    });

    $suite->test('login từ chối tài khoản PENDING (chưa kích hoạt)', function () use ($make): void {
        $_SESSION = [];
        $svc = $make();
        $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        $_SESSION = [];

        $thrown = null;
        try {
            $svc->login('a@b.vn', 'matkhau123', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_true($thrown !== null);
        assert_same(AuthException::ACCOUNT_DISABLED, $thrown->reason());
        assert_false(isset($_SESSION['user_id']));
    });

    $suite->test('login sai mật khẩu / email lạ -> INVALID_CREDENTIALS', function () use ($make): void {
        $_SESSION = [];
        $svc = $make();
        $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        $_SESSION = [];

        $thrown = null;
        try {
            $svc->login('a@b.vn', 'saimatkhau', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::INVALID_CREDENTIALS, $thrown->reason());

        $thrown = null;
        try {
            $svc->login('khongton@tai.vn', 'matkhau123', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::INVALID_CREDENTIALS, $thrown->reason());
    });

    $suite->test('login bị rate limit -> RATE_LIMITED', function () use ($makeDeny): void {
        $_SESSION = [];
        $svc = $makeDeny();
        $thrown = null;
        try {
            $svc->login('a@b.vn', 'matkhau123', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::RATE_LIMITED, $thrown->reason());
    });

    $suite->test('logout xoá session', function () use ($activate): void {
        $_SESSION = [];
        $pdo = make_sqlite();
        $svc = new AuthService(new UserRepository($pdo), new AlwaysAllowLimiter(new RateLimitRepository($pdo)));
        $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        $activate($pdo, $svc, 'a@b.vn');
        assert_true(isset($_SESSION['user_id']));

        $svc->logout();
        assert_false(isset($_SESSION['user_id']));
    });

    $suite->test('register mặc định gắn gói Free khi có plan free', function (): void {
        $_SESSION = [];
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (code, name) VALUES (?,?)')->execute(['free', 'Miễn phí']);
        $freeId = (int) $pdo->lastInsertId();
        $svc = new AuthService(new UserRepository($pdo), new AlwaysAllowLimiter(new RateLimitRepository($pdo)));

        $user = $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        $uid = (int) $user['id'];

        $stmt = $pdo->prepare("SELECT plan_id, status FROM user_subscriptions WHERE user_id = ?");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        assert_true($row !== false, 'register phải tạo subscription');
        assert_same($freeId, (int) $row['plan_id']);
        assert_same('active', $row['status']);
    });

    $suite->test('register không gắn sub khi không có plan free', function () use ($make): void {
        $_SESSION = [];
        $pdo = make_sqlite();
        $svc = new AuthService(new UserRepository($pdo), new AlwaysAllowLimiter(new RateLimitRepository($pdo)));
        $user = $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_subscriptions WHERE user_id = ?');
        $stmt->execute([(int) $user['id']]);
        assert_same(0, (int) $stmt->fetchColumn());
    });

    $suite->test('đổi mật khẩu thành công -> login bằng mật khẩu mới', function () use ($activate): void {
        $_SESSION = [];
        $pdo = make_sqlite();
        $svc = new AuthService(new UserRepository($pdo), new AlwaysAllowLimiter(new RateLimitRepository($pdo)));
        $user = $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        $activate($pdo, $svc, 'a@b.vn');
        $_SESSION = [];

        $svc->changePassword((int) $user['id'], 'matkhau123', 'matkhauMoi456', 'matkhauMoi456');

        $thrown = null;
        try {
            $svc->login('a@b.vn', 'matkhau123', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::INVALID_CREDENTIALS, $thrown->reason());

        $_SESSION = [];
        $user2 = $svc->login('a@b.vn', 'matkhauMoi456', '127.0.0.1');
        assert_same('a@b.vn', $user2['email']);
    });

    $suite->test('đổi mật khẩu: sai mật khẩu cũ / ngắn / không khớp / giống cũ', function () use ($make): void {
        $_SESSION = [];
        $pdo = make_sqlite();
        $svc = new AuthService(new UserRepository($pdo), new AlwaysAllowLimiter(new RateLimitRepository($pdo)));
        $user = $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');

        foreach ([
            ['sai', 'matkhauMoi456', 'matkhauMoi456'],
            ['matkhau123', 'ngan', 'ngan'],
            ['matkhau123', 'matkhauMoi456', 'khacnhau999'],
            ['matkhau123', 'matkhau123', 'matkhau123'],
        ] as $case) {
            $thrown = null;
            try {
                $svc->changePassword((int) $user['id'], $case[0], $case[1], $case[2]);
            } catch (AuthException $e) {
                $thrown = $e;
            }
            assert_same(AuthException::INVALID_INPUT, $thrown->reason());
        }

        // Mật khẩu không bị đổi (vẫn là hash của mật khẩu cũ)
        $row = (new UserRepository($pdo))->findByEmail('a@b.vn');
        assert_true(password_verify('matkhau123', $row['password_hash']));
    });

    $suite->test('vô hiệu hoá (soft): status=disabled, login bị từ chối, dữ liệu giữ lại', function () use ($activate): void {
        $_SESSION = [];
        $pdo = make_sqlite();
        $svc = new AuthService(new UserRepository($pdo), new AlwaysAllowLimiter(new RateLimitRepository($pdo)));
        $user = $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        $activate($pdo, $svc, 'a@b.vn');
        $_SESSION = [];

        $svc->deactivate((int) $user['id'], 'matkhau123');

        $row = (new UserRepository($pdo))->findById((int) $user['id']);
        assert_same('disabled', $row['status']);

        $thrown = null;
        try {
            $svc->login('a@b.vn', 'matkhau123', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::ACCOUNT_DISABLED, $thrown->reason());
    });

    $suite->test('vô hiệu hoá sai mật khẩu -> INVALID_INPUT, không đổi status', function () use ($make): void {
        $_SESSION = [];
        $pdo = make_sqlite();
        $svc = new AuthService(new UserRepository($pdo), new AlwaysAllowLimiter(new RateLimitRepository($pdo)));
        $user = $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');

        $thrown = null;
        try {
            $svc->deactivate((int) $user['id'], 'saimatkhau');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::INVALID_INPUT, $thrown->reason());
        assert_same('pending', (new UserRepository($pdo))->findById((int) $user['id'])['status']);
    });
};

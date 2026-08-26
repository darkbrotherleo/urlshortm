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

    $suite->test('register tạo user, mật khẩu hash, tự đăng nhập', function (): void {
        $_SESSION = [];
        $pdo = make_sqlite();
        $svc = new AuthService(
            new UserRepository($pdo),
            new AlwaysAllowLimiter(new RateLimitRepository($pdo))
        );
        $user = $svc->register('Ban@Vidu.Com', 'matkhau123', 'matkhau123', 'Minh Anh', '127.0.0.1');

        assert_same('ban@vidu.com', $user['email']);
        assert_same('Minh Anh', $user['display_name']);
        assert_same((int) $user['id'], (int) ($_SESSION['user_id'] ?? 0));

        // mật khẩu lưu dạng hash, verify được
        $userRow = (new UserRepository($pdo))->findByEmail('ban@vidu.com');
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

    $suite->test('login đúng -> user + session + last_login', function () use ($make): void {
        $_SESSION = [];
        $svc = $make();
        $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        $_SESSION = [];

        $user = $svc->login('a@b.vn', 'matkhau123', '127.0.0.1');
        assert_same('a@b.vn', $user['email']);
        assert_same((int) $user['id'], (int) ($_SESSION['user_id'] ?? 0));
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

    $suite->test('logout xoá session', function () use ($make): void {
        $_SESSION = [];
        $svc = $make();
        $svc->register('a@b.vn', 'matkhau123', 'matkhau123', '', '127.0.0.1');
        assert_true(isset($_SESSION['user_id']));

        $svc->logout();
        assert_false(isset($_SESSION['user_id']));
    });
};

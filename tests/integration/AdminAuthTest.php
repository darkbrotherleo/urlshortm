<?php
declare(strict_types=1);

use App\Repository\AdminRepository;
use App\Repository\RateLimitRepository;
use App\Service\AdminAuthService;
use App\Service\AuthException;

return function (TestSuite $suite): void {
    $make = function () {
        $pdo = make_sqlite();
        return [
            'pdo' => $pdo,
            'svc' => new AdminAuthService(
                new AdminRepository($pdo),
                new AlwaysAllowLimiter(new RateLimitRepository($pdo))
            ),
        ];
    };

    $suite->test('admin login đúng -> session + last_login', function () use ($make): void {
        $_SESSION = [];
        $ctx = $make();
        $ctx['pdo']->prepare('INSERT INTO admins (email, password_hash, display_name, role) VALUES (?,?,?,?)')
            ->execute(['admin@vidu.vn', password_hash('Admin@123', PASSWORD_DEFAULT), 'Quản trị hệ thống', 'super_admin']);

        $admin = $ctx['svc']->login('ADMIN@vidu.vn', 'Admin@123', '127.0.0.1');
        assert_same('admin@vidu.vn', $admin['email']);
        assert_same('super_admin', $admin['role']);
        assert_same((int) $admin['id'], (int) ($_SESSION['admin_id'] ?? 0));
    });

    $suite->test('admin login sai mật khẩu / email lạ -> INVALID_CREDENTIALS', function () use ($make): void {
        $_SESSION = [];
        $ctx = $make();
        $ctx['pdo']->prepare('INSERT INTO admins (email, password_hash, display_name, role) VALUES (?,?,?,?)')
            ->execute(['admin@vidu.vn', password_hash('Admin@123', PASSWORD_DEFAULT), 'QTV', 'admin']);

        foreach ([['admin@vidu.vn', 'sai'], ['khongton@ai.vn', 'Admin@123']] as $case) {
            $thrown = null;
            try {
                $ctx['svc']->login($case[0], $case[1], '127.0.0.1');
            } catch (AuthException $e) {
                $thrown = $e;
            }
            assert_same(AuthException::INVALID_CREDENTIALS, $thrown->reason());
        }
    });

    $suite->test('admin login disabled -> ACCOUNT_DISABLED', function () use ($make): void {
        $_SESSION = [];
        $ctx = $make();
        $ctx['pdo']->prepare("INSERT INTO admins (email, password_hash, display_name, role, status) VALUES (?,?,?,?,'disabled')")
            ->execute(['admin@vidu.vn', password_hash('Admin@123', PASSWORD_DEFAULT), 'QTV', 'admin']);

        $thrown = null;
        try {
            $ctx['svc']->login('admin@vidu.vn', 'Admin@123', '127.0.0.1');
        } catch (AuthException $e) {
            $thrown = $e;
        }
        assert_same(AuthException::ACCOUNT_DISABLED, $thrown->reason());
    });

    $suite->test('admin logout xoá session', function () use ($make): void {
        $_SESSION = [];
        $ctx = $make();
        $ctx['pdo']->prepare('INSERT INTO admins (email, password_hash, display_name, role) VALUES (?,?,?,?)')
            ->execute(['admin@vidu.vn', password_hash('Admin@123', PASSWORD_DEFAULT), 'QTV', 'admin']);
        $ctx['svc']->login('admin@vidu.vn', 'Admin@123', '127.0.0.1');
        assert_true(isset($_SESSION['admin_id']));

        $ctx['svc']->logout();
        assert_false(isset($_SESSION['admin_id']));
    });

    $suite->test('AdminRepository round-trip + updateLastLogin', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO admins (email, password_hash, display_name, role) VALUES (?,?,?,?)')
            ->execute(['a@admin.vn', 'hash', 'A', 'admin']);
        $repo = new AdminRepository($pdo);

        $row = $repo->findByEmail('a@admin.vn');
        assert_same('a@admin.vn', $row['email']);
        assert_same('admin', $row['role']);
        assert_null($repo->findByEmail('nope@admin.vn'));

        $byId = $repo->findById((int) $row['id']);
        assert_same('A', $byId['display_name']);
        $repo->updateLastLogin((int) $row['id']);
        assert_true(true);
    });
};

<?php
declare(strict_types=1);

use App\Repository\UserRepository;

return function (TestSuite $suite): void {
    $suite->test('findAllForAdmin: user + gói (Miễn phí khi không có sub)', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (id, code, name) VALUES (1,?,?), (2,?,?)')->execute(['free', 'Miễn phí', 'starter', 'Starter']);
        $repo = new UserRepository($pdo);

        $repo->insert('a@v.vn', 'h', 'User A');
        $repo->insert('b@v.vn', 'h', 'User B');
        $uid = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, status, starts_at, ends_at) VALUES (?,?,?,?,?)")
            ->execute([$uid, 2, 'active', '2026-08-01 00:00:00', '2026-09-01 00:00:00']);

        $all = $repo->findAllForAdmin();
        assert_same(2, count($all));
        foreach ($all as $u) {
            if ($u['email'] === 'b@v.vn') {
                assert_same('Starter', $u['plan_name']);
                assert_same('2026-08-01 00:00:00', $u['starts_at']);
                assert_same('2026-09-01 00:00:00', $u['ends_at']);
            } else {
                assert_true($u['plan_name'] === null, 'user không sub phải null plan');
            }
        }
    });

    $suite->test('findUserForAdmin trả chi tiết hoặc null', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (id, code, name) VALUES (1,?,?)')->execute(['free', 'Miễn phí']);
        $repo = new UserRepository($pdo);
        $id = $repo->insert('a@v.vn', 'h', 'User A');

        $u = $repo->findUserForAdmin((int) $id);
        assert_same('a@v.vn', $u['email']);
        assert_null($repo->findUserForAdmin(99999));
    });

    $suite->test('setSubscription: cấp gói active + hạ về Miễn phí', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (id, code, name) VALUES (1,?,?), (2,?,?)')->execute(['free', 'Miễn phí', 'starter', 'Starter']);
        $repo = new UserRepository($pdo);
        $uid = $repo->insert('a@v.vn', 'h', 'A');

        $repo->setSubscription((int) $uid, 2, '2026-08-01 00:00:00', '2026-09-01 00:00:00');
        $u = $repo->findUserForAdmin((int) $uid);
        assert_same('Starter', $u['plan_name']);

        // Đổi gói khác trên cùng sub
        $repo->setSubscription((int) $uid, 2, '2026-08-10 00:00:00', '2026-10-01 00:00:00');
        $u2 = $repo->findUserForAdmin((int) $uid);
        assert_same('2026-10-01 00:00:00', $u2['ends_at']);

        // Hạ về Miễn phí -> sub cũ hết hạn
        $repo->setSubscription((int) $uid, null, null, null);
        $u3 = $repo->findUserForAdmin((int) $uid);
        assert_true($u3['plan_name'] === null, 'Miễn phí phải không có gói active');
    });

    $suite->test('setStatus + updateEmail', function (): void {
        $pdo = make_sqlite();
        $repo = new UserRepository($pdo);
        $uid = $repo->insert('a@v.vn', 'h', 'A');

        $repo->setStatus((int) $uid, 'disabled');
        assert_same('disabled', $repo->findById((int) $uid)['status']);

        $repo->updateEmail((int) $uid, 'b@v.vn');
        assert_same('b@v.vn', $repo->findById((int) $uid)['email']);
    });

    $suite->test('plansAll trả gói active', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (code, name, is_active, sort_order) VALUES (?,?,?,?), (?,?,?,?)')
            ->execute(['free', 'Miễn phí', 1, 1, 'pro', 'Pro', 1, 2]);
        $repo = new UserRepository($pdo);
        $plans = $repo->plansAll();
        assert_same(2, count($plans));
        assert_same('free', $plans[0]['code']);
    });
};

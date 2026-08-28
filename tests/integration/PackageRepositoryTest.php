<?php
declare(strict_types=1);

use App\Repository\PackageRepository;

return function (TestSuite $suite): void {
    $makeData = function (string $code, string $name): array {
        return [
            'code' => $code, 'name' => $name, 'description' => 'Mô tả ' . $name,
            'price' => 149000, 'currency' => 'VND', 'billing_period' => 'monthly',
            'max_links' => 500, 'max_clicks' => 50000, 'max_custom_domains' => 3,
            'max_pixels' => 5, 'max_users' => 1,
            'has_analytics' => 1, 'has_qr_code' => 1, 'has_password_protection' => 1,
            'has_link_expiration' => 1, 'has_utm_builder' => 1, 'has_api_access' => 0,
            'is_popular' => 1, 'is_active' => 1, 'sort_order' => 20,
            'features' => ['max_links' => 500, 'analytics' => true],
        ];
    };

    $suite->test('package create + findById + update round-trip', function () use ($makeData): void {
        $pdo = make_sqlite();
        $repo = new PackageRepository($pdo);

        $id = $repo->create($makeData('starter', 'Starter'));
        $p = $repo->findById($id);
        assert_same('starter', $p['code']);
        assert_same('Starter', $p['name']);
        assert_same(149000.0, (float) $p['price']);
        assert_same('VND', $p['currency']);
        assert_same(1, (int) $p['is_popular']);
        assert_same(500, (int) $p['max_links']);

        $data = $makeData('pro', 'Pro');
        $data['price'] = 399000;
        $data['max_links'] = -1;
        $repo->update($id, $data);
        $p2 = $repo->findById($id);
        assert_same('pro', $p2['code']);
        assert_same(399000.0, (float) $p2['price']);
        assert_same(-1, (int) $p2['max_links']);
    });

    $suite->test('package findAll/count theo search + findByCode', function () use ($makeData): void {
        $pdo = make_sqlite();
        $repo = new PackageRepository($pdo);
        $repo->create($makeData('free', 'Miễn phí'));
        $repo->create($makeData('starter', 'Starter'));

        assert_same(2, $repo->countAll());
        assert_same(1, $repo->countAll('Starter'));
        $rows = $repo->findAll('starter', 20, 0);
        assert_same(1, count($rows));
        assert_same('starter', $rows[0]['code']);

        assert_same('free', $repo->findByCode('free')['code']);
        assert_null($repo->findByCode('nope'));
    });

    $suite->test('toggle đổi is_active', function () use ($makeData): void {
        $pdo = make_sqlite();
        $repo = new PackageRepository($pdo);
        $id = $repo->create($makeData('x', 'X'));
        assert_same(1, (int) $repo->findById($id)['is_active']);
        $repo->toggle($id);
        assert_same(0, (int) $repo->findById($id)['is_active']);
        $repo->toggle($id);
        assert_same(1, (int) $repo->findById($id)['is_active']);
    });

    $suite->test('delete bị chặn khi có user đang dùng gói', function () use ($makeData): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO plans (code, name) VALUES (?,?)')->execute(['free', 'Miễn phí']);
        $planId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO user_subscriptions (user_id, plan_id, status) VALUES (?,?,'active')")->execute([$uid, $planId]);

        $repo = new PackageRepository($pdo);
        assert_same(1, $repo->activeSubscriptionCount($planId));

        $repo->delete($planId);
        assert_null($repo->findById($planId), 'admin phải tự kiểm tra activeSubscriptionCount trước khi xoá');
    });
};

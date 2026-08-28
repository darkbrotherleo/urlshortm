<?php
declare(strict_types=1);

use App\Repository\VoucherRepository;
use App\Service\VoucherService;

return function (TestSuite $suite): void {
    $suite->test('VoucherRepository: create/findByCode/findAll/toggle', function (): void {
        $pdo = make_sqlite();
        $repo = new VoucherRepository($pdo);
        $id = $repo->create([
            'code' => 'GIAM10', 'campaign_name' => 'KM', 'discount_type' => 'percent',
            'discount_value' => 10, 'usage_limit' => 5, 'per_user' => 'once',
            'starts_at' => null, 'ends_at' => null, 'note' => '', 'is_active' => 1,
        ]);
        $v = $repo->findByCode('GIAM10');
        assert_same($id, (int) $v['id']);
        assert_same(10.0, (float) $v['discount_value']);
        assert_same(1, count($repo->findAll(null, 20, 0)));
        $repo->toggle($id);
        assert_same(0, (int) $repo->findById($id)['is_active']);
    });

    $suite->test('VoucherService: redeem hợp lệ + giảm %/tiền + chặn các trường hợp sai', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();
        $repo = new VoucherRepository($pdo);
        $svc = new VoucherService($repo);

        // 10% của 149000 = 14900
        $repo->create(['code' => 'GIAM10', 'campaign_name' => 'C', 'discount_type' => 'percent', 'discount_value' => 10, 'usage_limit' => 2, 'per_user' => 'once', 'starts_at' => null, 'ends_at' => null, 'note' => '', 'is_active' => 1]);
        $r = $svc->redeem('GIAM10', $uid, 149000.0);
        assert_same(14900.0, $r['discount']);
        assert_same(134100.0, $r['amount_after']);

        // Fixed 50000
        $repo->create(['code' => 'GIAM50K', 'campaign_name' => 'C2', 'discount_type' => 'fixed', 'discount_value' => 50000, 'usage_limit' => 2, 'per_user' => 'once', 'starts_at' => null, 'ends_at' => null, 'note' => '', 'is_active' => 1]);
        $r2 = $svc->redeem('GIAM50K', $uid, 149000.0);
        assert_same(50000.0, $r2['discount']);
        assert_same(99000.0, $r2['amount_after']);

        // Không tồn tại
        $thrown = null;
        try { $svc->redeem('KHONGCÓ', $uid, 1000.0); } catch (\RuntimeException $e) { $thrown = $e; }
        assert_true($thrown !== null);

        // Hết hạn
        $repo->create(['code' => 'HETHAN', 'campaign_name' => 'C', 'discount_type' => 'percent', 'discount_value' => 10, 'usage_limit' => 2, 'per_user' => 'once', 'starts_at' => null, 'ends_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'note' => '', 'is_active' => 1]);
        $thrown = null;
        try { $svc->redeem('HETHAN', $uid, 1000.0); } catch (\RuntimeException $e) { $thrown = $e; }
        assert_true($thrown !== null);
    });

    $suite->test('VoucherService: giới hạn lượt dùng + 1 user 1 lần', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();
        $repo = new VoucherRepository($pdo);
        $svc = new VoucherService($repo);
        $repo->create(['code' => 'LIMIT1', 'campaign_name' => 'C', 'discount_type' => 'percent', 'discount_value' => 10, 'usage_limit' => 1, 'per_user' => 'once', 'starts_at' => null, 'ends_at' => null, 'note' => '', 'is_active' => 1]);
        $v = $repo->findByCode('LIMIT1');

        // dùng hết lượt
        $repo->incrementUsed((int) $v['id']);
        $thrown = null;
        try { $svc->redeem('LIMIT1', $uid, 1000.0); } catch (\RuntimeException $e) { $thrown = $e; }
        assert_true($thrown !== null, 'hết lượt phải chặn');

        // 1 user 1 lần: user đã dùng -> chặn
        $repo->create(['code' => 'ONCE', 'campaign_name' => 'C', 'discount_type' => 'fixed', 'discount_value' => 1000, 'usage_limit' => 10, 'per_user' => 'once', 'starts_at' => null, 'ends_at' => null, 'note' => '', 'is_active' => 1]);
        $once = $repo->findByCode('ONCE');
        $repo->recordUsage((int) $once['id'], 1, $uid, 'success', 1000, 0);
        $thrown = null;
        try { $svc->redeem('ONCE', $uid, 1000.0); } catch (\RuntimeException $e) { $thrown = $e; }
        assert_true($thrown !== null, '1 user 1 lần phải chặn khi đã dùng');
    });

    $suite->test('VoucherService: consume ghi nhận + tăng used_count', function (): void {
        $pdo = make_sqlite();
        $repo = new VoucherRepository($pdo);
        $repo->create(['code' => 'GOOD', 'campaign_name' => 'C', 'discount_type' => 'fixed', 'discount_value' => 5000, 'usage_limit' => 10, 'per_user' => 'once', 'starts_at' => null, 'ends_at' => null, 'note' => '', 'is_active' => 1]);
        $v = $repo->findByCode('GOOD');
        $svc = new VoucherService($repo);
        $svc->consume($v, 7, 99, 149000.0, 144000.0);

        assert_same(1, (int) $repo->findById((int) $v['id'])['used_count']);
        $all = $repo->findAll(null, 20, 0);
        assert_same('success', $all[0]['last_status']);
        assert_same(149000.0, (float) $all[0]['last_before']);
        assert_same(144000.0, (float) $all[0]['last_after']);
    });
};

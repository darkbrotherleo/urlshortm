<?php
declare(strict_types=1);

use App\Repository\OrderRepository;
use App\Service\PayPalService;

return function (TestSuite $suite): void {
    $fakePaypal = static function (string $url, string $method = 'POST', string $body = '', string $auth = ''): string {
        if (str_contains($url, '/v1/oauth2/token')) {
            return '{"access_token":"tok123"}';
        }
        if (str_contains($url, '/capture')) {
            return '{"status":"COMPLETED","payer":{"email_address":"buyer@example.com"}}';
        }

        return '{"id":"PAYPAL-9X8","status":"CREATED","links":[{"rel":"approve","href":"https://paypal.example/approve/9X8"}]}';
    };

    $suite->test('PayPalService: isConfigured theo client_id/secret', function (): void {
        assert_false((new PayPalService('', '', 'sandbox'))->isConfigured());
        assert_false((new PayPalService('cid', '', 'sandbox'))->isConfigured());
        assert_true((new PayPalService('cid', 'sec', 'sandbox'))->isConfigured());
    });

    $suite->test('PayPalService: createOrder trả id + approve_url (fake http)', function () use ($fakePaypal): void {
        $svc = new PayPalService('cid', 'sec', 'sandbox', $fakePaypal);
        $r = $svc->createOrder(149000.0, 'VND', 'https://x/ok', 'https://x/cancel');
        assert_same('PAYPAL-9X8', $r['id']);
        assert_contains('approve/9X8', $r['approve_url']);
    });

    $suite->test('PayPalService: captureOrder COMPLETED + payer', function () use ($fakePaypal): void {
        $svc = new PayPalService('cid', 'sec', 'sandbox', $fakePaypal);
        $r = $svc->captureOrder('PAYPAL-9X8');
        assert_same('COMPLETED', $r['status']);
        assert_contains('buyer@example.com', (string) $r['payer']);
    });

    $suite->test('PayPalService: không cấu hình -> không gọi mạng', function (): void {
        $called = false;
        $svc = new PayPalService('', '', 'sandbox', function () use (&$called) { $called = true; return ''; });
        assert_false($svc->isConfigured());
        assert_false($called);
    });

    $suite->test('OrderRepository: create -> findByCode -> markPaid -> user lookup', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();

        $repo = new OrderRepository($pdo);
        $id = $repo->create($uid, 2, 'Starter', 'monthly', 149000.0, 'VND');
        $order = $repo->findById($id);
        assert_same('pending', $order['status']);
        assert_same('Starter', $order['plan_name']);
        assert_matches('/^DH-[A-Z0-9]{8}$/', (string) $order['order_code']);

        $code = $order['order_code'];
        assert_same($id, (int) $repo->findByUserAndCode($uid, $code)['id']);
        assert_null($repo->findByUserAndCode($uid, 'KHONG-CO'));

        $repo->setGateway($id, 'PAYPAL-1');
        $repo->markPaid($id, 'buyer@x');
        $paid = $repo->findByCode($code);
        assert_same('paid', $paid['status']);
        assert_same('PAYPAL-1', $paid['gateway_order_id']);
        assert_true($paid['paid_at'] !== null);

        $repo->markStatus($id, 'canceled');
        assert_same('canceled', $repo->findById($id)['status']);
    });

    $suite->test('SettingRepository: set/get upsert driver-aware', function (): void {
        $pdo = make_sqlite();
        $repo = new \App\Repository\SettingRepository($pdo);
        $repo->set('paypal_client_id', 'cid');
        assert_same('cid', $repo->get('paypal_client_id'));
        $repo->set('paypal_client_id', 'cid2');
        assert_same('cid2', $repo->get('paypal_client_id'));
        assert_null($repo->get('none'));
    });

    $suite->test('OrderRepository: findAllForAdmin filter theo status/search + đếm', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['a@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();
        $repo = new OrderRepository($pdo);
        $id1 = $repo->create($uid, 2, 'Starter', 'monthly', 149000.0, 'VND');
        $id2 = $repo->create($uid, 3, 'Pro', 'monthly', 399000.0, 'VND');
        $repo->markPaid($id1, 'payer@x');

        $all = $repo->findAllForAdmin(null, null, 20, 0);
        assert_same(2, count($all));
        assert_same('a@v.vn', $all[0]['user_email']);

        $paid = $repo->findAllForAdmin(null, 'paid', 20, 0);
        assert_same(1, count($paid));
        assert_same('paid', $paid[0]['status']);

        $search = $repo->findAllForAdmin('a@v.vn', null, 20, 0);
        assert_same(2, count($search));

        assert_same(2, $repo->countAllForAdmin(null, null));
        assert_same(1, $repo->countAllForAdmin(null, 'paid'));
        assert_same(0, $repo->countAllForAdmin(null, 'failed'));
    });
};

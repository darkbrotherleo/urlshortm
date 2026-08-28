<?php
declare(strict_types=1);

use App\Repository\DomainRepository;

return function (TestSuite $suite): void {
    $suite->test('system domains: add/đặt mặc định/toggle/delete + systemDefault', function (): void {
        $pdo = make_sqlite();
        $repo = new DomainRepository($pdo);

        $repo->addSystemDomain('urlshortm.test');
        $repo->addSystemDomain('link.congty.com');
        assert_same(2, count($repo->findSystemDomains()));

        // Domain đầu tiên tự thành mặc định
        $default = $repo->systemDefault();
        assert_same('urlshortm.test', $default['domain']);

        // Đặt mặc định domain khác
        $rows = $repo->findSystemDomains();
        $linkId = 0;
        foreach ($rows as $r) {
            if ($r['domain'] === 'link.congty.com') {
                $linkId = (int) $r['id'];
            }
        }
        $repo->setSystemDefault($linkId);
        assert_same('link.congty.com', $repo->systemDefault()['domain']);

        // Thêm trùng -> bỏ qua
        $repo->addSystemDomain('urlshortm.test');
        assert_same(2, count($repo->findSystemDomains()));

        $repo->toggleSystemActive($linkId);
        assert_null($repo->systemDefault(), 'domain tắt không được là mặc định');

        $repo->deleteSystemDomain($linkId);
        assert_same(1, count($repo->findSystemDomains()));
    });

    $suite->test('admin user domains: findAllForAdmin/count + toggle + delete + countActiveByUser', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO users (email, password_hash, display_name) VALUES (?,?,?)')->execute(['u@v.vn', 'h', 'User One']);
        $uid = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO domains (user_id, domain, is_verified) VALUES (?,?,?)')->execute([$uid, 'a.test', 1]);
        $pdo->prepare('INSERT INTO domains (user_id, domain, is_verified) VALUES (?,?,?)')->execute([$uid, 'b.test', 0]);

        $repo = new DomainRepository($pdo);
        $all = $repo->findAllForAdmin(null, 20, 0);
        assert_same(2, count($all));
        assert_same('User One', $all[0]['username']);
        assert_same(2, $repo->countAllForAdmin(null));
        assert_same(1, $repo->countAllForAdmin('b.test'));
        assert_same(2, $repo->countActiveByUser($uid));

        $repo->toggleUserActive((int) $all[0]['id']);
        assert_same(1, $repo->countActiveByUser($uid));

        $repo->deleteAny((int) $all[1]['id']);
        assert_same(1, $repo->countAllForAdmin(null));
    });
};

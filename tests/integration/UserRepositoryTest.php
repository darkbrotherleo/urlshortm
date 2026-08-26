<?php
declare(strict_types=1);

use App\Repository\UserRepository;

return function (TestSuite $suite): void {
    $suite->test('insert + findByEmail round-trip', function (): void {
        $repo = new UserRepository(make_sqlite());
        $id = $repo->insert('ban@vidu.com', 'hash-abc', 'Minh Anh');

        $user = $repo->findByEmail('ban@vidu.com');
        assert_true($user !== null);
        assert_same('ban@vidu.com', $user['email']);
        assert_same('hash-abc', $user['password_hash']);
        assert_same('Minh Anh', $user['display_name']);
        assert_same('active', $user['status']);
        assert_true($id > 0);
    });

    $suite->test('findByEmail chuẩn hoá chữ thường không áp dụng (truy vấn nguyên bản)', function (): void {
        $repo = new UserRepository(make_sqlite());
        $repo->insert('Ban@Vidu.COM', 'hash', null);
        assert_true($repo->findByEmail('Ban@Vidu.COM') !== null);
        assert_null($repo->findByEmail('ban@vidu.com'));
    });

    $suite->test('findById trả user hoặc null', function (): void {
        $repo = new UserRepository(make_sqlite());
        $id = $repo->insert('a@b.vn', 'hash', 'A');
        $u = $repo->findById((int) $id);
        assert_same('a@b.vn', $u['email']);
        assert_null($repo->findById(99999));
    });

    $suite->test('insert trùng email -> PDOException', function (): void {
        $repo = new UserRepository(make_sqlite());
        $repo->insert('ban@vidu.com', 'hash', null);
        $thrown = false;
        try {
            $repo->insert('ban@vidu.com', 'hash2', null);
        } catch (PDOException) {
            $thrown = true;
        }
        assert_true($thrown);
    });

    $suite->test('display_name rỗng -> NULL', function (): void {
        $repo = new UserRepository(make_sqlite());
        $id = $repo->insert('x@y.vn', 'hash', '  ');
        $u = $repo->findById((int) $id);
        assert_null($u['display_name']);
    });

    $suite->test('updateLastLogin không lỗi', function (): void {
        $repo = new UserRepository(make_sqlite());
        $id = $repo->insert('x@y.vn', 'hash', null);
        $repo->updateLastLogin((int) $id);
        assert_true(true);
    });
};

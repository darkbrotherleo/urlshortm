<?php
declare(strict_types=1);

use App\Repository\UrlRepository;

return function (TestSuite $suite): void {
    $suite->test('insert + findBySlug round-trip', function (): void {
        $repo = new UrlRepository(make_sqlite());
        $repo->insert('Ab3x9Q', 'https://example.com/a/b');

        $row = $repo->findBySlug('Ab3x9Q');
        assert_true($row !== null);
        assert_same('Ab3x9Q', $row['slug']);
        assert_same('https://example.com/a/b', $row['target_url']);
        assert_same(0, (int) $row['click_count']);
    });

    $suite->test('findBySlug không tồn tại -> null', function (): void {
        $repo = new UrlRepository(make_sqlite());
        assert_null($repo->findBySlug('zzzzzz'));
    });

    $suite->test('insert trùng slug -> PDOException', function (): void {
        $repo = new UrlRepository(make_sqlite());
        $repo->insert('Ab3x9Q', 'https://example.com/1');
        $thrown = false;
        try {
            $repo->insert('Ab3x9Q', 'https://example.com/2');
        } catch (PDOException) {
            $thrown = true;
        }
        assert_true($thrown);
    });

    $suite->test('incrementClicks tăng đúng một mỗi lần', function (): void {
        $repo = new UrlRepository(make_sqlite());
        $repo->insert('Ab3x9Q', 'https://example.com/a');

        $repo->incrementClicks('Ab3x9Q');
        $repo->incrementClicks('Ab3x9Q');
        $repo->incrementClicks('Ab3x9Q');

        $row = $repo->findBySlug('Ab3x9Q');
        assert_same(3, (int) $row['click_count']);
    });

    $suite->test('incrementClicks slug lạ không ảnh hưởng', function (): void {
        $repo = new UrlRepository(make_sqlite());
        $repo->insert('Ab3x9Q', 'https://example.com/a');
        $repo->incrementClicks('zzzzzz');
        $row = $repo->findBySlug('Ab3x9Q');
        assert_same(0, (int) $row['click_count']);
    });

    $suite->test('admin links: findAllForAdmin/count + tìm theo slug/email', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO users (email, password_hash, display_name) VALUES (?,?,?)')->execute(['u@v.vn', 'h', 'User One']);
        $uid = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO short_links (slug, target_url, user_id, click_count) VALUES (?,?,?,?)')->execute(['aaaaaa', 'https://a.com', $uid, 5]);
        $pdo->prepare('INSERT INTO short_links (slug, target_url, user_id) VALUES (?,?,?)')->execute(['bbbbbb', 'https://b.com', null]);

        $repo = new UrlRepository($pdo);
        $all = $repo->findAllForAdmin(null, 20, 0);
        assert_same(2, count($all));
        assert_same('User One', $all[1]['username']);
        assert_true($all[0]['user_id'] === null);

        assert_same(1, $repo->countAllForAdmin('User One'));
        assert_same(2, $repo->countAllForAdmin(null));
    });

    $suite->test('cleanupGuestLinks: xoá link khách cũ không chỉnh sửa, giữ link mới/user', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?,?)')->execute(['u@v.vn', 'h']);
        $uid = (int) $pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO short_links (slug, target_url, user_id, created_at) VALUES (?,?,?,?)');
        $ins->execute(['aaaaaa', 'https://a.com', null, date('Y-m-d H:i:s', strtotime('-20 days'))]);
        $ins->execute(['bbbbbb', 'https://b.com', null, date('Y-m-d H:i:s')]);
        $ins->execute(['cccccc', 'https://c.com', $uid, date('Y-m-d H:i:s', strtotime('-20 days'))]);

        $repo = new UrlRepository($pdo);
        $deleted = $repo->cleanupGuestLinks(15);
        assert_same(1, $deleted, 'chỉ xoá link khách cũ');
        assert_true($repo->findBySlug('aaaaaa') === null);
        assert_true($repo->findBySlug('bbbbbb') !== null);
        assert_true($repo->findBySlug('cccccc') !== null);
    });

    $suite->test('admin links: toggleActive + updateByAdmin', function (): void {
        $pdo = make_sqlite();
        $pdo->prepare('INSERT INTO short_links (slug, target_url) VALUES (?,?)')->execute(['aaaaaa', 'https://a.com']);
        $repo = new UrlRepository($pdo);

        $repo->toggleActive(1);
        assert_same(0, (int) $repo->findBySlug('aaaaaa')['is_active']);

        $repo->updateByAdmin(1, ['target_url' => 'https://new.com', 'title' => 'T', 'ends_at' => '2026-12-31 23:59:59', 'is_active' => 1]);
        $row = $repo->findBySlug('aaaaaa');
        assert_same('https://new.com', $row['target_url']);
        assert_same('T', $row['title']);
        assert_same(1, (int) $row['is_active']);
    });
};

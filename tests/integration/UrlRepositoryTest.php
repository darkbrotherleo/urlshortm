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
};

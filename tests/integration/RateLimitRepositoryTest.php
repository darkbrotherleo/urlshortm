<?php
declare(strict_types=1);

use App\Repository\RateLimitRepository;

return function (TestSuite $suite): void {
    $suite->test('increment cùng bucket tăng count', function (): void {
        $repo = new RateLimitRepository(make_sqlite());
        $hash = hash('sha256', '127.0.0.1');

        $c1 = $repo->increment($hash, 'shorten', 1000);
        $c2 = $repo->increment($hash, 'shorten', 1000);

        assert_same(1, $c1);
        assert_same(2, $c2);
    });

    $suite->test('bucket khác (window/ip/key) tách biệt', function (): void {
        $repo = new RateLimitRepository(make_sqlite());
        $h1 = hash('sha256', '1.1.1.1');
        $h2 = hash('sha256', '2.2.2.2');

        assert_same(1, $repo->increment($h1, 'shorten', 1000));
        assert_same(1, $repo->increment($h1, 'shorten', 2000));
        assert_same(1, $repo->increment($h1, 'other', 1000));
        assert_same(1, $repo->increment($h2, 'shorten', 1000));
    });
};

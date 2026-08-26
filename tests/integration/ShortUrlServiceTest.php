<?php
declare(strict_types=1);

use App\Repository\RateLimitRepository;
use App\Repository\UrlRepository;
use App\Security\RateLimiter;
use App\Security\SlugGenerator;
use App\Security\UrlNormalizer;
use App\Service\RateLimitExceededException;
use App\Service\ShortUrlService;
use App\Service\UrlValidationException;

return function (TestSuite $suite): void {
    $suite->test('create sinh slug đúng và trả target', function (): void {
        $pdo = make_sqlite();
        $service = new ShortUrlService(
            new UrlRepository($pdo),
            new UrlNormalizer(),
            new SlugGenerator(),
            new AlwaysAllowLimiter(new RateLimitRepository($pdo))
        );

        $result = $service->create('https://example.com/a/b?c=d', '127.0.0.1');
        assert_matches('/^[0-9a-zA-Z]{6}$/', $result['slug']);
        assert_same('https://example.com/a/b?c=d', $result['target_url']);
        assert_same(0, $result['click_count']);
    });

    $suite->test('create tự thêm scheme khi thiếu', function (): void {
        $pdo = make_sqlite();
        $service = new ShortUrlService(
            new UrlRepository($pdo),
            new UrlNormalizer(),
            new SlugGenerator(),
            new AlwaysAllowLimiter(new RateLimitRepository($pdo))
        );

        $result = $service->create('example.com/x', '127.0.0.1');
        assert_same('https://example.com/x', $result['target_url']);
    });

    $suite->test('create từ chối URL sai', function (): void {
        $pdo = make_sqlite();
        $service = new ShortUrlService(
            new UrlRepository($pdo),
            new UrlNormalizer(),
            new SlugGenerator(),
            new AlwaysAllowLimiter(new RateLimitRepository($pdo))
        );

        $thrown = false;
        try {
            $service->create('javascript:alert(1)', '127.0.0.1');
        } catch (UrlValidationException) {
            $thrown = true;
        }
        assert_true($thrown);
    });

    $suite->test('create vượt rate limit -> RateLimitExceededException', function (): void {
        $pdo = make_sqlite();
        $service = new ShortUrlService(
            new UrlRepository($pdo),
            new UrlNormalizer(),
            new SlugGenerator(),
            new AlwaysDenyLimiter(new RateLimitRepository($pdo))
        );

        $thrown = false;
        try {
            $service->create('https://example.com/', '127.0.0.1');
        } catch (RateLimitExceededException) {
            $thrown = true;
        }
        assert_true($thrown);
    });

    $suite->test('resolve trả null khi không tồn tại', function (): void {
        $pdo = make_sqlite();
        $service = new ShortUrlService(
            new UrlRepository($pdo),
            new UrlNormalizer(),
            new SlugGenerator(),
            new AlwaysAllowLimiter(new RateLimitRepository($pdo))
        );

        assert_null($service->resolve('zzzzzz'));
    });

    $suite->test('resolve tăng click và trả click mới', function (): void {
        $pdo = make_sqlite();
        $service = new ShortUrlService(
            new UrlRepository($pdo),
            new UrlNormalizer(),
            new SlugGenerator(),
            new AlwaysAllowLimiter(new RateLimitRepository($pdo))
        );

        $created = $service->create('https://example.com/x', '127.0.0.1');
        $r1 = $service->resolve($created['slug']);
        $r2 = $service->resolve($created['slug']);

        assert_same(1, $r1['click_count']);
        assert_same(2, $r2['click_count']);
    });
};

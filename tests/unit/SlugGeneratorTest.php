<?php
declare(strict_types=1);

use App\Security\SlugGenerator;

return function (TestSuite $suite): void {
    $suite->test('slug có đúng độ dài và charset', function (): void {
        $g = new SlugGenerator();
        for ($i = 0; $i < 100; $i++) {
            $slug = $g->generate();
            assert_matches('/^[0-9a-zA-Z]{6}$/', $slug);
        }
    });

    $suite->test('slug độ dài tuỳ chỉnh', function (): void {
        $g = new SlugGenerator();
        assert_matches('/^[0-9a-zA-Z]{8}$/', $g->generate('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz', 8));
    });

    $suite->test('isValid nhận slug đúng và từ chối sai', function (): void {
        $g = new SlugGenerator();
        assert_true($g->isValid('Ab3x9Q'));
        assert_false($g->isValid('ab3x9'));
        assert_false($g->isValid('ab3x9Q-'));
        assert_false($g->isValid('ab3x9Q1'));
    });

    $suite->test('length < 1 thì ném lỗi', function (): void {
        $g = new SlugGenerator();
        $thrown = false;
        try {
            $g->generate('a', 0);
        } catch (InvalidArgumentException) {
            $thrown = true;
        }
        assert_true($thrown);
    });
};

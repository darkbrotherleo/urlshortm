<?php
declare(strict_types=1);

use App\Security\Csrf;

return function (TestSuite $suite): void {
    $suite->test('token sinh và lưu vào session', function (): void {
        $_SESSION = [];
        $c = new Csrf();
        $token = $c->token();
        assert_true(strlen($token) === 64);
        assert_same($token, $_SESSION['csrf_token']);
    });

    $suite->test('verify nhận token đúng, từ chối token sai', function (): void {
        $_SESSION = ['csrf_token' => 'known-token'];
        $c = new Csrf();
        assert_true($c->verify('known-token'));
        assert_false($c->verify('wrong'));
        assert_false($c->verify(null));
    });

    $suite->test('field trả input hidden đúng', function (): void {
        $_SESSION = [];
        $c = new Csrf();
        $field = $c->field();
        assert_contains('name="csrf_token"', $field);
        assert_contains($_SESSION['csrf_token'], $field);
    });
};

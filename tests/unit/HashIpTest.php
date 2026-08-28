<?php
declare(strict_types=1);

return function (TestSuite $suite): void {
    $suite->test('hash_ip: 64 hex, khác IP thô', function (): void {
        $h = \App\hash_ip('127.0.0.1');
        assert_matches('/^[0-9a-f]{64}$/', $h);
        assert_false($h === '127.0.0.1');
    });

    $suite->test('hash_ip: deterministic cùng input', function (): void {
        assert_same(\App\hash_ip('203.0.113.7'), \App\hash_ip('203.0.113.7'));
        assert_false(\App\hash_ip('203.0.113.7') === \App\hash_ip('203.0.113.8'));
    });
};

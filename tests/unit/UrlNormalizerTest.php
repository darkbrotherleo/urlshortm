<?php
declare(strict_types=1);

use App\Security\UrlNormalizer;

return function (TestSuite $suite): void {
    $suite->test('tự thêm https khi thiếu scheme', function (): void {
        $n = new UrlNormalizer();
        assert_same('https://example.com/', $n->normalize('example.com'));
        assert_same('https://example.com/duong-dan', $n->normalize('example.com/duong-dan'));
    });

    $suite->test('giữ nguyên http/https hợp lệ', function (): void {
        $n = new UrlNormalizer();
        assert_same('https://example.com/a/b?x=1#frag', $n->normalize('https://example.com/a/b?x=1#frag'));
        assert_same('http://example.com:8080/x', $n->normalize('http://example.com:8080/x'));
    });

    $suite->test('bỏ port mặc định', function (): void {
        $n = new UrlNormalizer();
        assert_same('https://example.com/', $n->normalize('https://example.com:443/'));
        assert_same('http://example.com/', $n->normalize('http://example.com:80/'));
    });

    $suite->test('từ chối scheme không phải http/https', function (): void {
        $n = new UrlNormalizer();
        assert_null($n->normalize('javascript:alert(1)'));
        assert_null($n->normalize('ftp://example.com/x'));
        assert_null($n->normalize('file:///etc/passwd'));
    });

    $suite->test('từ chối chuỗi rác / rỗng', function (): void {
        $n = new UrlNormalizer();
        assert_null($n->normalize(''));
        assert_null($n->normalize('   '));
        assert_null($n->normalize('không phải url'));
    });

    $suite->test('từ chối URL có credential (phishing)', function (): void {
        $n = new UrlNormalizer();
        assert_null($n->normalize('https://user:pass@example.com/'));
    });

    $suite->test('giữ nguyên ký tự Unicode trong path', function (): void {
        $n = new UrlNormalizer();
        $out = $n->normalize('https://example.com/đường-đẫn-tiếng-việt');
        assert_true($out !== null);
        assert_contains('đường-đẫn-tiếng-việt', $out);
    });

    $suite->test('từ chối URL quá dài', function (): void {
        $n = new UrlNormalizer();
        $long = 'https://example.com/' . str_repeat('a', 2100);
        assert_null($n->normalize($long));
    });

    $suite->test('host viết hoa được chuẩn hoá lowercase', function (): void {
        $n = new UrlNormalizer();
        assert_same('https://example.com/Path', $n->normalize('https://EXAMPLE.COM/Path'));
    });
};

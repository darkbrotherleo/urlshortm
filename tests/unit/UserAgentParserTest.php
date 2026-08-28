<?php
declare(strict_types=1);

use App\Tracking\UserAgentParser;

return function (TestSuite $suite): void {
    $p = new UserAgentParser();

    $suite->test('iPhone Safari -> mobile/Safari/iOS', function () use ($p): void {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $r = $p->parse($ua);
        assert_same('mobile', $r['device']);
        assert_same('Safari', $r['browser']);
        assert_same('iOS', $r['os']);
    });

    $suite->test('Android Chrome -> mobile/Chrome/Android', function () use ($p): void {
        $ua = 'Mozilla/5.0 (Linux; Android 13; SM-G998B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
        $r = $p->parse($ua);
        assert_same('mobile', $r['device']);
        assert_same('Chrome', $r['browser']);
        assert_same('Android', $r['os']);
    });

    $suite->test('iPad Safari -> tablet/Safari/iOS', function () use ($p): void {
        $ua = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $r = $p->parse($ua);
        assert_same('tablet', $r['device']);
        assert_same('Safari', $r['browser']);
        assert_same('iOS', $r['os']);
    });

    $suite->test('Windows Chrome -> desktop/Chrome/Windows', function () use ($p): void {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $r = $p->parse($ua);
        assert_same('desktop', $r['device']);
        assert_same('Chrome', $r['browser']);
        assert_same('Windows 10/11', $r['os']);
    });

    $suite->test('Windows Firefox -> desktop/Firefox/Windows', function () use ($p): void {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0';
        $r = $p->parse($ua);
        assert_same('desktop', $r['device']);
        assert_same('Firefox', $r['browser']);
        assert_same('Windows 10/11', $r['os']);
    });

    $suite->test('Edge Windows -> desktop/Edge', function () use ($p): void {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';
        $r = $p->parse($ua);
        assert_same('desktop', $r['device']);
        assert_same('Edge', $r['browser']);
    });

    $suite->test('Mac Chrome -> desktop/Chrome/macOS', function () use ($p): void {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $r = $p->parse($ua);
        assert_same('desktop', $r['device']);
        assert_same('Chrome', $r['browser']);
        assert_same('macOS', $r['os']);
    });
};

<?php
declare(strict_types=1);

return function (TestSuite $suite): void {
    $suite->test('append_utm: URL chưa query -> thêm ?utm…', function (): void {
        $url = \App\append_utm('https://example.com/x', [
            'utm_campaign' => 'thang9',
            'utm_medium'   => 'social',
            'utm_source'   => '',
            'utm_term'     => null,
        ]);
        assert_same('https://example.com/x?utm_campaign=thang9&utm_medium=social', $url);
    });

    $suite->test('append_utm: URL đã có query -> thêm &utm…', function (): void {
        $url = \App\append_utm('https://example.com/x?a=1', ['utm_campaign' => 'c']);
        assert_same('https://example.com/x?a=1&utm_campaign=c', $url);
    });

    $suite->test('append_utm: không phải http/https -> giữ nguyên', function (): void {
        assert_same('mailto:a@b.vn', \App\append_utm('mailto:a@b.vn', ['utm_campaign' => 'c']));
        assert_same('tel:+849123', \App\append_utm('tel:+849123', ['utm_source' => 'fb']));
    });

    $suite->test('append_utm: rỗng UTM -> giữ nguyên', function (): void {
        assert_same('https://example.com/', \App\append_utm('https://example.com/', []));
    });
};

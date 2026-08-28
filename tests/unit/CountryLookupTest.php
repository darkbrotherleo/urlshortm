<?php
declare(strict_types=1);

use App\Tracking\CountryLookup;

return function (TestSuite $suite): void {
    $lookup = new CountryLookup(dirname(__DIR__, 2) . '/data/geo/ip-country.csv');

    $suite->test('IP công khai -> mã quốc gia', function () use ($lookup): void {
        assert_same('US', $lookup->lookup('8.8.8.8'));
        assert_same('US', $lookup->lookup('1.1.1.1'));
        assert_same('VN', $lookup->lookup('113.160.10.10'));
        assert_same('CN', $lookup->lookup('14.100.0.1'));
    });

    $suite->test('IP private/local -> null', function () use ($lookup): void {
        assert_null($lookup->lookup('127.0.0.1'));
        assert_null($lookup->lookup('192.168.1.1'));
        assert_null($lookup->lookup('10.0.0.5'));
        assert_null($lookup->lookup('172.16.5.5'));
        assert_null($lookup->lookup('169.254.1.1'));
    });

    $suite->test('IP không hợp lệ -> null', function () use ($lookup): void {
        assert_null($lookup->lookup('khong-phai-ip'));
        assert_null($lookup->lookup(''));
    });

    $suite->test('IP không nằm trong dataset -> null', function () use ($lookup): void {
        assert_null($lookup->lookup('200.200.200.200'));
    });
};

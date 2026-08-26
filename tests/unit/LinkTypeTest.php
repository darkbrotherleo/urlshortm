<?php
declare(strict_types=1);

use App\Security\LinkType;
use App\Security\UrlNormalizer;
use App\Service\LinkValidationException;

return function (TestSuite $suite): void {
    $make = fn () => new LinkType(new UrlNormalizer());

    $suite->test('link dựng đích http(s) và tự thêm scheme', function () use ($make): void {
        $lt = $make();
        assert_same('https://example.com/a', $lt->build('link', 'example.com/a'));
        assert_same('https://example.com/', $lt->build('link', 'https://example.com'));
    });

    $suite->test('email -> mailto', function () use ($make): void {
        $lt = $make();
        assert_same('mailto:ban@vidu.com', $lt->build('email', 'Ban@Vidu.com'));
    });

    $suite->test('phone/whatsapp/sms/viber dùng số', function () use ($make): void {
        $lt = $make();
        assert_same('tel:+0912345678', $lt->build('phone', '0912 345 678'));
        assert_same('https://wa.me/84912345678', $lt->build('whatsapp', '84912345678'));
        assert_same('sms:+84912345678', $lt->build('sms', '+84912345678'));
        assert_same('viber://chat?number=%2B0912345678', $lt->build('viber', '0912345678'));
    });

    $suite->test('telegram/messenger/skype/line/wechat dùng handle', function () use ($make): void {
        $lt = $make();
        assert_same('https://t.me/username', $lt->build('telegram', '@UserName'));
        assert_same('https://m.me/abc.xyz', $lt->build('messenger', 'abc.xyz'));
        assert_same('skype:skypeuser?chat', $lt->build('skype', 'skypeuser'));
        assert_same('https://line.me/ti/p/lineid', $lt->build('line', 'lineid'));
        assert_same('weixin://dl/chat/wxid', $lt->build('wechat', 'wxid'));
    });

    $suite->test('từ chối input rỗng / sai', function () use ($make): void {
        $lt = $make();
        $thrown = false;
        try {
            $lt->build('link', '');
        } catch (LinkValidationException) {
            $thrown = true;
        }
        assert_true($thrown);

        $thrown = false;
        try {
            $lt->build('email', 'khong-phai-email');
        } catch (LinkValidationException) {
            $thrown = true;
        }
        assert_true($thrown);

        $thrown = false;
        try {
            $lt->build('whatsapp', 'khong-co-so');
        } catch (LinkValidationException) {
            $thrown = true;
        }
        assert_true($thrown);
    });
};

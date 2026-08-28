<?php
declare(strict_types=1);

use App\Repository\SettingRepository;
use App\Service\ImageProcessor;
use App\Service\SiteSettingsService;

return function (TestSuite $suite): void {
    $suite->test('SiteSettingsService: mặc định + đọc cấu hình', function (): void {
        $pdo = make_sqlite();
        $repo = new SettingRepository($pdo);
        $svc = new SiteSettingsService($repo);

        assert_same('UrlShortM', $svc->siteName());
        $formats = $svc->mediaFormats();
        assert_true(in_array('jpg', $formats, true) && in_array('png', $formats, true));
        assert_false($svc->mediaCompress());
        assert_same('', $svc->mediaConvert());

        $repo->set('site_name', 'UrlShortM Pro');
        $repo->set('media_formats', json_encode(['webp', 'png']));
        $repo->set('media_compress', '1');
        $repo->set('media_convert', 'webp');
        assert_same('UrlShortM Pro', $svc->siteName());
        assert_same(['webp', 'png'], $svc->mediaFormats());
        assert_true($svc->mediaCompress());
        assert_same('webp', $svc->mediaConvert());
    });

    $suite->test('ImageProcessor: isAllowed + extForMime', function (): void {
        $img = new ImageProcessor();
        assert_true($img->isAllowed('image/jpeg', ['jpg', 'png']));
        assert_false($img->isAllowed('image/gif', ['jpg', 'png']));
        assert_same('webp', $img->extForMime('image/webp'));
        assert_null($img->extForMime('application/pdf'));
    });

    $suite->test('Mailer: isConfigured + send khi chưa cấu hình ném lỗi (không mạng)', function (): void {
        $pdo = make_sqlite();
        $repo = new SettingRepository($pdo);
        $mailer = new \App\Service\Mailer($repo);

        assert_false($mailer->isConfigured());

        $thrown = null;
        try {
            $mailer->send('a@b.vn', 'Test', 'Hello');
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }
        assert_true($thrown !== null, 'chưa cấu hình phải ném lỗi');
        assert_contains('Chưa cấu hình SMTP', $thrown->getMessage());

        // Cấu hình host + user + pass -> isConfigured true (không gọi mạng trong test)
        $repo->set('smtp_host', 'smtp.example.com');
        $repo->set('smtp_username', 'u');
        $repo->set('smtp_password', 'p');
        assert_true($mailer->isConfigured());
    });

    $suite->test('EmailTemplates: render 5 template đủ subject + html', function (): void {
        $pdo = make_sqlite();
        $repo = new SettingRepository($pdo);
        $svc = new SiteSettingsService($repo);
        $tpl = new \App\Service\EmailTemplates($svc);

        $types = $tpl->types();
        assert_same(5, count($types));
        assert_true(isset($types['purchase_success'], $types['invoice'], $types['registration'], $types['forgot_password'], $types['activate_account']));

        $data = [
            'name' => 'Minh', 'email' => 'm@x.vn', 'plan_name' => 'Pro', 'amount' => '399.000 ₫',
            'order_code' => 'DH-123', 'paid_at' => '01/01/2026', 'invoice_no' => '0000001',
            'dashboard_link' => 'http://x/dashboard', 'invoice_link' => 'http://x/hoa-don/1',
            'login_link' => 'http://x/dang-nhap', 'reset_link' => 'http://x/reset', 'activation_link' => 'http://x/active',
        ];
        foreach (array_keys($types) as $type) {
            $mail = $tpl->render($type, $data);
            assert_true($mail['subject'] !== '', 'thiếu subject: ' . $type);
            assert_contains('<html', $mail['html']);
            assert_contains('UrlShortM', $mail['html'], 'thiếu brand: ' . $type);
        }
        assert_contains('Mã đơn hàng', $tpl->render('purchase_success', $data)['html']);
        assert_contains('Số hoá đơn', $tpl->render('invoice', $data)['html']);
        assert_contains('Đặt lại mật khẩu', $tpl->render('forgot_password', $data)['html']);
        assert_contains('Kích hoạt tài khoản', $tpl->render('activate_account', $data)['html']);
    });
};

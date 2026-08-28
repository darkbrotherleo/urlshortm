<?php
declare(strict_types=1);

namespace App\Controller;

final class HelpController
{
    public function index(): never
    {
        http_response_code(200);
        echo \App\render('help-index', ['title' => 'Trung tâm trợ giúp']);
        exit;
    }

    public function pixelId(): never
    {
        http_response_code(200);
        echo \App\render('help-pixel-id', ['title' => 'Hướng dẫn lấy Pixel ID']);
        exit;
    }

    public function customDomain(): never
    {
        http_response_code(200);
        echo \App\render('help-custom-domain', [
            'title'      => 'Cách thêm tên miền tuỳ chỉnh',
            'relay_host' => \App\Config::get('app.domains.relay_host', 'links.urlshortm.com'),
        ]);
        exit;
    }
}

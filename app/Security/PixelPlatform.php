<?php
declare(strict_types=1);

namespace App\Security;

final class PixelPlatform
{
    public const LIST = [
        'facebook'     => 'Facebook / Meta',
        'google_ads'   => 'Google Ads',
        'ga4'          => 'Google Analytics 4',
        'gtm'          => 'Google Tag Manager',
        'tiktok'       => 'TikTok',
        'zalo'         => 'Zalo',
        'pinterest'    => 'Pinterest',
        'snapchat'     => 'Snapchat',
    ];

    public static function label(?string $key): string
    {
        if ($key !== null && isset(self::LIST[$key])) {
            return self::LIST[$key];
        }

        return $key !== null && $key !== '' ? $key : '—';
    }
}

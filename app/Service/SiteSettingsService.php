<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\SettingRepository;

/**
 * Đọc cấu hình website (Cài đặt Website) với giá trị mặc định.
 */
final class SiteSettingsService
{
    public function __construct(private readonly SettingRepository $settings)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->settings->get($key);

        return $value === null ? $default : $value;
    }

    public function siteName(): string
    {
        return (string) $this->get('site_name', 'UrlShortM');
    }

    public function siteIntro(): string
    {
        return (string) $this->get('site_intro', 'Rút gọn link dễ dàng, biết rõ ai đã bấm vào — nhẹ nhàng, miễn phí.');
    }

    /**
     * @return array<int,string> danh sách định dạng ảnh được phép (media_formats)
     */
    public function mediaFormats(): array
    {
        $raw = $this->get('media_formats', '');
        if ($raw === '') {
            return ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        }
        $list = json_decode((string) $raw, true);
        if (!is_array($list)) {
            return ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        }

        return array_values(array_filter(array_map('strtolower', array_map('strval', $list))));
    }

    public function mediaCompress(): bool
    {
        return $this->get('media_compress', '0') === '1';
    }

    public function mediaConvert(): string
    {
        $v = (string) $this->get('media_convert', '');
        return in_array($v, ['webp', 'avif'], true) ? $v : '';
    }

    public function logo(): string
    {
        return (string) $this->get('site_logo', '');
    }

    public function favicon(): string
    {
        return (string) $this->get('site_favicon', '');
    }
}

<?php
declare(strict_types=1);

namespace App\Tracking;

final class UserAgentParser
{
    /**
     * @return array{device:string,browser:string,os:string}
     */
    public function parse(string $ua): array
    {
        return [
            'device'  => $this->device($ua),
            'browser' => $this->browser($ua),
            'os'      => $this->os($ua),
        ];
    }

    private function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|Silk/i', $ua) === 1) {
            return 'tablet';
        }
        if (preg_match('/Mobi|Android.*Mobile|iPhone|iPod|Windows Phone/i', $ua) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function browser(string $ua): string
    {
        $map = [
            'Edg/'             => 'Edge',
            'OPR/'             => 'Opera',
            'SamsungBrowser/'  => 'Samsung Internet',
            'YaBrowser/'       => 'Yandex',
            'Vivaldi/'         => 'Vivaldi',
            'CriOS/'           => 'Chrome iOS',
            'FxiOS/'           => 'Firefox iOS',
            'Firefox/'         => 'Firefox',
        ];

        foreach ($map as $needle => $name) {
            if (strpos($ua, $needle) !== false) {
                return $name;
            }
        }

        if (preg_match('/Chrome\/(\d+)/i', $ua) === 1) {
            return 'Chrome';
        }
        if (preg_match('/Safari\/(\d+)/i', $ua) === 1) {
            return 'Safari';
        }
        if (preg_match('/MSIE|Trident/i', $ua) === 1) {
            return 'Internet Explorer';
        }

        return 'Khác';
    }

    private function os(string $ua): string
    {
        if (preg_match('/Windows NT 10\./', $ua) === 1) {
            return 'Windows 10/11';
        }
        if (preg_match('/Windows NT 6\.1/', $ua) === 1) {
            return 'Windows 7';
        }
        if (preg_match('/CrOS/i', $ua) === 1) {
            return 'ChromeOS';
        }
        if (preg_match('/Android/i', $ua) === 1) {
            return 'Android';
        }
        if (preg_match('/iPhone|iPad|iPod/i', $ua) === 1) {
            return 'iOS';
        }
        if (preg_match('/Mac OS X/i', $ua) === 1) {
            return 'macOS';
        }
        if (preg_match('/Linux/i', $ua) === 1) {
            return 'Linux';
        }

        return 'Khác';
    }
}

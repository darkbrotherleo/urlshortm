<?php
declare(strict_types=1);

namespace App\Security;

use App\Config;

final class UrlNormalizer
{
    /**
     * Chuẩn hoá URL: tự thêm scheme nếu thiếu, chỉ chấp nhận http/https,
     * dựng lại URL sạch. Trả về null nếu không hợp lệ.
     */
    public function normalize(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        if (!preg_match('~^[a-z][a-z0-9+.\-]*://~i', $raw)) {
            $raw = 'https://' . $raw;
        }

        $parts = parse_url($raw);
        if ($parts === false) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || !preg_match('/^[a-z0-9.\-\[\]:]+$/i', $host)) {
            return null;
        }

        // Không cho phép credential trong URL (tránh phishing).
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $url = $scheme . '://' . $host;

        if (isset($parts['port'])) {
            $port = (int) $parts['port'];
            if ($port < 1 || $port > 65535) {
                return null;
            }
            if (!($scheme === 'http' && $port === 80) && !($scheme === 'https' && $port === 443)) {
                $url .= ':' . $port;
            }
        }

        $path = $parts['path'] ?? '/';
        $url .= $path === '' ? '/' : $path;

        if (isset($parts['query']) && $parts['query'] !== '') {
            $url .= '?' . $parts['query'];
        }

        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $url .= '#' . $parts['fragment'];
        }

        $max = (int) Config::get('app.max_url_length', 2048);
        if (strlen($url) > $max) {
            return null;
        }

        return $url;
    }
}

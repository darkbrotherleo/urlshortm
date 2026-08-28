<?php
declare(strict_types=1);

namespace App\Tracking;

use App\Config;

/**
 * Tra quốc gia (mã ISO 2 ký tự) từ IP dùng file CSV dạng:
 *   ip_start,ip_end,country  (địa chỉ IPv4 dạng chấm).
 * Private/local IP trả về null. Thay file CSV bằng dataset đầy đủ khi deploy.
 */
final class CountryLookup
{
    /** @var array<int, array{int,int,string}>|null */
    private ?array $ranges = null;

    public function __construct(private readonly ?string $file = null)
    {
    }

    public function lookup(string $ip): ?string
    {
        $long = ip2long($ip);
        if ($long === false || $long < 0) {
            return null;
        }
        if ($this->isPrivate((int) $long)) {
            return null;
        }

        foreach ($this->ranges() as [$start, $end, $country]) {
            if ($long >= $start && $long <= $end) {
                return $country;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{int,int,string}>
     */
    private function ranges(): array
    {
        if ($this->ranges !== null) {
            return $this->ranges;
        }

        $file = $this->file ?? (string) Config::get('app.tracking.geoip_file', '');
        $this->ranges = [];

        if ($file === '' || !is_file($file)) {
            return $this->ranges;
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return $this->ranges;
        }

        $first = true;
        while (($line = fgetcsv($handle)) !== false) {
            if ($first) {
                $first = false;
                continue; // bỏ header
            }
            if (count($line) < 3 || trim((string) $line[2]) === '') {
                continue;
            }
            $start = ip2long(trim((string) $line[0]));
            $end = ip2long(trim((string) $line[1]));
            if ($start === false || $end === false) {
                continue;
            }
            $this->ranges[] = [(int) $start, (int) $end, trim((string) $line[2])];
        }
        fclose($handle);

        return $this->ranges;
    }

    private function isPrivate(int $long): bool
    {
        $private = [
            [ip2long('0.0.0.0'), ip2long('0.255.255.255')],
            [ip2long('10.0.0.0'), ip2long('10.255.255.255')],
            [ip2long('100.64.0.0'), ip2long('100.127.255.255')],
            [ip2long('127.0.0.0'), ip2long('127.255.255.255')],
            [ip2long('169.254.0.0'), ip2long('169.254.255.255')],
            [ip2long('172.16.0.0'), ip2long('172.31.255.255')],
            [ip2long('192.0.0.0'), ip2long('192.0.0.255')],
            [ip2long('192.168.0.0'), ip2long('192.168.255.255')],
            [ip2long('224.0.0.0'), ip2long('255.255.255.255')],
        ];

        foreach ($private as [$s, $e]) {
            if ($long >= $s && $long <= $e) {
                return true;
            }
        }

        return false;
    }
}

<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Tích hợp gói dịch vụ vào user: lấy gói active, kiểm tra giới hạn và tính năng.
 * Không có subscription active -> gói Free (mặc định).
 */
final class UserPlanService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string,mixed> gói active của user (hoặc Free nếu không có)
     */
    public function planOf(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.* FROM user_subscriptions s
             JOIN plans p ON p.id = s.plan_id
             WHERE s.user_id = ? AND s.status IN ('active','trial')
             ORDER BY s.id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return $row;
        }

        $free = $this->pdo->query("SELECT * FROM plans WHERE code = 'free' LIMIT 1")->fetch();
        if ($free !== false) {
            return $free;
        }

        return [
            'code' => 'free', 'name' => 'Miễn phí', 'max_links' => 20, 'max_clicks' => 10000,
            'max_custom_domains' => 5, 'max_pixels' => 5, 'max_users' => 1,
            'has_analytics' => 1, 'has_qr_code' => 1, 'has_password_protection' => 1,
            'has_link_expiration' => 1, 'has_utm_builder' => 1, 'has_api_access' => 0,
        ];
    }

    /**
     * @return array{links:int,domains:int,pixels:int,clicks_month:int}
     */
    public function usage(int $userId): array
    {
        $links = $this->count($userId, 'SELECT COUNT(*) FROM short_links WHERE user_id = ?');
        $domains = $this->count($userId, 'SELECT COUNT(*) FROM domains WHERE user_id = ?');
        $pixels = $this->count($userId, 'SELECT COUNT(*) FROM pixels WHERE user_id = ?');
        $clicks = $this->count($userId, 'SELECT COUNT(*) FROM click_events WHERE user_id = ? AND opened_at >= ?', date('Y-m-01 00:00:00'));

        return [
            'links'        => $links,
            'domains'      => $domains,
            'pixels'       => $pixels,
            'clicks_month' => $clicks,
        ];
    }

    /**
     * Giới hạn của gói, -1 -> null (không giới hạn).
     *
     * @return array{max_links:?int,max_clicks:?int,max_custom_domains:?int,max_pixels:?int,max_users:?int}
     */
    public function limits(int $userId): array
    {
        $p = $this->planOf($userId);

        return [
            'max_links'          => $this->resolve($p['max_links'] ?? 0),
            'max_clicks'         => $this->resolve($p['max_clicks'] ?? 0),
            'max_custom_domains' => $this->resolve($p['max_custom_domains'] ?? 0),
            'max_pixels'         => $this->resolve($p['max_pixels'] ?? 0),
            'max_users'          => $this->resolve($p['max_users'] ?? 1),
        ];
    }

    public function canCreateLink(int $userId): bool
    {
        $max = $this->limits($userId)['max_links'];

        return $max === null || $this->usage($userId)['links'] < $max;
    }

    public function canAddDomain(int $userId): bool
    {
        $max = $this->limits($userId)['max_custom_domains'];

        return $max === null || $this->usage($userId)['domains'] < $max;
    }

    public function canAddPixel(int $userId): bool
    {
        $max = $this->limits($userId)['max_pixels'];

        return $max === null || $this->usage($userId)['pixels'] < $max;
    }

    public function canClick(int $userId): bool
    {
        $max = $this->limits($userId)['max_clicks'];

        return $max === null || $this->usage($userId)['clicks_month'] < $max;
    }

    public function featureEnabled(int $userId, string $flag): bool
    {
        $p = $this->planOf($userId);

        return (int) ($p['has_' . $flag] ?? 0) === 1;
    }

    public function limitLabel(string $key, ?int $value): string
    {
        return $value === null ? 'Không giới hạn' : number_format($value);
    }

    private function resolve(int $value): ?int
    {
        return $value < 0 ? null : $value;
    }

    private function count(int $userId, string $sql, ?string $extra = null): int
    {
        $stmt = $this->pdo->prepare($sql);
        $params = [$userId];
        if ($extra !== null) {
            $params[] = $extra;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}

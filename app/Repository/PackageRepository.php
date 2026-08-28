<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class PackageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(?string $search = null, int $limit = 20, int $offset = 0): array
    {
        [$where, $params] = $this->whereSearch($search);
        $stmt = $this->pdo->prepare(
            'SELECT * FROM plans' . $where . ' ORDER BY sort_order, id LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countAll(?string $search = null): int
    {
        [$where, $params] = $this->whereSearch($search);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM plans' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string,mixed> $d
     */
    public function create(array $d): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO plans (code, name, description, price, price_monthly, currency, billing_period,
                max_links, max_clicks, max_custom_domains, max_pixels, max_users,
                has_analytics, has_qr_code, has_password_protection, has_link_expiration, has_utm_builder, has_api_access,
                is_popular, is_active, sort_order, features)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $d['code'], $d['name'], $d['description'], $d['price'], $d['price'], $d['currency'], $d['billing_period'],
            $d['max_links'], $d['max_clicks'], $d['max_custom_domains'], $d['max_pixels'], $d['max_users'],
            $d['has_analytics'], $d['has_qr_code'], $d['has_password_protection'], $d['has_link_expiration'],
            $d['has_utm_builder'], $d['has_api_access'], $d['is_popular'], $d['is_active'], $d['sort_order'],
            json_encode($d['features'], JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $d
     */
    public function update(int $id, array $d): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plans SET code=?, name=?, description=?, price=?, price_monthly=?, currency=?, billing_period=?,
                max_links=?, max_clicks=?, max_custom_domains=?, max_pixels=?, max_users=?,
                has_analytics=?, has_qr_code=?, has_password_protection=?, has_link_expiration=?, has_utm_builder=?, has_api_access=?,
                is_popular=?, is_active=?, sort_order=?, features=?
             WHERE id=?'
        );
        $stmt->execute([
            $d['code'], $d['name'], $d['description'], $d['price'], $d['price'], $d['currency'], $d['billing_period'],
            $d['max_links'], $d['max_clicks'], $d['max_custom_domains'], $d['max_pixels'], $d['max_users'],
            $d['has_analytics'], $d['has_qr_code'], $d['has_password_protection'], $d['has_link_expiration'],
            $d['has_utm_builder'], $d['has_api_access'], $d['is_popular'], $d['is_active'], $d['sort_order'],
            json_encode($d['features'], JSON_UNESCAPED_UNICODE),
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM plans WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function activeSubscriptionCount(int $planId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM user_subscriptions WHERE plan_id = ? AND status IN ('active','trial')"
        );
        $stmt->execute([$planId]);

        return (int) $stmt->fetchColumn();
    }

    public function toggle(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE plans SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function whereSearch(?string $search): array
    {
        if ($search !== null && trim($search) !== '') {
            return [' WHERE name LIKE ? OR code LIKE ?', ['%' . trim($search) . '%', '%' . trim($search) . '%']];
        }

        return ['', []];
    }
}

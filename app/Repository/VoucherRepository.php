<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class VoucherRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vouchers WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vouchers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function findAll(?string $search, int $limit, int $offset): array
    {
        [$where, $params] = $this->whereSearch($search);
        $stmt = $this->pdo->prepare(
            'SELECT v.*,
                    (SELECT COUNT(*) FROM voucher_usages u WHERE u.voucher_id = v.id) AS used_count,
                    (SELECT o.order_code FROM voucher_usages u LEFT JOIN orders o ON o.id = u.order_id
                        WHERE u.voucher_id = v.id ORDER BY u.id DESC LIMIT 1) AS last_order_code,
                    (SELECT u.amount_before FROM voucher_usages u WHERE u.voucher_id = v.id ORDER BY u.id DESC LIMIT 1) AS last_before,
                    (SELECT u.amount_after FROM voucher_usages u WHERE u.voucher_id = v.id ORDER BY u.id DESC LIMIT 1) AS last_after,
                    (SELECT u.status FROM voucher_usages u WHERE u.voucher_id = v.id ORDER BY u.id DESC LIMIT 1) AS last_status
             FROM vouchers v' . $where
            . ' ORDER BY v.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countAll(?string $search): int
    {
        [$where, $params] = $this->whereSearch($search);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM vouchers v' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $d
     */
    public function create(array $d): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO vouchers (code, campaign_name, discount_type, discount_value, usage_limit, per_user, starts_at, ends_at, note, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $d['code'], $d['campaign_name'], $d['discount_type'], $d['discount_value'], $d['usage_limit'],
            $d['per_user'], $d['starts_at'], $d['ends_at'], $d['note'], $d['is_active'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $d
     */
    public function update(int $id, array $d): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE vouchers SET code=?, campaign_name=?, discount_type=?, discount_value=?, usage_limit=?, per_user=?, starts_at=?, ends_at=?, note=?, is_active=? WHERE id=?'
        );
        $stmt->execute([
            $d['code'], $d['campaign_name'], $d['discount_type'], $d['discount_value'], $d['usage_limit'],
            $d['per_user'], $d['starts_at'], $d['ends_at'], $d['note'], $d['is_active'], $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM vouchers WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function toggle(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE vouchers SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function incrementUsed(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE vouchers SET used_count = used_count + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Đếm số lần user đã dùng voucher (cho per_user = once).
     */
    public function countUserUsages(int $voucherId, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM voucher_usages WHERE voucher_id = ? AND user_id = ? AND status = 'success'"
        );
        $stmt->execute([$voucherId, $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function recordUsage(int $voucherId, ?int $orderId, ?int $userId, string $status, float $before, float $after): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO voucher_usages (voucher_id, order_id, user_id, status, amount_before, amount_after)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([$voucherId, $orderId, $userId, $status, $before, $after]);
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function whereSearch(?string $search): array
    {
        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';

            return [' WHERE (v.code LIKE ? OR v.campaign_name LIKE ?)', [$term, $term]];
        }

        return ['', []];
    }
}

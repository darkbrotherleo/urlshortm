<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class OrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $userId, int $planId, string $planName, string $billingPeriod, float $amount, string $currency): int
    {
        $code = $this->generateCode();
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (order_code, user_id, plan_id, plan_name, billing_period, amount, currency)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$code, $userId, $planId, $planName, $billingPeriod, $amount, $currency]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE order_code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findByUserAndCode(int $userId, string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE order_code = ? AND user_id = ?');
        $stmt->execute([$code, $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findByUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit));
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function setGateway(int $id, string $gatewayOrderId): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET gateway_order_id = ? WHERE id = ?');
        $stmt->execute([$gatewayOrderId, $id]);
    }

    public function markPaid(int $id, ?string $payer): void
    {
        $stmt = $this->pdo->prepare("UPDATE orders SET status = 'paid', payer = ?, paid_at = ? WHERE id = ?");
        $stmt->execute([$payer, date('Y-m-d H:i:s'), $id]);
    }

    public function markStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    /**
     * Danh sách đơn hàng cho admin (join user email).
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAllForAdmin(?string $search, ?string $status, int $limit, int $offset): array
    {
        [$where, $params] = $this->adminWhere($search, $status);
        $stmt = $this->pdo->prepare(
            'SELECT o.*, u.email AS user_email, COALESCE(NULLIF(u.display_name, \'\'), u.email) AS username
             FROM orders o JOIN users u ON u.id = o.user_id' . $where
            . ' ORDER BY o.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countAllForAdmin(?string $search, ?string $status): int
    {
        [$where, $params] = $this->adminWhere($search, $status);
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id' . $where
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function adminWhere(?string $search, ?string $status): array
    {
        $where = [];
        $params = [];
        if ($status !== null && in_array($status, ['pending', 'paid', 'canceled', 'failed'], true)) {
            $where[] = 'o.status = ?';
            $params[] = $status;
        }
        if ($search !== null && trim($search) !== '') {
            $where[] = '(o.order_code LIKE ? OR u.email LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        return [$where !== [] ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function generateCode(): string
    {
        do {
            $code = 'DH-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_code = ?');
            $stmt->execute([$code]);
            $exists = (int) $stmt->fetchColumn() > 0;
        } while ($exists);

        return $code;
    }
}

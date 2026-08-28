<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{id:int,email:string,password_hash:string,display_name:?string,status:string}|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, display_name, status, activation_token, activation_expires_at,
                    reset_token, reset_expires_at
               FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array{id:int,email:string,display_name:?string,status:string,created_at:string,
     *               phone:?string,address:?string,city:?string,tax_type:?string,
     *               company_name:?string,tax_id:?string,invoice_name:?string}|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, display_name, status, created_at,
                    phone, address, city, tax_type, company_name, tax_id, invoice_name
             FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return int id vừa tạo
     *
     * @throws \PDOException khi email trùng (sqlstate 23000)
     */
    public function insert(string $email, string $passwordHash, ?string $displayName): int
    {
        $displayName = trim((string) $displayName);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, display_name) VALUES (?, ?, ?)'
        );
        $stmt->execute([$email, $passwordHash, $displayName !== '' ? $displayName : null]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function updateDisplayName(int $id, string $displayName): void
    {
        $displayName = trim($displayName);
        $stmt = $this->pdo->prepare('UPDATE users SET display_name = ? WHERE id = ?');
        $stmt->execute([$displayName !== '' ? $displayName : null, $id]);
    }

    /**
     * Cập nhật hồ sơ (allowlist cột cố định). Giá trị '' -> NULL.
     */
    public function updateProfile(int $id, array $fields): void
    {
        $allowed = ['phone', 'address', 'city', 'tax_type', 'company_name', 'tax_id', 'invoice_name'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            $sets[] = '`' . $col . '` = ?';
            $value = (string) $fields[$col];
            $params[] = $value !== '' ? $value : null;
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;

        $this->pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    public function passwordHashOf(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $id]);
    }

    /**
     * Soft delete — chỉ chuyển trạng thái, KHÔNG xoá dòng dữ liệu (data giữ lại).
     */
    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET status = 'disabled' WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public function updateEmail(int $id, string $email): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
        $stmt->execute([$email, $id]);
    }

    public function setActivation(int $id, string $token, string $expiresAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET activation_token = ?, activation_expires_at = ? WHERE id = ?');
        $stmt->execute([$token, $expiresAt, $id]);
    }

    public function findByActivationToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE activation_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Kích hoạt tài khoản (bỏ hold): active + email_verified_at + xoá token.
     */
    public function activate(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET status = 'active', email_verified_at = ?, activation_token = NULL, activation_expires_at = NULL WHERE id = ?"
        );
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function setResetToken(int $id, string $token, string $expiresAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?');
        $stmt->execute([$token, $expiresAt, $id]);
    }

    public function findByResetToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE reset_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function clearResetToken(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET reset_token = NULL, reset_expires_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Gắn gói Free cho user mới (không có subscription nào).
     */
    public function subscribeToFreePlan(int $userId): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM plans WHERE code = 'free' AND is_active = 1 LIMIT 1");
        $stmt->execute();
        $planId = $stmt->fetchColumn();
        if ($planId === false) {
            return;
        }

        $check = $this->pdo->prepare(
            "SELECT COUNT(*) FROM user_subscriptions WHERE user_id = ? AND status IN ('active','trial')"
        );
        $check->execute([$userId]);
        if ((int) $check->fetchColumn() > 0) {
            return;
        }

        $ins = $this->pdo->prepare(
            "INSERT INTO user_subscriptions (user_id, plan_id, status, starts_at, ends_at) VALUES (?, ?, 'active', ?, NULL)"
        );
        $ins->execute([$userId, (int) $planId, date('Y-m-d H:i:s')]);
    }

    /**
     * Danh sách user cho admin: kèm subscription hiện tại (active/trial) + gói.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAllForAdmin(): array
    {
        $stmt = $this->pdo->prepare($this->adminUserSelect() . ' ORDER BY u.id DESC');
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findUserForAdmin(int $id): ?array
    {
        $stmt = $this->pdo->prepare($this->adminUserSelect() . ' WHERE u.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int,array{id:int,code:string,name:string,price_monthly:?string}>
     */
    public function plansAll(): array
    {
        return $this->pdo->query(
            'SELECT id, code, name, price_monthly FROM plans WHERE is_active = 1 ORDER BY sort_order, id'
        )->fetchAll();
    }

    /**
     * Cấp/đổi gói cho user: $planId = 0/null -> Miễn phí (hết hạn sub đang chạy).
     * $starts/$ends có thể null.
     */
    public function setSubscription(int $userId, ?int $planId, ?string $starts, ?string $ends): void
    {
        if ($planId === null || $planId <= 0) {
            $stmt = $this->pdo->prepare(
                "UPDATE user_subscriptions SET status = 'expired', ends_at = ? WHERE user_id = ? AND status IN ('active','trial')"
            );
            $stmt->execute([date('Y-m-d H:i:s'), $userId]);

            return;
        }

        $find = $this->pdo->prepare(
            "SELECT id FROM user_subscriptions WHERE user_id = ? AND status IN ('active','trial') ORDER BY id DESC LIMIT 1"
        );
        $find->execute([$userId]);
        $subId = $find->fetchColumn();

        if ($subId === false) {
            $ins = $this->pdo->prepare(
                "INSERT INTO user_subscriptions (user_id, plan_id, status, starts_at, ends_at) VALUES (?, ?, 'active', ?, ?)"
            );
            $ins->execute([$userId, $planId, $starts, $ends]);
        } else {
            $upd = $this->pdo->prepare(
                "UPDATE user_subscriptions SET plan_id = ?, starts_at = ?, ends_at = ?, status = 'active' WHERE id = ?"
            );
            $upd->execute([$planId, $starts, $ends, (int) $subId]);
        }
    }

    private function adminUserSelect(): string
    {
        return 'SELECT u.id, u.email, COALESCE(NULLIF(u.display_name, \'\'), u.email) AS username,
                       u.display_name, u.status, u.created_at, u.phone, u.address, u.city,
                       u.tax_type, u.company_name, u.tax_id, u.invoice_name,
                       s.id AS sub_id, s.status AS sub_status, s.starts_at, s.ends_at,
                       p.id AS plan_id, p.code AS plan_code, p.name AS plan_name
                FROM users u
                LEFT JOIN user_subscriptions s ON s.id = (
                    SELECT s2.id FROM user_subscriptions s2
                    WHERE s2.user_id = u.id AND s2.status IN (\'active\',\'trial\')
                    ORDER BY s2.id DESC LIMIT 1
                )
                LEFT JOIN plans p ON p.id = s.plan_id';
    }
}

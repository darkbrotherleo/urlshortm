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
            'SELECT id, email, password_hash, display_name, status FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array{id:int,email:string,display_name:?string,status:string,created_at:string}|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, display_name, status, created_at FROM users WHERE id = ?'
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
}

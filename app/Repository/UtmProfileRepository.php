<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UtmProfileRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array{id:int,name:string,utm_campaign:?string,utm_medium:?string,utm_source:?string,utm_term:?string,utm_content:?string}>
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, utm_campaign, utm_medium, utm_source, utm_term, utm_content
             FROM utm_profiles WHERE user_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string,mixed>|null profile thuộc user
     */
    public function findById(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, utm_campaign, utm_medium, utm_source, utm_term, utm_content
             FROM utm_profiles WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $userId, string $name, array $utm): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO utm_profiles (user_id, name, utm_campaign, utm_medium, utm_source, utm_term, utm_content)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $name, $utm['utm_campaign'], $utm['utm_medium'], $utm['utm_source'], $utm['utm_term'], $utm['utm_content']]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $userId, string $name, array $utm): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE utm_profiles SET name = ?, utm_campaign = ?, utm_medium = ?, utm_source = ?, utm_term = ?, utm_content = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$name, $utm['utm_campaign'], $utm['utm_medium'], $utm['utm_source'], $utm['utm_term'], $utm['utm_content'], $id, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM utm_profiles WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
}

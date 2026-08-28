<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class PixelRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Pixel của user (không còn pixel mặc định hệ thống).
     *
     * @return array<int, array{id:int,code:string,name:?string,platform:?string,created_at:string}>
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, platform, created_at FROM pixels
             WHERE user_id = ?
             ORDER BY id ASC'
        );
        $stmt->execute([$userId]);

        return array_map(
            static fn (array $row): array => [
                'id'         => (int) $row['id'],
                'code'       => (string) $row['code'],
                'name'       => $row['name'],
                'platform'   => $row['platform'],
                'created_at' => (string) $row['created_at'],
            ],
            $stmt->fetchAll()
        );
    }

    public function create(int $userId, string $code, string $name, ?string $platform): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO pixels (user_id, code, name, platform, is_active, sort_order) VALUES (?, ?, ?, ?, 1, 100)');
        $stmt->execute([$userId, $code, $name, $platform]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM pixels WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    /**
     * @return array{id:int,code:string,name:?string,platform:?string}|null pixel thuộc user
     */
    public function findById(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, name, platform FROM pixels WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function update(int $id, int $userId, string $code, string $name, ?string $platform): void
    {
        $stmt = $this->pdo->prepare('UPDATE pixels SET code = ?, name = ?, platform = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$code, $name, $platform, $id, $userId]);
    }

    public function existsCode(string $code, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM pixels WHERE code = ?';
        $params = [$code];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}

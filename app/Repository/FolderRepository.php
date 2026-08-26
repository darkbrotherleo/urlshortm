<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class FolderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $userId, string $name): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO folders (user_id, name) VALUES (?, ?)');
        $stmt->execute([$userId, $name]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<int, array{id:int,name:string,created_at:string,total_links:int}> thư mục của user
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT f.id, f.name, f.created_at, COUNT(s.id) AS total_links
             FROM folders f
             LEFT JOIN short_links s ON s.folder_id = f.id
             WHERE f.user_id = ?
             GROUP BY f.id
             ORDER BY f.created_at ASC, f.id ASC'
        );
        $stmt->execute([$userId]);

        return array_map(
            static fn (array $row): array => [
                'id'          => (int) $row['id'],
                'name'        => (string) $row['name'],
                'created_at'  => (string) $row['created_at'],
                'total_links' => (int) $row['total_links'],
            ],
            $stmt->fetchAll()
        );
    }

    /**
     * @return array{id:int,name:string}|null thư mục thuộc user (kiểm quyền)
     */
    public function findById(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM folders WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Xoá thư mục (link trong đó được trả về "không thư mục"). Dùng transaction.
     */
    public function delete(int $id, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $unassign = $this->pdo->prepare('UPDATE short_links SET folder_id = NULL WHERE folder_id = ? AND user_id = ?');
            $unassign->execute([$id, $userId]);

            $delete = $this->pdo->prepare('DELETE FROM folders WHERE id = ? AND user_id = ?');
            $delete->execute([$id, $userId]);

            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

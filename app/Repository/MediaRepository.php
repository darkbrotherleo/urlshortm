<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class MediaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function findAll(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM media ORDER BY id DESC LIMIT ' . max(1, $limit));
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM media WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $filename, string $originalName, string $path, ?string $mime, int $size): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO media (filename, original_name, path, mime, size) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$filename, $originalName, $path, $mime, $size]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM media WHERE id = ?');
        $stmt->execute([$id]);
    }
}

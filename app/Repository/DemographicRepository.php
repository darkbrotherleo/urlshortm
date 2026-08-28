<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class DemographicRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function saveSnapshot(int $userId, array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO demographic_snapshots (user_id, payload, fetched_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, json_encode($payload, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s')]);
    }

    /**
     * @return array{payload:array<string,mixed>,fetched_at:string}|null
     */
    public function latest(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT payload, fetched_at FROM demographic_snapshots
             WHERE user_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $payload = json_decode((string) $row['payload'], true);

        return [
            'payload'    => is_array($payload) ? $payload : [],
            'fetched_at' => (string) $row['fetched_at'],
        ];
    }

    public function deleteAll(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM demographic_snapshots WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
}

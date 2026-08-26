<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class RateLimitRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Tăng counter (upsert theo (ip_hash, bucket_key, window_start)).
     * Trả về count sau khi tăng.
     */
    public function increment(string $ipHash, string $bucketKey, int $windowStart): int
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Upsert một câu lệnh (tránh INSERT-fail rồi UPDATE gây lock/rollback).
        if ($driver === 'sqlite') {
            $sql = 'INSERT INTO rate_limits (ip_hash, bucket_key, window_start, count)
                    VALUES (?, ?, ?, 1)
                    ON CONFLICT(ip_hash, bucket_key, window_start)
                    DO UPDATE SET count = count + 1';
        } else {
            $sql = 'INSERT INTO rate_limits (ip_hash, bucket_key, window_start, count)
                    VALUES (?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE count = count + 1';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ipHash, $bucketKey, $windowStart]);

        $select = $this->pdo->prepare(
            'SELECT count FROM rate_limits WHERE ip_hash = ? AND bucket_key = ? AND window_start = ?'
        );
        $select->execute([$ipHash, $bucketKey, $windowStart]);
        $count = (int) $select->fetchColumn();

        return $count;
    }
}

<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UserSettingsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(int $userId, string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT svalue FROM user_settings WHERE user_id = ? AND skey = ?');
        $stmt->execute([$userId, $key]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function set(int $userId, string $key, string $value): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = 'INSERT INTO user_settings (user_id, skey, svalue) VALUES (?, ?, ?)
                    ON CONFLICT(user_id, skey) DO UPDATE SET svalue = excluded.svalue, updated_at = datetime(\'now\')';
        } else {
            $sql = 'INSERT INTO user_settings (user_id, skey, svalue) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), updated_at = NOW()';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $key, $value]);
    }

    public function delete(int $userId, string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_settings WHERE user_id = ? AND skey = ?');
        $stmt->execute([$userId, $key]);
    }
}

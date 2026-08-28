<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class SettingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = 'INSERT INTO settings (skey, svalue) VALUES (?, ?)
                    ON CONFLICT (skey) DO UPDATE SET svalue = excluded.svalue';
        } else {
            $sql = 'INSERT INTO settings (skey, svalue) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$key, $value]);
    }

    public function delete(string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM settings WHERE skey = ?');
        $stmt->execute([$key]);
    }

    /**
     * @return array<string,string>
     */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT skey, svalue FROM settings')->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['skey']] = (string) $row['svalue'];
        }

        return $map;
    }
}

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
     * @return array<int, array{id:int,code:string,name:?string}>
     */
    public function findAllActive(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, code, name FROM pixels WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        );

        return array_map(
            static fn (array $row): array => [
                'id'   => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => $row['name'],
            ],
            $stmt->fetchAll()
        );
    }
}

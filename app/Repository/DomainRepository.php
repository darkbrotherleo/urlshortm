<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class DomainRepository
{
    private const COLUMNS = 'id, domain, is_verified, verification_token, verified_at, dns_checked_at, last_error';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM domains
             WHERE user_id = ? AND is_active = 1
             ORDER BY id ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<string,mixed>|null
     */
    /**
     * @return array<int, array<string,mixed>> domain đã xác minh (dùng được)
     */
    public function findVerifiedByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM domains
             WHERE user_id = ? AND is_active = 1 AND is_verified = 1
             ORDER BY id ASC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function findById(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM domains WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(int $userId, string $domain, string $verificationToken): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO domains (user_id, domain, is_verified, verification_token) VALUES (?, ?, 0, ?)'
        );
        $stmt->execute([$userId, $domain, $verificationToken]);

        return (int) $this->pdo->lastInsertId();
    }

    public function markVerified(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE domains SET is_verified = 1, verified_at = ?, dns_checked_at = ?, last_error = NULL WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id, $userId]);
    }

    public function setLastError(int $id, int $userId, string $message): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE domains SET dns_checked_at = ?, last_error = ? WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $message, $id, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM domains WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    public function existsDomain(string $domain): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM domains WHERE domain = ?');
        $stmt->execute([$domain]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /* ---------------- Domain hệ thống ---------------- */

    /**
     * @return array<int,array<string,mixed>>
     */
    public function findSystemDomains(): array
    {
        return $this->pdo->query('SELECT * FROM system_domains ORDER BY is_default DESC, id ASC')->fetchAll();
    }

    public function systemDefault(): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM system_domains WHERE is_default = 1 AND is_active = 1 LIMIT 1'
        );
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function systemFindByDomain(string $domain): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM system_domains WHERE domain = ?');
        $stmt->execute([$domain]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function addSystemDomain(string $domain): void
    {
        if ($this->systemFindByDomain($domain) !== null) {
            return;
        }
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM system_domains')->fetchColumn();
        $stmt = $this->pdo->prepare(
            'INSERT INTO system_domains (domain, is_default, is_active) VALUES (?, ?, 1)'
        );
        $stmt->execute([$domain, $count === 0 ? 1 : 0]);
    }

    public function setSystemDefault(int $id): void
    {
        $this->pdo->prepare('UPDATE system_domains SET is_default = 0 WHERE is_default = 1')->execute();
        $this->pdo->prepare('UPDATE system_domains SET is_default = 1 WHERE id = ?')->execute([$id]);
    }

    public function toggleSystemActive(int $id): void
    {
        $this->pdo->prepare('UPDATE system_domains SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    }

    public function deleteSystemDomain(int $id): void
    {
        $this->pdo->prepare('DELETE FROM system_domains WHERE id = ?')->execute([$id]);
    }

    /* ---------------- Domain của user (admin) ---------------- */

    /**
     * @return array<int,array<string,mixed>>
     */
    public function findAllForAdmin(?string $search, int $limit, int $offset): array
    {
        [$where, $params] = $this->adminWhere($search);
        $stmt = $this->pdo->prepare(
            'SELECT d.*, u.email AS user_email, COALESCE(NULLIF(u.display_name, \'\'), u.email) AS username
             FROM domains d JOIN users u ON u.id = d.user_id' . $where
            . ' ORDER BY d.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countAllForAdmin(?string $search): int
    {
        [$where, $params] = $this->adminWhere($search);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM domains d JOIN users u ON u.id = d.user_id' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findByIdAny(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM domains WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function toggleUserActive(int $id): void
    {
        $this->pdo->prepare('UPDATE domains SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    }

    public function deleteAny(int $id): void
    {
        $this->pdo->prepare('DELETE FROM domains WHERE id = ?')->execute([$id]);
    }

    public function countActiveByUser(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM domains WHERE user_id = ? AND is_active = 1');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function adminWhere(?string $search): array
    {
        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';

            return [
                ' WHERE (d.domain LIKE ? OR u.email LIKE ? OR COALESCE(u.display_name, \'\') LIKE ?)',
                [$term, $term, $term],
            ];
        }

        return ['', []];
    }
}

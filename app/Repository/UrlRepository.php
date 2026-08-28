<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UrlRepository
{
    private const COLUMNS = 'id, slug, target_url, click_count, user_id, folder_id, link_type,
        title, description, thumbnail, pixels, utm_campaign, utm_medium, utm_source, utm_term,
        utm_content, domain, password_hash, starts_at, ends_at, is_active, created_at, updated_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM short_links WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string,mixed>|null link thuộc user
     */
    public function findById(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM short_links WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Thêm link ẩn danh (không metadata).
     */
    public function insert(string $slug, string $targetUrl, ?int $userId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO short_links (slug, target_url, user_id) VALUES (?, ?, ?)');
        $stmt->execute([$slug, $targetUrl, $userId]);
    }

    /**
     * Tạo link đầy đủ. Trả về id.
     */
    public function create(array $f): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO short_links
             (slug, target_url, user_id, folder_id, link_type, title, description, thumbnail,
              pixels, utm_campaign, utm_medium, utm_source, utm_term, utm_content,
              domain, password_hash, starts_at, ends_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $f['slug'], $f['target_url'], $f['user_id'], $f['folder_id'], $f['link_type'],
            $f['title'], $f['description'], $f['thumbnail'], $f['pixels'],
            $f['utm_campaign'], $f['utm_medium'], $f['utm_source'], $f['utm_term'], $f['utm_content'],
            $f['domain'], $f['password_hash'], $f['starts_at'], $f['ends_at'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $f, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE short_links SET
                target_url = ?, folder_id = ?, link_type = ?, title = ?, description = ?,
                thumbnail = ?, pixels = ?, utm_campaign = ?, utm_medium = ?, utm_source = ?,
                utm_term = ?, utm_content = ?, domain = ?, password_hash = ?, starts_at = ?, ends_at = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $f['target_url'], $f['folder_id'], $f['link_type'], $f['title'], $f['description'],
            $f['thumbnail'], $f['pixels'], $f['utm_campaign'], $f['utm_medium'], $f['utm_source'],
            $f['utm_term'], $f['utm_content'], $f['domain'], $f['password_hash'], $f['starts_at'], $f['ends_at'],
            $id, $userId,
        ]);
    }

    public function updateSlug(int $id, string $slug, int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE short_links SET slug = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$slug, $id, $userId]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM short_links WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    /**
     * Xoá hàng loạt theo id thuộc user.
     */
    public function bulkDelete(array $ids, int $userId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare('DELETE FROM short_links WHERE id IN (' . $placeholders . ') AND user_id = ?');
        $stmt->execute(array_merge($ids, [$userId]));

        return $stmt->rowCount();
    }

    /**
     * Chuyển thư mục hàng loạt (folderId null = bỏ thư mục).
     */
    public function bulkMove(array $ids, ?int $folderId, int $userId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare('UPDATE short_links SET folder_id = ? WHERE id IN (' . $placeholders . ') AND user_id = ?');
        $stmt->execute(array_merge([$folderId], $ids, [$userId]));

        return $stmt->rowCount();
    }

    public function incrementClicks(string $slug): int
    {
        $stmt = $this->pdo->prepare('UPDATE short_links SET click_count = click_count + 1 WHERE slug = ?');
        $stmt->execute([$slug]);

        return $stmt->rowCount();
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function findByUser(int $userId, ?int $limit = null): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM short_links
                WHERE user_id = ?
                ORDER BY created_at DESC, id DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, (int) $limit);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function findByFolder(int $folderId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM short_links
             WHERE folder_id = ? AND user_id = ?
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$folderId, $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Gán/đổi thư mục cho link (chỉ khi link thuộc user). folderId null = bỏ thư mục.
     */
    public function assignFolder(int $linkId, ?int $folderId, int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE short_links SET folder_id = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$folderId, $linkId, $userId]);
    }

    /**
     * @return array{total_links:int,total_clicks:int} thống kê cho một user
     */
    public function userTotals(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total_links, COALESCE(SUM(click_count), 0) AS total_clicks
             FROM short_links WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return [
            'total_links'  => (int) ($row['total_links'] ?? 0),
            'total_clicks' => (int) ($row['total_clicks'] ?? 0),
        ];
    }

    /**
     * Danh sách link cho admin (join user; tìm theo slug / username / email).
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAllForAdmin(?string $search, int $limit, int $offset): array
    {
        [$where, $params] = $this->adminWhere($search);
        $stmt = $this->pdo->prepare(
            'SELECT l.*, u.email AS user_email, COALESCE(NULLIF(u.display_name, \'\'), u.email) AS username
             FROM short_links l LEFT JOIN users u ON u.id = l.user_id' . $where
            . ' ORDER BY l.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countAllForAdmin(?string $search): int
    {
        [$where, $params] = $this->adminWhere($search);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM short_links l LEFT JOIN users u ON u.id = l.user_id' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function toggleActive(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE short_links SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Cập nhật link bởi admin (allowlist cột cố định).
     *
     * @param array<string,mixed> $fields
     */
    public function updateByAdmin(int $id, array $fields): void
    {
        $allowed = ['target_url', 'title', 'description', 'ends_at', 'is_active'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            $sets[] = '`' . $col . '` = ?';
            $params[] = $fields[$col] !== '' ? $fields[$col] : null;
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        $this->pdo->prepare('UPDATE short_links SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    /**
     * Tự xoá link khách (user_id NULL) không được chỉnh sửa trong $days ngày.
     *
     * @return int số link đã xoá
     */
    public function cleanupGuestLinks(int $days): int
    {
        $days = max(1, (int) $days);
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = "DELETE FROM short_links WHERE user_id IS NULL
                    AND COALESCE(updated_at, created_at) < datetime('now', '-" . $days . " days')";
        } else {
            $sql = "DELETE FROM short_links WHERE user_id IS NULL
                    AND COALESCE(updated_at, created_at) < DATE_SUB(NOW(), INTERVAL " . $days . " DAY)";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function adminWhere(?string $search): array
    {
        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';

            return [
                ' WHERE (l.slug LIKE ? OR l.target_url LIKE ? OR u.email LIKE ? OR COALESCE(u.display_name, \'\') LIKE ?)',
                [$term, $term, $term, $term],
            ];
        }

        return ['', []];
    }
}

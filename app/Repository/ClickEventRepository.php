<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ClickEventRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $data link_id, user_id, ip_hash, user_agent, referrer
     */
    public function record(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO click_events
             (link_id, user_id, opened_at, ip_hash, ip_address, user_agent, referrer, country, device, browser, os)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['link_id'],
            $data['user_id'] !== null ? (int) $data['user_id'] : null,
            $data['opened_at'],
            (string) $data['ip_hash'],
            $data['ip_address'] ?? null,
            $data['user_agent'],
            $data['referrer'],
            $data['country'] ?? null,
            $data['device'] ?? null,
            $data['browser'] ?? null,
            $data['os'] ?? null,
        ]);
    }

    public function countByLink(int $linkId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM click_events WHERE link_id = ?');
        $stmt->execute([$linkId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * WHERE chung cho báo cáo (scope theo user + filter link/khoảng thời gian).
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    private function reportWhere(int $userId, ?int $linkId, ?string $from, ?string $to): array
    {
        $where = 'ce.user_id = ?';
        $params = [$userId];

        if ($linkId !== null) {
            $where .= ' AND ce.link_id = ?';
            $params[] = $linkId;
        }
        if ($from !== null) {
            $where .= ' AND ce.opened_at >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where .= ' AND ce.opened_at <= ?';
            $params[] = $to;
        }

        return [$where, $params];
    }

    /**
     * @return array{total_clicks:int,total_days:int,total_links:int,avg_per_day:float}
     */
    public function reportSummary(int $userId, ?int $linkId = null, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->reportWhere($userId, $linkId, $from, $to);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total_clicks,
                    COUNT(DISTINCT DATE(ce.opened_at)) AS total_days,
                    COUNT(DISTINCT ce.link_id) AS total_links
             FROM click_events ce WHERE $where"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        $total = (int) ($row['total_clicks'] ?? 0);
        $days = max(1, (int) ($row['total_days'] ?? 0));

        return [
            'total_clicks' => $total,
            'total_days'   => (int) ($row['total_days'] ?? 0),
            'total_links'  => (int) ($row['total_links'] ?? 0),
            'avg_per_day'  => round($total / $days, 1),
        ];
    }

    /**
     * @return array<int, array{label:string,count:int}>
     */
    public function reportByDay(int $userId, ?int $linkId = null, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->reportWhere($userId, $linkId, $from, $to);
        $stmt = $this->pdo->prepare(
            "SELECT DATE(ce.opened_at) AS label, COUNT(*) AS count
             FROM click_events ce WHERE $where
             GROUP BY label ORDER BY label ASC"
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $r): array => ['label' => (string) $r['label'], 'count' => (int) $r['count']],
            $stmt->fetchAll()
        );
    }

    /**
     * @return array<int, array{label:string,count:int}>
     */
    public function reportByFactor(string $column, int $userId, ?int $linkId = null, ?string $from = null, ?string $to = null): array
    {
        $label = $column === 'referrer' ? "COALESCE(NULLIF(ce.$column, ''), '(trực tiếp)')" : "COALESCE(ce.$column, 'Khác')";
        [$where, $params] = $this->reportWhere($userId, $linkId, $from, $to);

        $stmt = $this->pdo->prepare(
            "SELECT $label AS label, COUNT(*) AS count
             FROM click_events ce WHERE $where
             GROUP BY label ORDER BY count DESC"
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $r): array => ['label' => (string) $r['label'], 'count' => (int) $r['count']],
            $stmt->fetchAll()
        );
    }

    /**
     * @return array<int, array{link_id:int,slug:string,title:string,count:int}>
     */
    public function reportTopLinks(int $userId, ?int $linkId = null, ?string $from = null, ?string $to = null, int $limit = 8): array
    {
        [$where, $params] = $this->reportWhere($userId, $linkId, $from, $to);

        $stmt = $this->pdo->prepare(
            "SELECT ce.link_id, sl.slug, COALESCE(NULLIF(sl.title, ''), sl.slug) AS title, COUNT(*) AS count
             FROM click_events ce
             JOIN short_links sl ON sl.id = ce.link_id
             WHERE $where
             GROUP BY ce.link_id, sl.slug, sl.title
             ORDER BY count DESC
             LIMIT " . max(1, (int) $limit)
        );
        $stmt->execute($params);

        return array_map(
            static fn (array $r): array => [
                'link_id' => (int) $r['link_id'],
                'slug'    => (string) $r['slug'],
                'title'   => (string) $r['title'],
                'count'   => (int) $r['count'],
            ],
            $stmt->fetchAll()
        );
    }

    /**
     * Danh sách lượt mở chi tiết (join link slug/title).
     *
     * @return array<int, array<string,mixed>>
     */
    public function reportEvents(int $userId, ?int $linkId = null, ?string $from = null, ?string $to = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->reportWhere($userId, $linkId, $from, $to);

        $sql = "SELECT ce.id, ce.opened_at, ce.ip_hash, ce.ip_address, ce.country, ce.device, ce.browser, ce.os, ce.referrer,
                       sl.slug, COALESCE(NULLIF(sl.title, ''), sl.slug) AS title
                FROM click_events ce
                JOIN short_links sl ON sl.id = ce.link_id
                WHERE $where
                ORDER BY ce.opened_at DESC, ce.id DESC";

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, (int) $limit);
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ' . max(0, (int) $offset);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countReportEvents(int $userId, ?int $linkId = null, ?string $from = null, ?string $to = null): int
    {
        [$where, $params] = $this->reportWhere($userId, $linkId, $from, $to);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM click_events ce WHERE $where");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UrlRepository;
use App\Security\Csrf;

final class AdminLinksController
{
    private const PER_PAGE = 20;
    private const GUEST_CLEANUP_DAYS = 15;

    public function __construct(
        private readonly UrlRepository $urls,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $admin = $this->guard();

        // Tự xoá link khách không chỉnh sửa quá 15 ngày.
        $cleaned = $this->urls->cleanupGuestLinks(self::GUEST_CLEANUP_DAYS);

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->urls->countAllForAdmin($search !== '' ? $search : null);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);

        $content = \App\render('admin-links', [
            'links'      => $this->urls->findAllForAdmin($search !== '' ? $search : null, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'search'     => $search,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'cleaned'    => $cleaned,
            'csrf'       => $this->csrf,
            'ok'         => isset($_GET['ok']) && $_GET['ok'] === '1',
            'error'      => isset($_GET['error']) ? (string) $_GET['error'] : null,
        ]);

        \App\render_admin_page($admin, 'Admin — Quản lý Link', 'links', $content);
    }

    public function toggle(int $id): never
    {
        $this->guard();
        $this->requireCsrf();
        $this->urls->toggleActive($id);
        \App\redirect(url_for('admin/links') . '?ok=1', 302);
    }

    public function update(int $id): never
    {
        $this->guard();
        $this->requireCsrf();

        $target = trim((string) ($_POST['target_url'] ?? ''));
        if (filter_var($target, FILTER_VALIDATE_URL) === false) {
            \App\redirect(url_for('admin/links') . '?error=' . rawurlencode('URL đích không hợp lệ.'), 302);
        }

        $endsAt = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['ends_at'] ?? '')) === 1) {
            $endsAt = $_POST['ends_at'] . ' 23:59:59';
        }

        $this->urls->updateByAdmin($id, [
            'target_url' => $target,
            'title'      => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'ends_at'    => $endsAt,
            'is_active'  => isset($_POST['is_active']) ? 1 : 0,
        ]);

        \App\redirect(url_for('admin/links') . '?ok=1', 302);
    }

    private function guard(): array
    {
        $admin = current_admin();
        if ($admin === null) {
            \App\redirect(url_for('admin/dang-nhap'), 302);
        }

        return $admin;
    }

    private function requireCsrf(): void
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('admin/links'), 302);
        }
    }
}

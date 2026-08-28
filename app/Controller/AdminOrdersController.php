<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\PackageRepository;
use App\Repository\UserRepository;
use App\Security\Csrf;

final class AdminOrdersController
{
    private const PER_PAGE = 20;
    private const STATUSES = ['pending', 'paid', 'canceled', 'failed'];

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly PackageRepository $packages,
        private readonly UserRepository $users,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $admin = $this->guard();

        $search = trim((string) ($_GET['q'] ?? ''));
        $status = ($_GET['status'] ?? '') !== '' ? (string) $_GET['status'] : null;
        if (!in_array((string) $status, self::STATUSES, true)) {
            $status = null;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->orders->countAllForAdmin($search !== '' ? $search : null, $status);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);

        $content = \App\render('admin-orders', [
            'mode'       => 'orders',
            'title'      => 'Danh sách đơn hàng',
            'orders'     => $this->orders->findAllForAdmin($search !== '' ? $search : null, $status, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'search'     => $search,
            'status'     => $status,
            'statuses'   => self::STATUSES,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'csrf'       => $this->csrf,
            'ok'         => isset($_GET['ok']) && $_GET['ok'] === '1',
            'error'      => isset($_GET['error']) ? (string) $_GET['error'] : null,
        ]);

        \App\render_admin_page($admin, 'Admin — Danh sách đơn hàng', 'orders', $content);
    }

    public function updateStatus(int $id): never
    {
        $this->guard();
        $this->requireCsrf();

        $order = $this->orders->findById($id);
        if ($order === null) {
            \App\redirect(url_for('admin/orders'), 302);
        }

        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            \App\redirect(url_for('admin/orders') . '?error=' . rawurlencode('Trạng thái không hợp lệ.'), 302);
        }

        $this->orders->markStatus($id, $status);
        if ($status === 'paid' && $order['status'] !== 'paid') {
            $this->orders->markPaid($id, (string) ($_POST['payer'] ?? ''));
            $this->activatePlan($order);
        }

        \App\redirect(url_for('admin/orders') . '?ok=1', 302);
    }

    private function activatePlan(array $order): void
    {
        $plan = $this->packages->findById((int) $order['plan_id']);
        if ($plan === null) {
            return;
        }
        $period = (string) ($plan['billing_period'] ?? 'monthly');
        $starts = date('Y-m-d 00:00:00');
        $ends = match ($period) {
            'yearly' => date('Y-m-d 00:00:00', strtotime('+1 year')),
            'lifetime' => null,
            default => date('Y-m-d 00:00:00', strtotime('+1 month')),
        };
        $this->users->setSubscription((int) $order['user_id'], (int) $plan['id'], $starts, $ends);
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
            \App\redirect(url_for('admin/orders'), 302);
        }
    }
}

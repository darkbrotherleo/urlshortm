<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\PackageRepository;
use App\Security\Csrf;

final class AdminPackagesController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly PackageRepository $packages,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $admin = $this->guard();

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->packages->countAll($search !== '' ? $search : null);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);

        $content = \App\render('admin-packages', [
            'packages'   => $this->packages->findAll($search !== '' ? $search : null, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'search'     => $search,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'csrf'       => $this->csrf,
            'ok'         => isset($_GET['ok']) && $_GET['ok'] === '1',
            'error'      => isset($_GET['error']) ? (string) $_GET['error'] : null,
        ]);

        \App\render_admin_page($admin, 'Admin — Quản lý gói dịch vụ', 'packages', $content);
    }

    public function createForm(): never
    {
        $admin = $this->guard();
        $this->renderForm($admin, null);
    }

    public function editForm(int $id): never
    {
        $admin = $this->guard();
        $plan = $this->packages->findById($id);
        if ($plan === null) {
            \App\redirect(url_for('admin/packages'), 302);
        }
        $this->renderForm($admin, $plan);
    }

    public function store(): never
    {
        $admin = $this->guard();
        $this->requireCsrf();

        $data = $this->normalize();
        $error = $this->validate($data, null);
        if ($error !== null) {
            $this->backWith($error);
        }
        if ($this->packages->findByCode($data['code']) !== null) {
            $this->backWith('Slug này đã tồn tại.');
        }

        $this->packages->create($data);
        \App\redirect(url_for('admin/packages') . '?ok=1', 302);
    }

    public function update(int $id): never
    {
        $admin = $this->guard();
        $this->requireCsrf();

        $plan = $this->packages->findById($id);
        if ($plan === null) {
            \App\redirect(url_for('admin/packages'), 302);
        }

        $data = $this->normalize();
        $error = $this->validate($data, $id);
        if ($error !== null) {
            $this->backWith($error);
        }
        $existing = $this->packages->findByCode($data['code']);
        if ($existing !== null && (int) $existing['id'] !== $id) {
            $this->backWith('Slug này đã tồn tại.');
        }

        $this->packages->update($id, $data);
        \App\redirect(url_for('admin/packages') . '?ok=1', 302);
    }

    public function delete(int $id): never
    {
        $this->guard();
        $this->requireCsrf();

        $plan = $this->packages->findById($id);
        if ($plan === null) {
            \App\redirect(url_for('admin/packages'), 302);
        }

        $inUse = $this->packages->activeSubscriptionCount($id);
        if ($inUse > 0) {
            $this->backWith("Gói này đang được {$inUse} user sử dụng — không thể xoá.");
        }

        $this->packages->delete($id);
        \App\redirect(url_for('admin/packages') . '?ok=1', 302);
    }

    public function toggle(int $id): never
    {
        $this->guard();
        $this->requireCsrf();

        $plan = $this->packages->findById($id);
        if ($plan !== null) {
            $this->packages->toggle($id);
        }

        \App\redirect(url_for('admin/packages') . '?ok=1', 302);
    }

    private function renderForm(array $admin, ?array $plan): never
    {
        $content = \App\render('admin-package-form', [
            'plan' => $plan,
            'csrf' => $this->csrf,
        ]);

        $title = $plan !== null ? 'Sửa gói dịch vụ' : 'Thêm gói dịch vụ';
        \App\render_admin_page($admin, 'Admin — ' . $title, 'packages', $content);
    }

    /**
     * @return array<string,mixed> dữ liệu đã chuẩn hoá
     */
    private function normalize(): array
    {
        $flags = ['has_analytics', 'has_qr_code', 'has_password_protection', 'has_link_expiration', 'has_utm_builder', 'has_api_access'];

        $data = [
            'code'                => strtolower(trim((string) ($_POST['code'] ?? ''))),
            'name'                => trim((string) ($_POST['name'] ?? '')),
            'description'         => trim((string) ($_POST['description'] ?? '')),
            'price'               => (float) ($_POST['price'] ?? 0),
            'currency'            => strtoupper(trim((string) ($_POST['currency'] ?? 'VND'))),
            'billing_period'      => (string) ($_POST['billing_period'] ?? 'monthly'),
            'max_links'           => (int) ($_POST['max_links'] ?? 0),
            'max_clicks'          => (int) ($_POST['max_clicks'] ?? 0),
            'max_custom_domains'  => (int) ($_POST['max_custom_domains'] ?? 0),
            'max_pixels'          => (int) ($_POST['max_pixels'] ?? 0),
            'max_users'           => (int) ($_POST['max_users'] ?? 1),
            'is_popular'          => isset($_POST['is_popular']) ? 1 : 0,
            'is_active'           => isset($_POST['is_active']) ? 1 : 0,
            'sort_order'          => (int) ($_POST['sort_order'] ?? 0),
        ];

        foreach ($flags as $flag) {
            $data[$flag] = isset($_POST[$flag]) ? 1 : 0;
        }

        $data['features'] = $this->buildFeatures($data);

        return $data;
    }

    /**
     * @param array<string,mixed> $d
     */
    private function buildFeatures(array $d): array
    {
        $features = [
            'max_links'          => $d['max_links'],
            'max_clicks'         => $d['max_clicks'],
            'max_custom_domains' => $d['max_custom_domains'],
            'max_pixels'         => $d['max_pixels'],
            'max_users'          => $d['max_users'],
        ];
        foreach (['has_analytics', 'has_qr_code', 'has_password_protection', 'has_link_expiration', 'has_utm_builder', 'has_api_access'] as $flag) {
            if ($d[$flag] === 1) {
                $features[str_replace('has_', '', $flag)] = true;
            }
        }

        return $features;
    }

    /**
     * @param array<string,mixed> $d
     */
    private function validate(array $d, ?int $ignoreId): ?string
    {
        if ($d['name'] === '' || mb_strlen($d['name'], 'UTF-8') > 100) {
            return 'Tên gói không hợp lệ (tối đa 100 ký tự).';
        }
        if (preg_match('/^[a-z0-9][a-z0-9\-]*$/', $d['code']) !== 1 || strlen($d['code']) > 100) {
            return 'Slug chỉ gồm chữ thường, số và dấu gạch ngang.';
        }
        if ($d['price'] < 0) {
            return 'Giá không được âm.';
        }
        if ($d['currency'] === '' || strlen($d['currency']) > 10) {
            return 'Đơn vị tiền không hợp lệ.';
        }
        if (!in_array($d['billing_period'], ['monthly', 'yearly', 'lifetime'], true)) {
            return 'Chu kỳ thanh toán không hợp lệ.';
        }
        foreach (['max_links', 'max_clicks', 'max_custom_domains', 'max_pixels', 'max_users'] as $key) {
            if ($d[$key] < -1) {
                return 'Giới hạn không hợp lệ (tối thiểu -1 = không giới hạn).';
            }
        }

        return null;
    }

    private function backWith(string $message): never
    {
        \App\redirect(url_for('admin/packages') . '?error=' . rawurlencode($message), 302);
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
            \App\redirect(url_for('admin/packages'), 302);
        }
    }
}

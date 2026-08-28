<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\VoucherRepository;
use App\Security\Csrf;

final class AdminVouchersController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly VoucherRepository $vouchers,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $admin = $this->guard();

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->vouchers->countAll($search !== '' ? $search : null);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);

        $content = \App\render('admin-vouchers', [
            'vouchers'   => $this->vouchers->findAll($search !== '' ? $search : null, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'search'     => $search,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'csrf'       => $this->csrf,
            'ok'         => isset($_GET['ok']) && $_GET['ok'] === '1',
            'error'      => isset($_GET['error']) ? (string) $_GET['error'] : null,
        ]);

        \App\render_admin_page($admin, 'Admin — Quản lý Voucher', 'vouchers', $content);
    }

    public function store(): never
    {
        $this->guard();
        $this->requireCsrf();

        $data = $this->normalize();
        $error = $this->validate($data);
        if ($error !== null) {
            $this->back($error);
        }
        if ($this->vouchers->findByCode($data['code']) !== null) {
            $this->back('Mã voucher này đã tồn tại.');
        }

        $this->vouchers->create($data);
        \App\redirect(url_for('admin/vouchers') . '?ok=1', 302);
    }

    public function update(int $id): never
    {
        $this->guard();
        $this->requireCsrf();

        $voucher = $this->vouchers->findById($id);
        if ($voucher === null) {
            \App\redirect(url_for('admin/vouchers'), 302);
        }

        $data = $this->normalize();
        $error = $this->validate($data);
        if ($error !== null) {
            $this->back($error);
        }
        $existing = $this->vouchers->findByCode($data['code']);
        if ($existing !== null && (int) $existing['id'] !== $id) {
            $this->back('Mã voucher này đã tồn tại.');
        }

        $this->vouchers->update($id, $data);
        \App\redirect(url_for('admin/vouchers') . '?ok=1', 302);
    }

    public function toggle(int $id): never
    {
        $this->guard();
        $this->requireCsrf();
        if ($this->vouchers->findById($id) !== null) {
            $this->vouchers->toggle($id);
        }
        \App\redirect(url_for('admin/vouchers') . '?ok=1', 302);
    }

    /**
     * @return array<string,mixed>
     */
    private function normalize(): array
    {
        return [
            'code'           => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'campaign_name'  => trim((string) ($_POST['campaign_name'] ?? '')),
            'discount_type'  => (string) ($_POST['discount_type'] ?? 'percent'),
            'discount_value' => (float) ($_POST['discount_value'] ?? 0),
            'usage_limit'    => max(1, (int) ($_POST['usage_limit'] ?? 1)),
            'per_user'       => (string) ($_POST['per_user'] ?? 'once'),
            'starts_at'      => $this->dateOrNull($_POST['starts_at'] ?? ''),
            'ends_at'        => $this->dateOrNull($_POST['ends_at'] ?? ''),
            'note'           => trim((string) ($_POST['note'] ?? '')),
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        ];
    }

    /**
     * @param array<string,mixed> $d
     */
    private function validate(array $d): ?string
    {
        if (preg_match('/^[A-Z0-9][A-Z0-9\-]{1,49}$/', $d['code']) !== 1) {
            return 'Mã voucher chỉ gồm chữ in hoa, số và dấu gạch ngang (2-50 ký tự).';
        }
        if (!in_array($d['discount_type'], ['percent', 'fixed'], true)) {
            return 'Hình thức giảm không hợp lệ.';
        }
        if ($d['discount_value'] <= 0) {
            return 'Giá trị giảm phải lớn hơn 0.';
        }
        if ($d['discount_type'] === 'percent' && $d['discount_value'] > 100) {
            return 'Giảm theo % tối đa 100%.';
        }
        if (!in_array($d['per_user'], ['once', 'multiple'], true)) {
            return 'Áp dụng cho khách không hợp lệ.';
        }
        if (mb_strlen($d['campaign_name'], 'UTF-8') > 190) {
            return 'Tên chiến dịch quá dài.';
        }
        if ($d['starts_at'] !== null && $d['ends_at'] !== null && $d['ends_at'] < $d['starts_at']) {
            return 'Ngày kết thúc phải sau ngày bắt đầu.';
        }

        return null;
    }

    private function dateOrNull(string $value): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value . ' 00:00:00';
    }

    private function back(string $message): never
    {
        \App\redirect(url_for('admin/vouchers') . '?error=' . rawurlencode($message), 302);
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
            \App\redirect(url_for('admin/vouchers'), 302);
        }
    }
}

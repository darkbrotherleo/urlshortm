<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\Csrf;

final class AdminUsersController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $admin = current_admin();
        if ($admin === null) {
            \App\redirect(url_for('admin/dang-nhap'), 302);
        }

        $content = \App\render('admin-users', [
            'users' => $this->users->findAllForAdmin(),
            'plans' => $this->users->plansAll(),
            'csrf'  => $this->csrf,
            'ok'    => isset($_GET['ok']) && $_GET['ok'] === '1',
            'error' => isset($_GET['error']) ? (string) $_GET['error'] : null,
        ]);

        \App\render_admin_page($admin, 'Admin — Quản lý người dùng', 'users', $content);
    }

    public function update(): never
    {
        $admin = current_admin();
        if ($admin === null) {
            \App\redirect(url_for('admin/dang-nhap'), 302);
        }
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('admin/users'), 302);
        }

        $id = (int) ($_POST['user_id'] ?? 0);
        $user = $this->users->findById($id);
        if ($user === null) {
            \App\redirect(url_for('admin/users'), 302);
        }

        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        if (mb_strlen($displayName, 'UTF-8') > 100) {
            $this->back('Tên hiển thị quá dài (tối đa 100 ký tự).');
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->back('Email không hợp lệ.');
        }
        $existing = $this->users->findByEmail($email);
        if ($existing !== null && (int) $existing['id'] !== $id) {
            $this->back('Email này đã được sử dụng bởi user khác.');
        }

        $phone = trim((string) ($_POST['phone'] ?? ''));
        if ($phone !== '' && preg_match('/^\+?\d{9,15}$/', preg_replace('/[\s\-()]/', '', $phone) ?? '') !== 1) {
            $this->back('Số điện thoại không hợp lệ.');
        }

        $taxType = (string) ($_POST['tax_type'] ?? '');
        if (!in_array($taxType, ['', 'individual', 'business'], true)) {
            $this->back('Loại khách hàng không hợp lệ.');
        }
        $taxId = trim((string) ($_POST['tax_id'] ?? ''));
        $taxDigits = preg_replace('/[\s\-.]/', '', $taxId) ?? '';
        if ($taxId !== '' && (preg_match('/^\d+$/', $taxDigits) !== 1 || strlen($taxDigits) < 10 || strlen($taxDigits) > 14)) {
            $this->back('Mã số thuế không hợp lệ (10-14 chữ số).');
        }

        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['active', 'disabled'], true)) {
            $this->back('Trạng thái không hợp lệ.');
        }

        $planId = (isset($_POST['plan_id']) && ctype_digit((string) $_POST['plan_id'])) ? (int) $_POST['plan_id'] : 0;
        $starts = $this->dateOrNull($_POST['sub_start'] ?? '');
        $ends = $this->dateOrNull($_POST['sub_end'] ?? '');
        if ($starts !== null && $ends !== null && $ends < $starts) {
            $this->back('Ngày hết hạn phải sau ngày mua.');
        }

        $this->users->updateDisplayName($id, $displayName);
        $this->users->updateEmail($id, $email);
        $this->users->updateProfile($id, [
            'phone'        => $phone,
            'address'      => trim((string) ($_POST['address'] ?? '')),
            'city'         => trim((string) ($_POST['city'] ?? '')),
            'tax_type'     => $taxType,
            'company_name' => trim((string) ($_POST['company_name'] ?? '')),
            'tax_id'       => $taxId,
            'invoice_name' => trim((string) ($_POST['invoice_name'] ?? '')),
        ]);
        $this->users->setStatus($id, $status);
        $this->users->setSubscription($id, $planId > 0 ? $planId : null, $starts, $ends);

        \App\redirect(url_for('admin/users') . '?ok=1', 302);
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
        \App\redirect(url_for('admin/users') . '?error=' . rawurlencode($message), 302);
    }
}

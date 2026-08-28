<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\SettingRepository;
use App\Security\Csrf;

final class AdminPaymentsController
{
    public function __construct(
        private readonly SettingRepository $settings,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $admin = current_admin();
        if ($admin === null) {
            \App\redirect(url_for('admin/dang-nhap'), 302);
        }

        $content = \App\render('admin-payments', [
            'paypal' => [
                'client_id' => (string) ($this->settings->get('paypal_client_id') ?? ''),
                'secret'    => (string) ($this->settings->get('paypal_secret') ?? ''),
                'mode'      => (string) ($this->settings->get('paypal_mode') ?? 'sandbox'),
            ],
            'csrf'  => $this->csrf,
            'ok'    => isset($_GET['ok']) && $_GET['ok'] === '1',
            'error' => isset($_GET['error']) ? (string) $_GET['error'] : null,
        ]);

        \App\render_admin_page($admin, 'Admin — Cổng thanh toán', 'payments', $content);
    }

    public function save(): never
    {
        $admin = current_admin();
        if ($admin === null) {
            \App\redirect(url_for('admin/dang-nhap'), 302);
        }
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('admin/payments'), 302);
        }

        $clientId = trim((string) ($_POST['paypal_client_id'] ?? ''));
        $secret = trim((string) ($_POST['paypal_secret'] ?? ''));
        $mode = (string) ($_POST['paypal_mode'] ?? 'sandbox');
        if (!in_array($mode, ['sandbox', 'live'], true)) {
            $mode = 'sandbox';
        }

        $this->settings->set('paypal_client_id', $clientId);
        if ($secret !== '') {
            $this->settings->set('paypal_secret', $secret);
        }
        $this->settings->set('paypal_mode', $mode);

        \App\redirect(url_for('admin/payments') . '?ok=1', 302);
    }
}

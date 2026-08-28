<?php
declare(strict_types=1);

namespace App\Controller;

use App\Security\Csrf;
use App\Service\EmailTemplates;

final class AdminEmailsController
{
    public function __construct(
        private readonly EmailTemplates $templates,
        private readonly \App\Service\Mailer $mailer,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $admin = current_admin();
        if ($admin === null) {
            \App\redirect(url_for('admin/dang-nhap'), 302);
        }

        $previews = [];
        foreach ($this->templates->types() as $key => $label) {
            $previews[$key] = ['label' => $label, 'html' => $this->sample($key)['html']];
        }

        $content = \App\render('admin-emails', [
            'previews' => $previews,
            'csrf'     => $this->csrf,
            'ok'       => isset($_GET['ok']) && $_GET['ok'] === '1',
            'error'    => isset($_GET['error']) ? (string) $_GET['error'] : null,
            'configured' => $this->mailer->isConfigured(),
        ]);

        \App\render_admin_page($admin, 'Admin — Email Template', 'emails', $content);
    }

    public function sendTest(): never
    {
        $admin = current_admin();
        if ($admin === null) {
            \App\redirect(url_for('admin/dang-nhap'), 302);
        }
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('admin/emails'), 302);
        }

        $type = (string) ($_POST['template'] ?? '');
        $to = trim((string) ($_POST['test_to'] ?? ''));
        if (!isset($this->templates->types()[$type])) {
            \App\redirect(url_for('admin/emails') . '?error=' . rawurlencode('Template không hợp lệ.'), 302);
        }

        $mail = $this->sample($type);
        try {
            $this->mailer->send($to, $mail['subject'], $mail['html'], true);
            \App\redirect(url_for('admin/emails') . '?ok=1', 302);
        } catch (\RuntimeException $e) {
            \App\redirect(url_for('admin/emails') . '?error=' . rawurlencode('Gửi thử thất bại: ' . $e->getMessage()), 302);
        }
    }

    /**
     * @return array{subject:string,html:string}
     */
    private function sample(string $type): array
    {
        $base = rtrim(\App\base_url(), '/');
        $data = [
            'name' => 'Nguyễn Văn Demo',
            'email' => 'demo@example.com',
            'plan_name' => 'Pro',
            'amount' => '399.000 ₫',
            'order_code' => 'DH-ABC12345',
            'paid_at' => date('d/m/Y H:i'),
            'invoice_no' => '0000123',
            'dashboard_link' => $base . '/dashboard',
            'invoice_link' => $base . '/hoa-don/DH-ABC12345',
            'login_link' => $base . '/dang-nhap',
            'reset_link' => $base . '/dang-nhap',
            'activation_link' => $base . '/dang-nhap',
        ];

        return $this->templates->render($type, $data);
    }
}

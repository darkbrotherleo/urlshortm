<?php
declare(strict_types=1);

namespace App\Controller;

use App\Security\Csrf;
use App\Service\AdminAuthService;
use App\Service\AuthException;

final class AdminController
{
    public function __construct(
        private readonly AdminAuthService $auth,
        private readonly Csrf $csrf
    ) {
    }

    public function showLogin(): never
    {
        if (current_admin() !== null) {
            \App\redirect(url_for('admin'), 302);
        }

        $this->renderLogin(['email' => ''], null, 200);
    }

    public function login(): never
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            $this->renderLogin(
                ['email' => trim((string) ($_POST['email'] ?? ''))],
                'Phiên làm việc đã hết hạn, vui lòng thử lại.',
                403
            );
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        try {
            $this->auth->login($email, (string) ($_POST['password'] ?? ''), $ip);
            \App\redirect(url_for('admin'), 302);
        } catch (AuthException $e) {
            $this->renderLogin(['email' => $email], $e->getMessage(), $this->statusFor($e));
        }
    }

    public function logout(): never
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('admin'), 302);
        }

        $this->auth->logout();
        \App\redirect(url_for('admin/dang-nhap'), 302);
    }

    private function renderLogin(array $values, ?string $error, int $status): never
    {
        http_response_code($status);
        echo \App\render('admin-login', [
            'title'  => 'Admin đăng nhập',
            'values' => $values,
            'error'  => $error,
            'csrf'   => $this->csrf,
        ]);
        exit;
    }

    private function statusFor(AuthException $e): int
    {
        return match ($e->reason()) {
            AuthException::RATE_LIMITED => 429,
            AuthException::INVALID_CREDENTIALS => 401,
            default => 400,
        };
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Security\Csrf;
use App\Service\AuthException;
use App\Service\AuthService;

final class AuthController
{
    public function __construct(
        private readonly AuthService $service,
        private readonly Csrf $csrf
    ) {
    }

    public function showRegister(): never
    {
        $this->render('auth-register', [
            'title'  => 'Tạo tài khoản',
            'values' => ['name' => '', 'email' => ''],
            'error'  => null,
        ]);
    }

    public function register(): never
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            $this->render('auth-register', [
                'title'  => 'Tạo tài khoản',
                'values' => $this->inputValues(),
                'error'  => 'Phiên làm việc đã hết hạn, vui lòng thử lại.',
            ], 403);
        }

        $values = $this->inputValues();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        try {
            $this->service->register(
                $values['email'],
                $values['password'],
                $_POST['password_confirm'] ?? '',
                $values['name'],
                $ip
            );

            \App\redirect(url_for('dashboard'), 302);
        } catch (AuthException $e) {
            $this->render('auth-register', [
                'title'  => 'Tạo tài khoản',
                'values' => $values,
                'error'  => $e->getMessage(),
            ], $this->statusFor($e));
        }
    }

    public function showLogin(): never
    {
        $this->render('auth-login', [
            'title'  => 'Đăng nhập',
            'values' => ['email' => ''],
            'error'  => null,
        ]);
    }

    public function login(): never
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            $this->render('auth-login', [
                'title'  => 'Đăng nhập',
                'values' => ['email' => trim((string) ($_POST['email'] ?? ''))],
                'error'  => 'Phiên làm việc đã hết hạn, vui lòng thử lại.',
            ], 403);
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        try {
            $this->service->login($email, (string) ($_POST['password'] ?? ''), $ip);

            \App\redirect(url_for('dashboard'), 302);
        } catch (AuthException $e) {
            $this->render('auth-login', [
                'title'  => 'Đăng nhập',
                'values' => ['email' => $email],
                'error'  => $e->getMessage(),
            ], $this->statusFor($e));
        }
    }

    public function logout(): never
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('/'), 302);
        }

        $this->service->logout();

        \App\redirect(url_for('/'), 302);
    }

    private function inputValues(): array
    {
        return [
            'name'     => trim((string) ($_POST['name'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
        ];
    }

    private function statusFor(AuthException $e): int
    {
        return match ($e->reason()) {
            AuthException::EMAIL_EXISTS => 409,
            AuthException::RATE_LIMITED => 429,
            AuthException::INVALID_CREDENTIALS => 401,
            default => 400,
        };
    }

    private function render(string $view, array $data, int $status = 200): never
    {
        $data['csrf'] = $this->csrf;
        http_response_code($status);
        echo \App\render($view, $data);
        exit;
    }
}

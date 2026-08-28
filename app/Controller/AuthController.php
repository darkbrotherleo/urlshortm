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

            // Tài khoản PENDING — chờ kích hoạt qua email.
            $this->render('auth-activate-sent', [
                'title'  => 'Kiểm tra email kích hoạt',
                'email'  => $values['email'],
                'error'  => null,
            ], 200);
        } catch (AuthException $e) {
            $this->render('auth-register', [
                'title'  => 'Tạo tài khoản',
                'values' => $values,
                'error'  => $e->getMessage(),
            ], $this->statusFor($e));
        }
    }

    public function activate(): never
    {
        $token = (string) ($_GET['token'] ?? '');
        try {
            $this->service->activate($token);
            \App\redirect(url_for('dashboard') . '?tab=tai-khoan&activated=1', 302);
        } catch (AuthException $e) {
            $this->render('auth-activate-sent', [
                'title' => 'Kích hoạt tài khoản',
                'email' => '',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function showForgot(): never
    {
        $this->render('auth-forgot', [
            'title'  => 'Quên mật khẩu',
            'values' => ['email' => ''],
            'error'  => null,
            'sent'   => false,
        ]);
    }

    public function requestReset(): never
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            $this->render('auth-forgot', [
                'title'  => 'Quên mật khẩu',
                'values' => ['email' => trim((string) ($_POST['email'] ?? ''))],
                'error'  => 'Phiên làm việc đã hết hạn, vui lòng thử lại.',
                'sent'   => false,
            ], 403);
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->render('auth-forgot', [
                'title'  => 'Quên mật khẩu',
                'values' => ['email' => $email],
                'error'  => 'Email không hợp lệ.',
                'sent'   => false,
            ], 400);
        }

        $this->service->requestPasswordReset($email);

        // Luôn hiện thông báo chung (không tiết lộ email tồn tại hay không).
        $this->render('auth-forgot', [
            'title'  => 'Quên mật khẩu',
            'values' => ['email' => $email],
            'error'  => null,
            'sent'   => true,
        ]);
    }

    public function showReset(): never
    {
        $token = (string) ($_GET['token'] ?? '');
        if ($token === '' || !$this->service->resetTokenValid($token)) {
            $this->render('auth-reset', [
                'title' => 'Đặt lại mật khẩu',
                'token' => '',
                'error' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn (hiệu lực 30 phút).',
            ], 400);
        }

        $this->render('auth-reset', [
            'title' => 'Đặt lại mật khẩu',
            'token' => $token,
            'error' => null,
        ]);
    }

    public function doReset(): never
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            $this->render('auth-reset', [
                'title' => 'Đặt lại mật khẩu',
                'token' => (string) ($_POST['token'] ?? ''),
                'error' => 'Phiên làm việc đã hết hạn, vui lòng thử lại.',
            ], 403);
        }

        $token = (string) ($_POST['token'] ?? '');
        try {
            $this->service->resetPassword(
                $token,
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password_confirm'] ?? '')
            );
            $this->render('auth-reset', [
                'title' => 'Đặt lại mật khẩu',
                'token' => '',
                'error' => null,
                'done'  => true,
            ]);
        } catch (AuthException $e) {
            $this->render('auth-reset', [
                'title' => 'Đặt lại mật khẩu',
                'token' => $token,
                'error' => $e->getMessage(),
            ], $this->statusFor($e));
        }
    }

    public function showLogin(): never
    {
        $error = null;
        if (($_GET['disabled'] ?? '') === '1') {
            $error = 'Tài khoản của bạn đã được vô hiệu hoá. Dữ liệu vẫn được giữ lại; liên hệ hỗ trợ nếu muốn mở lại.';
        }

        $this->render('auth-login', [
            'title'  => 'Đăng nhập',
            'values' => ['email' => ''],
            'error'  => $error,
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

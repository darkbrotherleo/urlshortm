<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use App\Security\RateLimiter;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    /**
     * @return array{id:int,email:string,display_name:?string,status:string} user vừa đăng ký
     *
     * @throws AuthException INVALID_INPUT / EMAIL_EXISTS / RATE_LIMITED
     */
    public function register(string $email, string $password, string $passwordConfirm, ?string $displayName, string $ip): array
    {
        $email = strtolower(trim($email));
        $displayName = trim((string) $displayName);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new AuthException('Email không hợp lệ.', AuthException::INVALID_INPUT);
        }
        if (strlen($password) < 8) {
            throw new AuthException('Mật khẩu phải có ít nhất 8 ký tự.', AuthException::INVALID_INPUT);
        }
        if (!hash_equals($password, $passwordConfirm)) {
            throw new AuthException('Mật khẩu nhập lại không khớp.', AuthException::INVALID_INPUT);
        }
        if (mb_strlen($displayName, 'UTF-8') > 100) {
            throw new AuthException('Tên hiển thị quá dài (tối đa 100 ký tự).', AuthException::INVALID_INPUT);
        }
        if (!$this->rateLimiter->allow('register', $ip, 10, 3600)) {
            throw new AuthException('Quá nhiều yêu cầu, vui lòng thử lại sau một giờ.', AuthException::RATE_LIMITED);
        }

        if ($this->repository->findByEmail($email) !== null) {
            throw new AuthException('Email này đã được đăng ký.', AuthException::EMAIL_EXISTS);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $id = $this->repository->insert($email, $hash, $displayName);
        } catch (\PDOException $e) {
            if (str_starts_with((string) $e->getCode(), '23')) {
                throw new AuthException('Email này đã được đăng ký.', AuthException::EMAIL_EXISTS);
            }
            throw $e;
        }

        // Tài khoản mới mặc định gắn gói Free + giữ ở trạng thái PENDING (chờ kích hoạt).
        $this->repository->subscribeToFreePlan((int) $id);

        $token = bin2hex(random_bytes(24));
        $this->repository->setStatus((int) $id, 'pending');
        $this->repository->setActivation((int) $id, $token, date('Y-m-d H:i:s', strtotime('+24 hours')));

        $this->sendActivationEmail($email, $displayName !== '' ? $displayName : $email, $token);

        // KHÔNG tự đăng nhập — chờ khách kích hoạt qua email.
        return $this->repository->findById((int) $id);
    }

    /**
     * Kích hoạt tài khoản qua token trong email.
     *
     * @return array{id:int,email:string,display_name:?string} user đã kích hoạt (tự đăng nhập)
     *
     * @throws AuthException INVALID_INPUT khi token sai/hết hạn
     */
    public function activate(string $token): array
    {
        $user = $this->repository->findByActivationToken($token);
        if ($user === null || empty($user['activation_expires_at'])) {
            throw new AuthException('Liên kết kích hoạt không hợp lệ.', AuthException::INVALID_INPUT);
        }
        if (strtotime((string) $user['activation_expires_at']) < time()) {
            throw new AuthException('Liên kết kích hoạt đã hết hạn. Vui lòng đăng ký lại hoặc liên hệ hỗ trợ.', AuthException::INVALID_INPUT);
        }

        $this->repository->activate((int) $user['id']);
        $this->repository->subscribeToFreePlan((int) $user['id']);
        $this->startSession((int) $user['id']);

        return $this->repository->findById((int) $user['id']);
    }

    /**
     * Gửi email đặt lại mật khẩu (token 30 phút). Không báo lỗi nếu email không tồn tại.
     */
    public function requestPasswordReset(string $email): void
    {
        $email = strtolower(trim($email));
        $user = $this->repository->findByEmail($email);
        if ($user === null || $user['status'] === 'disabled') {
            return;
        }

        $token = bin2hex(random_bytes(24));
        $this->repository->setResetToken((int) $user['id'], $token, date('Y-m-d H:i:s', strtotime('+30 minutes')));

        $this->sendResetEmail((string) $user['email'], (string) ($user['display_name'] ?: $user['email']), $token);
    }

    public function resetTokenValid(string $token): bool
    {
        $user = $this->repository->findByResetToken($token);
        if ($user === null || empty($user['reset_expires_at'])) {
            return false;
        }

        return strtotime((string) $user['reset_expires_at']) >= time();
    }

    /**
     * @throws AuthException INVALID_INPUT khi token sai/hết hạn hoặc mật khẩu không hợp lệ
     */
    public function resetPassword(string $token, string $new, string $confirm): void
    {
        $user = $this->repository->findByResetToken($token);
        if ($user === null || empty($user['reset_expires_at']) || strtotime((string) $user['reset_expires_at']) < time()) {
            throw new AuthException('Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn (30 phút).', AuthException::INVALID_INPUT);
        }
        if (strlen($new) < 8) {
            throw new AuthException('Mật khẩu mới phải có ít nhất 8 ký tự.', AuthException::INVALID_INPUT);
        }
        if (!hash_equals($new, $confirm)) {
            throw new AuthException('Mật khẩu mới nhập lại không khớp.', AuthException::INVALID_INPUT);
        }

        $this->repository->updatePassword((int) $user['id'], password_hash($new, PASSWORD_DEFAULT));
        $this->repository->clearResetToken((int) $user['id']);
    }

    private function sendActivationEmail(string $email, string $name, string $token): void
    {
        try {
            $c = \App\Container::getInstance();
            $mailer = $c->mailer();
            if (!$mailer->isConfigured()) {
                return;
            }
            $mail = $c->emailTemplates()->render('activate_account', [
                'name' => $name,
                'activation_link' => rtrim(\App\base_url(), '/') . '/kich-hoat?token=' . rawurlencode($token),
            ]);
            $mailer->send($email, $mail['subject'], $mail['html'], true);
        } catch (\Throwable) {
            // Lỗi gửi email không làm hỏng đăng ký.
        }
    }

    private function sendResetEmail(string $email, string $name, string $token): void
    {
        try {
            $c = \App\Container::getInstance();
            $mailer = $c->mailer();
            if (!$mailer->isConfigured()) {
                return;
            }
            $mail = $c->emailTemplates()->render('forgot_password', [
                'name' => $name,
                'reset_link' => rtrim(\App\base_url(), '/') . '/dat-lai-mat-khau?token=' . rawurlencode($token),
            ]);
            $mailer->send($email, $mail['subject'], $mail['html'], true);
        } catch (\Throwable) {
            // bỏ qua
        }
    }

    /**
     * @return array{id:int,email:string,display_name:?string,status:string} user đã đăng nhập
     *
     * @throws AuthException INVALID_CREDENTIALS / RATE_LIMITED / ACCOUNT_DISABLED
     */
    public function login(string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));

        if (!$this->rateLimiter->allow('login', $ip, 10, 3600)) {
            throw new AuthException('Quá nhiều lần thử, vui lòng thử lại sau một giờ.', AuthException::RATE_LIMITED);
        }

        $user = $this->repository->findByEmail($email);

        // Giới hạn theo email để tránh dò tài khoản.
        if ($user !== null && !$this->rateLimiter->allow('login', $email, 10, 3600)) {
            throw new AuthException('Quá nhiều lần thử, vui lòng thử lại sau một giờ.', AuthException::RATE_LIMITED);
        }

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new AuthException('Email hoặc mật khẩu không đúng.', AuthException::INVALID_CREDENTIALS);
        }

        if ($user['status'] === 'pending') {
            throw new AuthException('Tài khoản chưa được kích hoạt. Hãy kiểm tra email để kích hoạt tài khoản.', AuthException::ACCOUNT_DISABLED);
        }
        if ($user['status'] !== 'active') {
            throw new AuthException('Tài khoản đang bị khoá, liên hệ hỗ trợ để được mở lại.', AuthException::ACCOUNT_DISABLED);
        }

        $this->repository->updateLastLogin((int) $user['id']);
        $this->startSession((int) $user['id']);

        return $this->repository->findById((int) $user['id']);
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies') && !headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
    }

    /**
     * @throws AuthException INVALID_INPUT (mật khẩu cũ sai / mới ngắn / nhập lại không khớp)
     */
    public function changePassword(int $userId, string $current, string $new, string $confirm): void
    {
        $hash = $this->repository->passwordHashOf($userId);
        if ($hash === null || !password_verify($current, $hash)) {
            throw new AuthException('Mật khẩu hiện tại không đúng.', AuthException::INVALID_INPUT);
        }
        if (strlen($new) < 8) {
            throw new AuthException('Mật khẩu mới phải có ít nhất 8 ký tự.', AuthException::INVALID_INPUT);
        }
        if (!hash_equals($new, $confirm)) {
            throw new AuthException('Mật khẩu mới nhập lại không khớp.', AuthException::INVALID_INPUT);
        }
        if (hash_equals($current, $new)) {
            throw new AuthException('Mật khẩu mới phải khác mật khẩu hiện tại.', AuthException::INVALID_INPUT);
        }

        $this->repository->updatePassword($userId, password_hash($new, PASSWORD_DEFAULT));
    }

    /**
     * Soft delete: vô hiệu hoá tài khoản (status=disabled) — KHÔNG xoá dữ liệu.
     *
     * @throws AuthException INVALID_INPUT khi mật khẩu hiện tại sai
     */
    public function deactivate(int $userId, string $current): void
    {
        $hash = $this->repository->passwordHashOf($userId);
        if ($hash === null || !password_verify($current, $hash)) {
            throw new AuthException('Mật khẩu không đúng.', AuthException::INVALID_INPUT);
        }

        $this->repository->deactivate($userId);
    }

    private function startSession(int $userId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
    }
}

<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\AdminRepository;
use App\Security\RateLimiter;

final class AdminAuthService
{
    public function __construct(
        private readonly AdminRepository $repository,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    /**
     * @return array{id:int,email:string,display_name:?string,role:string,status:string} admin đã đăng nhập
     *
     * @throws AuthException INVALID_CREDENTIALS / RATE_LIMITED / ACCOUNT_DISABLED
     */
    public function login(string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));

        if (!$this->rateLimiter->allow('admin_login', $ip, 10, 3600)) {
            throw new AuthException('Quá nhiều lần thử, vui lòng thử lại sau một giờ.', AuthException::RATE_LIMITED);
        }

        $admin = $this->repository->findByEmail($email);
        if ($admin === null || !password_verify($password, $admin['password_hash'])) {
            throw new AuthException('Email hoặc mật khẩu không đúng.', AuthException::INVALID_CREDENTIALS);
        }

        if ($admin['status'] !== 'active') {
            throw new AuthException('Tài khoản quản trị đang bị khoá.', AuthException::ACCOUNT_DISABLED);
        }

        $this->repository->updateLastLogin((int) $admin['id']);
        $this->startSession((int) $admin['id']);

        return $this->repository->findById((int) $admin['id']);
    }

    public function logout(): void
    {
        unset($_SESSION['admin_id']);

        if (session_status() === PHP_SESSION_ACTIVE && count($_SESSION) === 0 && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            session_destroy();
        }
    }

    private function startSession(int $adminId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['admin_id'] = $adminId;
    }
}

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

        $this->startSession((int) $id);

        return $this->repository->findById((int) $id);
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

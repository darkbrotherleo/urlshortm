<?php
declare(strict_types=1);

namespace App\Security;

final class Csrf
{
    public function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public function verify(?string $token): bool
    {
        $expected = $_SESSION['csrf_token'] ?? null;
        if ($expected === null || $token === null) {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public function field(): string
    {
        $token = $this->token();

        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

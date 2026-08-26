<?php
declare(strict_types=1);

namespace App\Service;

final class AuthException extends \RuntimeException
{
    public const INVALID_INPUT = 'invalid_input';
    public const EMAIL_EXISTS = 'email_exists';
    public const INVALID_CREDENTIALS = 'invalid_credentials';
    public const RATE_LIMITED = 'rate_limited';
    public const ACCOUNT_DISABLED = 'account_disabled';

    public function __construct(string $message, private readonly string $code_)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->code_;
    }
}

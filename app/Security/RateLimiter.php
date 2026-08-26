<?php
declare(strict_types=1);

namespace App\Security;

use App\Repository\RateLimitRepository;

class RateLimiter
{
    public function __construct(
        private readonly RateLimitRepository $repository
    ) {
    }

    /**
     * Tăng counter và kiểm tra có nằm trong ngưỡng cho phép không.
     *
     * @return bool true nếu còn trong ngưỡng (được phép), false nếu đã vượt.
     */
    public function allow(string $key, string $ip, int $limit, int $windowSeconds): bool
    {
        $ipHash = hash('sha256', $ip);
        $windowStart = (int) floor(time() / $windowSeconds) * $windowSeconds;

        $count = $this->repository->increment($ipHash, $key, $windowStart);

        return $count <= $limit;
    }
}

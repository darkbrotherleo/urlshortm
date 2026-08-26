<?php
declare(strict_types=1);

namespace App\Security;

use App\Router;

final class SlugValidator
{
    public function isValid(string $slug): bool
    {
        return preg_match(Router::SLUG_PATTERN, $slug) === 1;
    }

    public function isReserved(string $slug): bool
    {
        return in_array($slug, Router::RESERVED, true);
    }
}

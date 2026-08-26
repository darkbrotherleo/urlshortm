<?php
declare(strict_types=1);

namespace App\Security;

final class SlugGenerator
{
    /**
     * @param string      $charset ký tự dùng để sinh slug (base62 mặc định)
     * @param positive-int $length  độ dài slug
     */
    public function generate(string $charset = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz', int $length = 6): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Slug length must be >= 1');
        }
        if ($charset === '') {
            throw new \InvalidArgumentException('Charset must not be empty');
        }

        $max = strlen($charset) - 1;
        $slug = '';

        for ($i = 0; $i < $length; $i++) {
            $slug .= $charset[random_int(0, $max)];
        }

        return $slug;
    }

    public function isValid(string $slug, int $length = 6): bool
    {
        return preg_match('/^[0-9a-zA-Z]{' . $length . '}$/', $slug) === 1;
    }
}

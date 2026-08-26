<?php
declare(strict_types=1);

namespace App;

final class Config
{
    private static array $config = [];

    public static function load(array $config): void
    {
        self::$config = $config;
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

function config(string $path, mixed $default = null): mixed
{
    return Config::get($path, $default);
}

function escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));

    return rtrim($scheme . '://' . $host . $base, '/');
}

function url_for(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $location, int $status = 302): never
{
    http_response_code($status);
    header('Location: ' . $location);
    exit;
}

function render(string $template, array $data = []): string
{
    $appDir = __DIR__;
    $file   = $appDir . '/View/' . $template . '.php';

    if (!is_file($file)) {
        throw new \RuntimeException("View not found: {$template}");
    }

    extract($data, EXTR_SKIP);

    ob_start();
    include $file;

    return (string) ob_get_clean();
}

/**
 * @return array{id:int,email:string,display_name:?string,status:string}|null user đang đăng nhập
 */
function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    try {
        return Container::getInstance()->userRepository()->findById((int) $id);
    } catch (\Throwable) {
        return null;
    }
}

function csrf_field(): string
{
    return (new \App\Security\Csrf())->field();
}

/**
 * URL ngắn cho link (dùng domain tuỳ chỉnh nếu có, ngược lại base local).
 *
 * @param array<string,mixed> $link
 */
function short_url_for(array $link): string
{
    $domain = $link['domain'] ?? null;
    if ($domain !== null && $domain !== '' && $domain !== 'local') {
        $base = 'https://' . $domain;
    } else {
        $base = base_url();
    }

    return rtrim($base, '/') . '/' . $link['slug'];
}

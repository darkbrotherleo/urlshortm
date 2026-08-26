<?php
declare(strict_types=1);

namespace App;

final class Router
{
    /** Slug: 3-16 kÃ½ tá»± [0-9a-zA-Z-_] (cho phÃ©p custom back-half). */
    public const SLUG_PATTERN = '/^[0-9a-zA-Z\-_]{3,16}$/';

    /** ÄÆ°á»ng dáº«n dÃ nh riÃªng, khÃ´ng pháº£i slug. */
    public const RESERVED = [
        'shorten', 'assets', 'index', 'app', 'tests', 'database',
        'config', 'scripts', 'robots', 'favicon', 'stats',
        'dang-ky', 'dang-nhap', 'dang-xuat', 'dashboard',
        'link', 'folder', 'new', 'edit', 'bulk', 'settings', 'unlock',
    ];

    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';

    /**
     * @return array{handler:string, params:array<string,mixed>}
     */
    public function match(string $method, string $path): array
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        if ($method === self::METHOD_GET && $path === '/') {
            return ['handler' => 'home', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/shorten') {
            return ['handler' => 'shorten', 'params' => []];
        }

        if ($method === self::METHOD_GET && preg_match('#^/stats/([0-9a-zA-Z\-_]+)$#', $path, $m) === 1) {
            return ['handler' => 'stats', 'params' => ['slug' => $m[1]]];
        }

        if ($path === '/dang-ky' && ($method === self::METHOD_GET || $method === self::METHOD_POST)) {
            return ['handler' => 'register', 'params' => []];
        }

        if ($path === '/dang-nhap' && ($method === self::METHOD_GET || $method === self::METHOD_POST)) {
            return ['handler' => 'login', 'params' => []];
        }

        if ($path === '/dang-xuat' && $method === self::METHOD_POST) {
            return ['handler' => 'logout', 'params' => []];
        }

        if ($method === self::METHOD_GET && $path === '/dashboard') {
            return ['handler' => 'dashboard', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/folder/create') {
            return ['handler' => 'folder_create', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/folder/delete') {
            return ['handler' => 'folder_delete', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/link-folder') {
            return ['handler' => 'link_folder', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/settings') {
            return ['handler' => 'settings_update', 'params' => []];
        }

        if ($method === self::METHOD_POST && preg_match('#^/([0-9a-zA-Z\-_]{3,16})/unlock$#', $path, $m) === 1) {
            return ['handler' => 'unlock', 'params' => ['slug' => $m[1]]];
        }

        if ($method === self::METHOD_GET && $path === '/dashboard/link/new') {
            return ['handler' => 'link_new', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/link') {
            return ['handler' => 'link_store', 'params' => []];
        }

        if ($method === self::METHOD_POST && $path === '/dashboard/link/bulk') {
            return ['handler' => 'link_bulk', 'params' => []];
        }

        if ($method === self::METHOD_GET && preg_match('#^/dashboard/link/(\d+)/edit$#', $path, $m) === 1) {
            return ['handler' => 'link_edit', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/dashboard/link/(\d+)/update$#', $path, $m) === 1) {
            return ['handler' => 'link_update', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_POST && preg_match('#^/dashboard/link/(\d+)/delete$#', $path, $m) === 1) {
            return ['handler' => 'link_delete', 'params' => ['id' => (int) $m[1]]];
        }

        if ($method === self::METHOD_GET) {
            $slug = ltrim($path, '/');
            if (preg_match(self::SLUG_PATTERN, $slug) === 1 && !in_array($slug, self::RESERVED, true)) {
                return ['handler' => 'redirect', 'params' => ['slug' => $slug]];
            }
        }

        return ['handler' => 'notfound', 'params' => []];
    }
}

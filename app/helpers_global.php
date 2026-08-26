<?php
declare(strict_types=1);

/**
 * Wrapper toàn cục cho các hàm helper (views chạy ở global namespace).
 */

namespace {
    if (!function_exists('escape')) {
        function escape(?string $value): string
        {
            return \App\escape($value);
        }
    }

    if (!function_exists('url_for')) {
        function url_for(string $path = ''): string
        {
            return \App\url_for($path);
        }
    }

    if (!function_exists('render')) {
        function render(string $template, array $data = []): string
        {
            return \App\render($template, $data);
        }
    }

    if (!function_exists('current_user')) {
        function current_user(): ?array
        {
            return \App\current_user();
        }
    }

    if (!function_exists('csrf_field')) {
        function csrf_field(): string
        {
            return \App\csrf_field();
        }
    }

    if (!function_exists('short_url_for')) {
        function short_url_for(array $link): string
        {
            return \App\short_url_for($link);
        }
    }
}

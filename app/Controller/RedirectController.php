<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UrlRepository;
use App\Security\Csrf;

final class RedirectController
{
    public function __construct(
        private readonly UrlRepository $urlRepository,
        private readonly Csrf $csrf
    ) {
    }

    public function redirect(string $slug): never
    {
        $row = $this->urlRepository->findBySlug($slug);

        if ($row === null) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');
            echo \App\render('notfound');
            exit;
        }

        // Khung thời gian hoạt động của link
        $blocked = $this->timeWindowBlocked($row);
        if ($blocked !== null) {
            http_response_code(410);
            header('Content-Type: text/html; charset=UTF-8');
            echo \App\render('link-expired', ['message' => $blocked]);
            exit;
        }

        // Link có mật khẩu -> yêu cầu nhập trước khi đi tiếp
        if (!empty($row['password_hash'])) {
            if (empty($_SESSION['link_access'][$slug]) || $_SESSION['link_access'][$slug] !== true) {
                http_response_code(200);
                header('Content-Type: text/html; charset=UTF-8');
                echo \App\render('link-password', [
                    'title' => 'Link được bảo vệ',
                    'slug'  => $slug,
                    'csrf'  => $this->csrf,
                    'error' => null,
                ]);
                exit;
            }
        }

        // Đếm click và điều hướng
        $this->urlRepository->incrementClicks($slug);

        $target = (string) $row['target_url'];
        $status = preg_match('#^https?://#i', $target) === 1 ? 301 : 302;

        http_response_code($status);
        header('Location: ' . $target);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        exit;
    }

    public function unlock(string $slug): never
    {
        $row = $this->urlRepository->findBySlug($slug);
        if ($row === null || empty($row['password_hash'])) {
            \App\redirect(url_for($slug), 302);
        }

        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for($slug), 302);
        }

        $password = (string) ($_POST['password'] ?? '');
        if (password_verify($password, (string) $row['password_hash'])) {
            $_SESSION['link_access'][$slug] = true;
            \App\redirect(url_for($slug), 302);
        }

        http_response_code(400);
        header('Content-Type: text/html; charset=UTF-8');
        echo \App\render('link-password', [
            'title' => 'Link được bảo vệ',
            'slug'  => $slug,
            'csrf'  => $this->csrf,
            'error' => 'Mật khẩu không đúng, vui lòng thử lại.',
        ]);
        exit;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function timeWindowBlocked(array $row): ?string
    {
        $now = time();

        if (!empty($row['starts_at'])) {
            $start = strtotime((string) $row['starts_at']);
            if ($start !== false && $now < $start) {
                return 'Link này chưa được mở. Quay lại sau nhé.';
            }
        }

        if (!empty($row['ends_at'])) {
            $end = strtotime((string) $row['ends_at']);
            if ($end !== false && $now > $end) {
                return 'Link này đã hết hạn.';
            }
        }

        return null;
    }
}

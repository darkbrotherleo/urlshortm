<?php
declare(strict_types=1);

namespace App\Controller;

use App\Security\Csrf;
use App\Service\RateLimitExceededException;
use App\Service\ShortUrlService;
use App\Service\UrlValidationException;

final class HomeController
{
    public function __construct(
        private readonly ShortUrlService $service,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $this->renderHome([
            'title'  => 'UrlShortM — Rút gọn & theo dõi link',
            'target' => '',
        ]);
    }

    public function shorten(): never
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            $this->renderHome([
                'title'         => 'UrlShortM — Rút gọn & theo dõi link',
                'target'        => trim((string) ($_POST['target'] ?? '')),
                'error'         => 'Phiên đăng nhập đã hết hạn, vui lòng thử lại.',
                'status'        => 'error',
                'statusMessage' => null,
            ], 403);
        }

        $rawTarget = trim((string) ($_POST['target'] ?? ''));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = \App\current_user()['id'] ?? null;

        try {
            $result = $this->service->create($rawTarget, $ip, $userId);
            $this->renderHome([
                'title'         => 'UrlShortM — Link đã được rút gọn',
                'target'        => $rawTarget,
                'result'        => $result,
                'status'        => 'success',
                'statusMessage' => 'Link đã được rút gọn thành công.',
            ]);
        } catch (UrlValidationException $e) {
            $this->renderHome([
                'title'  => 'UrlShortM — Rút gọn & theo dõi link',
                'target' => $rawTarget,
                'error'  => $e->getMessage(),
                'status' => 'error',
            ], 400);
        } catch (RateLimitExceededException $e) {
            $this->renderHome([
                'title'  => 'UrlShortM — Rút gọn & theo dõi link',
                'target' => $rawTarget,
                'error'  => $e->getMessage(),
                'status' => 'error',
            ], 429);
        } catch (\Throwable $e) {
            error_log('[UrlShortM] shorten failed: ' . $e->getMessage());
            $this->renderHome([
                'title'  => 'UrlShortM — Rút gọn & theo dõi link',
                'target' => $rawTarget,
                'error'  => 'Đã có lỗi xảy ra, vui lòng thử lại.',
                'status' => 'error',
            ], 500);
        }
    }

    private function renderHome(array $overrides = [], int $status = 200): never
    {
        $base = [
            'title'         => 'UrlShortM — Rút gọn & theo dõi link',
            'csrf'          => $this->csrf,
            'target'        => '',
            'result'        => null,
            'error'         => null,
            'status'        => null,
            'statusMessage' => null,
        ];

        http_response_code($status);
        echo \App\render('home', array_merge($base, $overrides));
        exit;
    }
}

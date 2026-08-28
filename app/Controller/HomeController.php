<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\PackageRepository;
use App\Security\Csrf;
use App\Service\RateLimitExceededException;
use App\Service\ShortUrlService;
use App\Service\UrlValidationException;

final class HomeController
{
    public function __construct(
        private readonly ShortUrlService $service,
        private readonly PackageRepository $packages,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $siteTitle = (string) \App\Container::getInstance()->siteSettingsService()->get('seo_site_title', '');
        $this->renderHome([
            'title'  => $siteTitle !== '' ? $siteTitle : 'UrlShortM — Rút gọn & theo dõi link',
            'target' => '',
        ]);
    }

    public function pricing(): never
    {
        http_response_code(200);
        echo \App\render('pricing', [
            'title' => 'Bảng giá — UrlShortM',
            'plans' => $this->packages->findAll(),
        ]);
        exit;
    }

    public function features(): never
    {
        http_response_code(200);
        echo \App\render('features', [
            'title' => 'Tính năng — UrlShortM',
            'plans' => $this->packages->findAll(),
        ]);
        exit;
    }

    public function sitemap(): never
    {
        $base = rtrim(\App\base_url(), '/');
        $pages = ['/', '/tinh-nang', '/bang-gia', '/tro-giup', '/dang-ky', '/dang-nhap'];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($pages as $p) {
            $xml .= '  <url><loc>' . \App\escape($base . $p) . '</loc><changefreq>daily</changefreq></url>' . "\n";
        }
        foreach (\App\Container::getInstance()->urlRepository()->findAllForAdmin(null, 1000, 0) as $link) {
            if (isset($link['is_active']) && (int) $link['is_active'] !== 1) {
                continue;
            }
            $lastmod = substr((string) (($link['updated_at'] ?? '') ?: ($link['created_at'] ?? '')), 0, 10);
            $xml .= '  <url><loc>' . \App\escape($base . '/' . $link['slug']) . '</loc>'
                . ($lastmod !== '' ? '<lastmod>' . \App\escape($lastmod) . '</lastmod>' : '')
                . '<changefreq>weekly</changefreq></url>' . "\n";
        }
        $xml .= '</urlset>' . "\n";

        header('Content-Type: application/xml; charset=UTF-8');
        echo $xml;
        exit;
    }

    public function robotsTxt(): never
    {
        header('Content-Type: text/plain; charset=UTF-8');
        echo \App\robots_txt_content();
        exit;
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

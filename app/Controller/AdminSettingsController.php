<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\MediaRepository;
use App\Repository\SettingRepository;
use App\Security\Csrf;
use App\Service\ImageProcessor;
use App\Service\SiteSettingsService;

final class AdminSettingsController
{
    public function __construct(
        private readonly SettingRepository $settings,
        private readonly SiteSettingsService $site,
        private readonly MediaRepository $media,
        private readonly ImageProcessor $images,
        private readonly Csrf $csrf
    ) {
    }

    /** Thông tin hệ thống — chỉ xem */
    public function systemInfo(): never
    {
        $admin = $this->guard();
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = rtrim(\App\base_url(), '/');

        $server = (string) ($_SERVER['SERVER_SOFTWARE'] ?? PHP_OS . ' / ' . php_sapi_name());

        $db = \App\Config::get('db', []);
        $dbInfo = (($db['driver'] ?? 'mysql') === 'sqlite')
            ? 'SQLite'
            : sprintf('MySQL %s@%s/%s', (string) ($db['user'] ?? ''), (string) ($db['host'] ?? ''), (string) ($db['name'] ?? ''));

        $ok = true;
        try {
            \App\Database::default()->query('SELECT 1');
        } catch (\Throwable) {
            $ok = false;
        }

        $this->page('system', 'Thông tin hệ thống', 'admin-settings-system', [
            'scheme' => $scheme, 'host' => $host, 'base' => $base,
            'path' => rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') ?: '/',
            'server' => $server, 'db' => $dbInfo, 'dbOk' => $ok,
        ]);
    }

    public function website(): never
    {
        $this->page('website', 'Thông tin website', 'admin-settings-website', [
            'siteName' => $this->site->siteName(),
            'siteIntro' => $this->site->siteIntro(),
            'logo' => $this->site->logo(),
            'favicon' => $this->site->favicon(),
        ]);
    }

    public function invoice(): never
    {
        $this->page('invoice', 'Hoá đơn', 'admin-settings-invoice', [
            'invoice_name' => (string) $this->site->get('invoice_name', ''),
            'invoice_tax_type' => (string) $this->site->get('invoice_tax_type', ''),
            'invoice_address' => (string) $this->site->get('invoice_address', ''),
            'invoice_phone' => (string) $this->site->get('invoice_phone', ''),
            'invoice_tax_id' => (string) $this->site->get('invoice_tax_id', ''),
        ]);
    }

    public function smtp(): never
    {
        $this->page('smtp', 'Email (SMTP)', 'admin-settings-smtp', [
            'smtp_host' => (string) $this->site->get('smtp_host', ''),
            'smtp_port' => (string) $this->site->get('smtp_port', ''),
            'smtp_username' => (string) $this->site->get('smtp_username', ''),
            'smtp_password' => (string) $this->site->get('smtp_password', ''),
            'smtp_from_email' => (string) $this->site->get('smtp_from_email', ''),
            'smtp_configured' => \App\Container::getInstance()->mailer()->isConfigured(),
        ]);
    }

    public function smtpTest(): never
    {
        $this->guard();
        $this->requireCsrf();

        $to = trim((string) ($_POST['test_to'] ?? ''));
        $subject = trim((string) ($_POST['test_subject'] ?? 'Email thử nghiệm'));
        $body = trim((string) ($_POST['test_body'] ?? 'Email thử nghiệm từ UrlShortM. Nếu bạn nhận được email này, cấu hình SMTP hoạt động tốt.'));

        try {
            \App\Container::getInstance()->mailer()->send($to, $subject, $body);
            \App\redirect(url_for('admin/settings/smtp') . '?ok=1', 302);
        } catch (\RuntimeException $e) {
            \App\redirect(url_for('admin/settings/smtp') . '?error=' . rawurlencode('Gửi thử thất bại: ' . $e->getMessage()), 302);
        }
    }

    public function media(): never
    {
        $formats = $this->site->mediaFormats();
        $this->page('media', 'Media', 'admin-settings-media', [
            'formats' => $formats,
            'allFormats' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'],
            'compress' => $this->site->mediaCompress(),
            'convert' => $this->site->mediaConvert(),
            'media' => $this->media->findAll(),
        ]);
    }

    public function seo(): never
    {
        $this->page('seo', 'SEO', 'admin-settings-seo', [
            'siteName' => $this->site->siteName(),
            'base' => rtrim(\App\base_url(), '/'),
            'robots_content' => \App\robots_txt_content(),
            'seo' => [
                'site_title' => (string) $this->site->get('seo_site_title', $this->site->siteName()),
                'meta_description' => (string) $this->site->get('seo_meta_description', $this->site->siteIntro()),
                'meta_keywords' => (string) $this->site->get('seo_meta_keywords', ''),
                'canonical_url' => (string) $this->site->get('seo_canonical_url', ''),
                'robots_meta' => (string) $this->site->get('seo_robots_meta', ''),
                'og_title' => (string) $this->site->get('seo_og_title', ''),
                'og_description' => (string) $this->site->get('seo_og_description', ''),
                'og_image' => (string) $this->site->get('seo_og_image', ''),
                'og_type' => (string) $this->site->get('seo_og_type', 'website'),
                'gsc' => (string) $this->site->get('seo_gsc', ''),
                'bing' => (string) $this->site->get('seo_bing', ''),
                'yandex' => (string) $this->site->get('seo_yandex', ''),
                'baidu' => (string) $this->site->get('seo_baidu', ''),
                'ga4' => (string) $this->site->get('seo_ga4', ''),
                'gtm' => (string) $this->site->get('seo_gtm', ''),
                'meta_pixel' => (string) $this->site->get('seo_meta_pixel', ''),
                'tiktok_pixel' => (string) $this->site->get('seo_tiktok_pixel', ''),
                'indexnow_key' => (string) $this->site->get('seo_indexnow_key', ''),
                'hreflang' => (string) $this->site->get('seo_hreflang', ''),
                'head_code' => (string) $this->site->get('seo_head_code', ''),
                'body_code' => (string) $this->site->get('seo_body_code', ''),
                'footer_code' => (string) $this->site->get('seo_footer_code', ''),
                'ai_meta' => (string) $this->site->get('seo_ai_meta', '1'),
            ],
        ]);
    }

    /* ---------------- Lưu ---------------- */

    public function saveWebsite(): never
    {
        $this->guard();
        $this->requireCsrf();

        $logo = $this->handleUpload('logo') ?? $this->site->logo();
        $favicon = $this->handleUpload('favicon') ?? $this->site->favicon();

        $this->settings->set('site_name', trim((string) ($_POST['site_name'] ?? '')) !== '' ? trim((string) $_POST['site_name']) : 'UrlShortM');
        $this->settings->set('site_intro', trim((string) ($_POST['site_intro'] ?? '')));
        $this->settings->set('site_logo', $logo);
        $this->settings->set('site_favicon', $favicon);

        \App\redirect(url_for('admin/settings/website') . '?ok=1', 302);
    }

    public function saveInvoice(): never
    {
        $this->guard();
        $this->requireCsrf();

        $taxType = (string) ($_POST['invoice_tax_type'] ?? '');
        if (!in_array($taxType, ['', 'individual', 'business'], true)) {
            $taxType = '';
        }
        $taxId = trim((string) ($_POST['invoice_tax_id'] ?? ''));
        $digits = preg_replace('/[\s\-.]/', '', $taxId) ?? '';
        if ($taxId !== '' && (preg_match('/^\d+$/', $digits) !== 1 || strlen($digits) < 10 || strlen($digits) > 14)) {
            $this->back('Mã số thuế không hợp lệ (10-14 chữ số).');
        }

        $this->settings->set('invoice_name', trim((string) ($_POST['invoice_name'] ?? '')));
        $this->settings->set('invoice_tax_type', $taxType);
        $this->settings->set('invoice_address', trim((string) ($_POST['invoice_address'] ?? '')));
        $this->settings->set('invoice_phone', trim((string) ($_POST['invoice_phone'] ?? '')));
        $this->settings->set('invoice_tax_id', $taxId);

        \App\redirect(url_for('admin/settings/invoice') . '?ok=1', 302);
    }

    public function saveSmtp(): never
    {
        $this->guard();
        $this->requireCsrf();

        $this->settings->set('smtp_host', trim((string) ($_POST['smtp_host'] ?? '')));
        $this->settings->set('smtp_port', trim((string) ($_POST['smtp_port'] ?? '587')));
        $this->settings->set('smtp_username', trim((string) ($_POST['smtp_username'] ?? '')));
        if (trim((string) ($_POST['smtp_password'] ?? '')) !== '') {
            $this->settings->set('smtp_password', trim((string) $_POST['smtp_password']));
        }
        $this->settings->set('smtp_from_email', trim((string) ($_POST['smtp_from_email'] ?? '')));

        \App\redirect(url_for('admin/settings/smtp') . '?ok=1', 302);
    }

    public function saveMedia(): never
    {
        $this->guard();
        $this->requireCsrf();

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
        $chosen = [];
        foreach ($allowed as $f) {
            if (isset($_POST['format_' . $f])) {
                $chosen[] = $f;
            }
        }
        if ($chosen === []) {
            $this->back('Phải chọn ít nhất 1 định dạng ảnh.');
        }
        $convert = (string) ($_POST['media_convert'] ?? '');
        if (!in_array($convert, ['', 'webp', 'avif'], true)) {
            $convert = '';
        }

        $this->settings->set('media_formats', json_encode($chosen));
        $this->settings->set('media_compress', isset($_POST['media_compress']) ? '1' : '0');
        $this->settings->set('media_convert', $convert);

        \App\redirect(url_for('admin/settings/media') . '?ok=1', 302);
    }

    public function mediaUpload(): never
    {
        $this->guard();
        $this->requireCsrf();

        if (empty($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
            $this->back('Chưa chọn file ảnh.');
        }

        $info = (array) $_FILES['media_file'];
        $mime = (string) ($info['type'] ?? '');
        if (!$this->images->isAllowed($mime, $this->site->mediaFormats())) {
            $this->back('Định dạng ảnh không được phép (chỉ: ' . implode(', ', $this->site->mediaFormats()) . ').');
        }

        $result = $this->storeUpload((array) $info, 'media_');
        if ($result === null) {
            $this->back('Không lưu được ảnh.');
        }
        [$path, $finalMime] = $result;

        $filename = basename($path);
        $this->media->create($filename, (string) ($info['name'] ?? $filename), $path, $finalMime, (int) ($info['size'] ?? 0));

        \App\redirect(url_for('admin/settings/media') . '?ok=1', 302);
    }

    public function mediaDelete(int $id): never
    {
        $this->guard();
        $this->requireCsrf();
        $row = $this->media->findById($id);
        if ($row !== null) {
            $abs = dirname(__DIR__, 2) . '/' . $row['path'];
            if (is_file($abs)) {
                @unlink($abs);
            }
            $this->media->delete($id);
        }
        \App\redirect(url_for('admin/settings/media') . '?ok=1', 302);
    }

    public function saveSeo(): never
    {
        $this->guard();
        $this->requireCsrf();

        $map = [
            'seo_site_title' => 'site_title', 'seo_meta_description' => 'meta_description',
            'seo_meta_keywords' => 'meta_keywords', 'seo_canonical_url' => 'canonical_url',
            'seo_robots_meta' => 'robots_meta', 'seo_og_title' => 'og_title',
            'seo_og_description' => 'og_description', 'seo_og_image' => 'og_image',
            'seo_og_type' => 'og_type', 'seo_gsc' => 'gsc', 'seo_bing' => 'bing',
            'seo_yandex' => 'yandex', 'seo_baidu' => 'baidu', 'seo_ga4' => 'ga4',
            'seo_gtm' => 'gtm', 'seo_meta_pixel' => 'meta_pixel', 'seo_tiktok_pixel' => 'tiktok_pixel',
            'seo_indexnow_key' => 'indexnow_key', 'seo_hreflang' => 'hreflang',
            'seo_robots_txt' => 'robots_txt_content',
            'seo_head_code' => 'head_code', 'seo_body_code' => 'body_code', 'seo_footer_code' => 'footer_code',
        ];
        foreach ($map as $key => $field) {
            $this->settings->set($key, trim((string) ($_POST[$field] ?? '')));
        }
        $this->settings->set('seo_ai_meta', isset($_POST['ai_meta']) ? '1' : '0');

        \App\redirect(url_for('admin/settings/seo') . '?ok=1', 302);
    }

    /* ---------------- Helpers ---------------- */

    private function page(string $key, string $title, string $view, array $data): never
    {
        $content = \App\render($view, array_merge($data, [
            'csrf' => $this->csrf,
            'ok'   => isset($_GET['ok']) && $_GET['ok'] === '1',
            'error' => isset($_GET['error']) ? (string) $_GET['error'] : null,
        ]));
        \App\render_admin_page($this->guard(), 'Admin — ' . $title, $key, $content);
    }

    private function handleUpload(string $field): ?string
    {
        if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $info = (array) $_FILES[$field];
        $mime = (string) ($info['type'] ?? '');
        if (!$this->images->isAllowed($mime, $this->site->mediaFormats())) {
            return null;
        }
        $result = $this->storeUpload($info, 'site_' . $field . '_');
        if ($result === null) {
            return null;
        }

        return $result[0];
    }

    /**
     * Lưu file upload vào uploads/, áp dụng nén + chuyển đổi.
     *
     * @param array<string,mixed> $info
     *
     * @return array{0:string,1:string}|null [path, mime]
     */
    private function storeUpload(array $info, string $prefix): ?array
    {
        $mime = (string) ($info['type'] ?? '');
        $ext = $this->images->extForMime($mime);
        if ($ext === null) {
            return null;
        }
        $tmp = (string) ($info['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return null;
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        $filename = $prefix . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            return null;
        }

        $final = $this->images->process($dest, $mime, $this->site->mediaCompress(), $this->site->mediaConvert());
        if ($final !== null) {
            [$dest, $mime] = $final;
            $filename = basename($dest);
        }

        return ['uploads/' . $filename, $mime];
    }

    private function guard(): array
    {
        $admin = current_admin();
        if ($admin === null) {
            \App\redirect(url_for('admin/dang-nhap'), 302);
        }

        return $admin;
    }

    private function requireCsrf(): void
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('admin/settings'), 302);
        }
    }

    private function back(string $message): never
    {
        \App\redirect(url_for('admin/settings') . '?error=' . rawurlencode($message), 302);
    }
}

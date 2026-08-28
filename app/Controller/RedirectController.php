<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ClickEventRepository;
use App\Repository\UrlRepository;
use App\Security\Csrf;
use App\Service\UserPlanService;
use App\Tracking\CountryLookup;
use App\Tracking\UserAgentParser;

final class RedirectController
{
    public function __construct(
        private readonly UrlRepository $urlRepository,
        private readonly ClickEventRepository $clickEventRepository,
        private readonly UserAgentParser $uaParser,
        private readonly CountryLookup $countryLookup,
        private readonly UserPlanService $plan,
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

        // Link bị vô hiệu (admin) -> chặn truy cập
        if (isset($row['is_active']) && (int) $row['is_active'] !== 1) {
            http_response_code(410);
            header('Content-Type: text/html; charset=UTF-8');
            echo \App\render('link-expired', ['message' => 'Link này đã bị vô hiệu hoá.']);
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
        if (isset($row['user_id']) && $row['user_id'] !== null && !$this->plan->canClick((int) $row['user_id'])) {
            http_response_code(410);
            header('Content-Type: text/html; charset=UTF-8');
            echo \App\render('link-expired', ['message' => 'Link của gói này đã đạt giới hạn click trong tháng. Hãy nâng cấp gói để tiếp tục.']);
            exit;
        }

        $this->urlRepository->incrementClicks($slug);

        // GĐ 0+1 tracking: ghi 1 click_event (IP hash, UA -> device/browser/os, referrer, quốc gia).
        $ua = $this->trimHeader($_SERVER['HTTP_USER_AGENT'] ?? null);
        $uaInfo = $ua !== null ? $this->uaParser->parse($ua) : ['device' => null, 'browser' => null, 'os' => null];
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        $this->clickEventRepository->record([
            'link_id'    => (int) $row['id'],
            'user_id'    => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'opened_at'  => date('Y-m-d H:i:s'),
            'ip_hash'    => \App\hash_ip($ip),
            'ip_address' => (bool) \App\Config::get('app.tracking.store_raw_ip', false) ? $ip : null,
            'user_agent' => $ua,
            'referrer'   => $this->trimHeader($_SERVER['HTTP_REFERER'] ?? null),
            'country'    => $this->countryLookup->lookup($ip),
            'device'     => $uaInfo['device'],
            'browser'    => $uaInfo['browser'],
            'os'         => $uaInfo['os'],
        ]);

        // Gắn UTM tags vào URL đích để tracking hoạt động.
        $target = \App\append_utm((string) $row['target_url'], [
            'utm_campaign' => $row['utm_campaign'] ?? null,
            'utm_medium'   => $row['utm_medium'] ?? null,
            'utm_source'   => $row['utm_source'] ?? null,
            'utm_term'     => $row['utm_term'] ?? null,
            'utm_content'  => $row['utm_content'] ?? null,
        ]);

        $status = preg_match('#^https?://#i', $target) === 1 ? 301 : 302;

        http_response_code($status);
        header('Location: ' . $target);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        exit;
    }

    private function trimHeader(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : substr($value, 0, 512);
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

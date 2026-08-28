<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\DomainRepository;
use App\Security\Csrf;
use App\Service\UserPlanService;

final class AdminDomainsController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly DomainRepository $domains,
        private readonly UserPlanService $plan,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $admin = $this->guard();

        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->domains->countAllForAdmin($search !== '' ? $search : null);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);

        $userDomains = $this->domains->findAllForAdmin($search !== '' ? $search : null, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        // "Số lượng": domain đã kích hoạt / còn lại theo gói của user
        $usageByUser = [];
        foreach ($userDomains as $d) {
            $uid = (int) $d['user_id'];
            if (isset($usageByUser[$uid])) {
                continue;
            }
            $max = $this->plan->limits($uid)['max_custom_domains'];
            $active = $this->domains->countActiveByUser($uid);
            $remaining = $max === null ? 'Không giới hạn' : (string) max(0, $max - $active);
            $usageByUser[$uid] = ['active' => $active, 'remaining' => $remaining];
        }

        $content = \App\render('admin-domains', [
            'systemDomains' => $this->domains->findSystemDomains(),
            'userDomains'   => $userDomains,
            'usageByUser'   => $usageByUser,
            'search'        => $search,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'total'         => $total,
            'currentHost'   => parse_url(\App\base_url(), PHP_URL_HOST) ?: '',
            'effectiveBase' => rtrim(\App\system_short_base(), '/'),
            'csrf'          => $this->csrf,
            'ok'            => isset($_GET['ok']) && $_GET['ok'] === '1',
            'error'         => isset($_GET['error']) ? (string) $_GET['error'] : null,
        ]);

        \App\render_admin_page($admin, 'Admin — Quản lý Domain', 'domains', $content);
    }

    public function addSystem(): never
    {
        $this->guard();
        $this->requireCsrf();

        $domain = strtolower(trim((string) ($_POST['domain'] ?? '')));
        if (preg_match('/^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?$/', $domain) !== 1 || strlen($domain) > 190) {
            $this->back('Domain không hợp lệ.');
        }
        $this->domains->addSystemDomain($domain);
        \App\redirect(url_for('admin/domains') . '?ok=1', 302);
    }

    public function setDefault(int $id): never
    {
        $this->guard();
        $this->requireCsrf();
        $this->domains->setSystemDefault($id);
        \App\redirect(url_for('admin/domains') . '?ok=1', 302);
    }

    public function toggleSystem(int $id): never
    {
        $this->guard();
        $this->requireCsrf();
        $this->domains->toggleSystemActive($id);
        \App\redirect(url_for('admin/domains') . '?ok=1', 302);
    }

    public function deleteSystem(int $id): never
    {
        $this->guard();
        $this->requireCsrf();
        $this->domains->deleteSystemDomain($id);
        \App\redirect(url_for('admin/domains') . '?ok=1', 302);
    }

    public function toggleUser(int $id): never
    {
        $this->guard();
        $this->requireCsrf();
        if ($this->domains->findByIdAny($id) !== null) {
            $this->domains->toggleUserActive($id);
        }
        \App\redirect(url_for('admin/domains') . '?ok=1', 302);
    }

    public function deleteUser(int $id): never
    {
        $this->guard();
        $this->requireCsrf();
        $this->domains->deleteAny($id);
        \App\redirect(url_for('admin/domains') . '?ok=1', 302);
    }

    private function back(string $message): never
    {
        \App\redirect(url_for('admin/domains') . '?error=' . rawurlencode($message), 302);
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
            \App\redirect(url_for('admin/domains'), 302);
        }
    }
}

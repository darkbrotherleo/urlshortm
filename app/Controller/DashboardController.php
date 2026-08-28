<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ClickEventRepository;
use App\Repository\DemographicRepository;
use App\Repository\DomainRepository;
use App\Repository\FolderRepository;
use App\Repository\PixelRepository;
use App\Repository\UrlRepository;
use App\Repository\UserRepository;
use App\Repository\UserSettingsRepository;
use App\Repository\UtmProfileRepository;
use App\Security\Csrf;
use App\Service\AuthService;
use App\Service\UserPlanService;

final class DashboardController
{
    /** Các tab hợp lệ — menu sidebar. */
    private const TABS = ['tong-quan', 'links', 'folder', 'baocao', 'tai-khoan', 'cai-dat', 'pixels', 'domains', 'utms', 'demographics'];

    public function __construct(
        private readonly UrlRepository $urlRepository,
        private readonly FolderRepository $folderRepository,
        private readonly UserRepository $userRepository,
        private readonly PixelRepository $pixelRepository,
        private readonly DomainRepository $domainRepository,
        private readonly UtmProfileRepository $utmProfileRepository,
        private readonly ClickEventRepository $clickEventRepository,
        private readonly UserSettingsRepository $userSettingsRepository,
        private readonly DemographicRepository $demographicRepository,
        private readonly AuthService $authService,
        private readonly UserPlanService $plan,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $user = $this->currentUserOrRedirect();

        $tab = $_GET['tab'] ?? 'tong-quan';
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'tong-quan';
        }

        $userId = (int) $user['id'];
        $totals = $this->urlRepository->userTotals($userId);
        $folders = $this->folderRepository->findByUser($userId);

        $folderId = null;
        if (isset($_GET['folder']) && ctype_digit((string) $_GET['folder'])) {
            $candidate = (int) $_GET['folder'];
            if ($this->folderRepository->findById($candidate, $userId) !== null) {
                $folderId = $candidate;
            }
        }

        $links = [];
        switch ($tab) {
            case 'links':
                $links = $this->urlRepository->findByUser($userId);
                break;
            case 'folder':
                $links = $folderId !== null
                    ? $this->urlRepository->findByFolder($folderId, $userId)
                    : $this->urlRepository->findByUser($userId);
                break;
            case 'tong-quan':
                $links = $this->urlRepository->findByUser($userId, 5);
                break;
        }

        $pixels = $tab === 'pixels' ? $this->pixelRepository->findByUser($userId) : [];
        $pixelEdit = null;
        if ($tab === 'pixels' && isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
            $pixelEdit = $this->pixelRepository->findById((int) $_GET['edit'], $userId);
        }
        $domains = $tab === 'domains' ? $this->domainRepository->findByUser($userId) : [];
        $utmProfiles = in_array($tab, ['utms'], true) ? $this->utmProfileRepository->findByUser($userId) : [];
        $utmEdit = null;
        if ($tab === 'utms' && isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
            $utmEdit = $this->utmProfileRepository->findById((int) $_GET['edit'], $userId);
        }

        // Nhân khẩu học (Meta) — cấu hình + snapshot
        $metaConfig = null;
        $demoSnapshot = null;
        if (in_array($tab, ['baocao', 'demographics'], true)) {
            $adAccount = $this->userSettingsRepository->get($userId, 'meta_ad_account');
            $token = $this->userSettingsRepository->get($userId, 'meta_token');
            $metaConfig = [
                'ad_account' => $adAccount ?? '',
                'has_token'  => $token !== null && $token !== '',
                'token_mask' => $token !== null && $token !== '' ? '••••' . substr($token, -4) : '',
            ];
            $demoSnapshot = $this->demographicRepository->latest($userId);
        }

        // Báo cáo
        $reportData = null;
        $reportLinkId = null;
        $reportFrom = null;
        $reportTo = null;
        $allLinks = [];
        $reportEvents = [];
        $reportPage = 1;
        $reportTotal = 0;
        if ($tab === 'baocao') {
            $allLinks = $this->urlRepository->findByUser($userId);
            [$reportLinkId, $reportFrom, $reportTo] = $this->reportFilters();

            $reportData = [
                'summary'    => $this->clickEventRepository->reportSummary($userId, $reportLinkId, $reportFrom, $reportTo),
                'byDay'      => $this->clickEventRepository->reportByDay($userId, $reportLinkId, $reportFrom, $reportTo),
                'byDevice'   => $this->clickEventRepository->reportByFactor('device', $userId, $reportLinkId, $reportFrom, $reportTo),
                'byBrowser'  => $this->clickEventRepository->reportByFactor('browser', $userId, $reportLinkId, $reportFrom, $reportTo),
                'byOs'       => $this->clickEventRepository->reportByFactor('os', $userId, $reportLinkId, $reportFrom, $reportTo),
                'byCountry'  => $this->clickEventRepository->reportByFactor('country', $userId, $reportLinkId, $reportFrom, $reportTo),
                'byReferrer' => $this->clickEventRepository->reportByFactor('referrer', $userId, $reportLinkId, $reportFrom, $reportTo),
                'topLinks'   => $this->clickEventRepository->reportTopLinks($userId, $reportLinkId, $reportFrom, $reportTo),
            ];
            if ($demoSnapshot !== null) {
                $reportData['demographics'] = $demoSnapshot['payload'];
            }

            // Chi tiết lượt mở (phân trang)
            $limit = 50;
            $reportPage = max(1, (int) ($_GET['page'] ?? 1));
            $reportTotal = $this->clickEventRepository->countReportEvents($userId, $reportLinkId, $reportFrom, $reportTo);
            $reportEvents = $this->clickEventRepository->reportEvents(
                $userId,
                $reportLinkId,
                $reportFrom,
                $reportTo,
                $limit,
                ($reportPage - 1) * $limit
            );
        }

        $activeFolder = $folderId !== null
            ? $this->folderRepository->findById($folderId, $userId)
            : null;

        http_response_code(200);
        echo \App\render('dashboard', [
            'title'        => 'Bảng điều khiển',
            'user'         => $user,
            'tab'          => $tab,
            'totals'       => $totals,
            'links'        => $links,
            'folders'      => $folders,
            'folderId'     => $folderId,
            'activeFolder' => $activeFolder,
            'pixels'       => $pixels,
            'pixelEdit'    => $pixelEdit,
            'domains'      => $domains,
            'utmProfiles'  => $utmProfiles,
            'utmEdit'      => $utmEdit,
            'reportData'   => $reportData,
            'reportLinkId' => $reportLinkId,
            'reportFrom'   => $reportFrom !== null ? substr($reportFrom, 0, 10) : '',
            'reportTo'     => $reportTo !== null ? substr($reportTo, 0, 10) : '',
            'allLinks'     => $allLinks,
            'reportEvents' => $reportEvents,
            'reportPage'   => $reportPage,
            'reportTotal'  => $reportTotal,
            'metaConfig'   => $metaConfig,
            'demoSnapshot' => $demoSnapshot,
            'planInfo'     => [
                'plan'  => $this->plan->planOf($userId),
                'usage' => $this->plan->usage($userId),
                'limits' => $this->plan->limits($userId),
                'features' => [
                    'analytics' => $this->plan->featureEnabled($userId, 'analytics'),
                    'qr_code' => $this->plan->featureEnabled($userId, 'qr_code'),
                    'password_protection' => $this->plan->featureEnabled($userId, 'password_protection'),
                    'link_expiration' => $this->plan->featureEnabled($userId, 'link_expiration'),
                    'utm_builder' => $this->plan->featureEnabled($userId, 'utm_builder'),
                    'api_access' => $this->plan->featureEnabled($userId, 'api_access'),
                ],
            ],
            'platforms'    => \App\Security\PixelPlatform::LIST,
            'flashOk'      => isset($_GET['ok']) && $_GET['ok'] === '1',
            'flashError'   => isset($_GET['error']) ? (string) $_GET['error'] : null,
            'csrf'         => $this->csrf,
        ]);
        exit;
    }

    public function createFolder(): never
    {
        $user = $this->currentUserOrRedirect();
        $this->requireCsrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '' && mb_strlen($name, 'UTF-8') <= 100) {
            $this->folderRepository->create((int) $user['id'], $name);
        }

        $this->back('folder');
    }

    public function deleteFolder(): never
    {
        $user = $this->currentUserOrRedirect();
        $this->requireCsrf();

        $id = (int) ($_POST['folder_id'] ?? 0);
        if ($id > 0) {
            $this->folderRepository->delete($id, (int) $user['id']);
        }

        $this->back('folder');
    }

    public function assignLink(): never
    {
        $user = $this->currentUserOrRedirect();
        $this->requireCsrf();

        $userId = (int) $user['id'];
        $linkId = (int) ($_POST['link_id'] ?? 0);

        $folderId = null;
        if (isset($_POST['folder_id']) && $_POST['folder_id'] !== '') {
            $candidate = (int) $_POST['folder_id'];
            if ($candidate > 0 && $this->folderRepository->findById($candidate, $userId) !== null) {
                $folderId = $candidate;
            }
        }

        if ($linkId > 0) {
            $this->urlRepository->assignFolder($linkId, $folderId, $userId);
        }

        $tab = in_array($_POST['return_tab'] ?? 'links', self::TABS, true) ? $_POST['return_tab'] : 'links';
        $folder = (isset($_POST['return_folder']) && ctype_digit((string) $_POST['return_folder'])) ? (int) $_POST['return_folder'] : null;

        $this->back($tab, $folder);
    }

    public function updateSettings(): never
    {
        $user = $this->currentUserOrRedirect();
        $this->requireCsrf();
        $userId = (int) $user['id'];

        $name = trim((string) ($_POST['display_name'] ?? ''));
        if (mb_strlen($name, 'UTF-8') > 100) {
            $this->backError('cai-dat', 'Tên hiển thị quá dài (tối đa 100 ký tự).');
        }

        $phone = trim((string) ($_POST['phone'] ?? ''));
        if ($phone !== '' && preg_match('/^\+?\d{9,15}$/', preg_replace('/[\s\-()]/', '', $phone) ?? '') !== 1) {
            $this->backError('cai-dat', 'Số điện thoại không hợp lệ.');
        }

        $taxType = (string) ($_POST['tax_type'] ?? '');
        if (!in_array($taxType, ['', 'individual', 'business'], true)) {
            $this->backError('cai-dat', 'Loại khách hàng không hợp lệ.');
        }

        $taxId = trim((string) ($_POST['tax_id'] ?? ''));
        $taxDigits = preg_replace('/[\s\-.]/', '', $taxId) ?? '';
        if ($taxId !== '' && (preg_match('/^\d+$/', $taxDigits) !== 1 || strlen($taxDigits) < 10 || strlen($taxDigits) > 14)) {
            $this->backError('cai-dat', 'Mã số thuế không hợp lệ (10-14 chữ số, không kèm ký tự khác).');
        }

        $companyName = trim((string) ($_POST['company_name'] ?? ''));
        $invoiceName = trim((string) ($_POST['invoice_name'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        if (mb_strlen($companyName, 'UTF-8') > 190 || mb_strlen($invoiceName, 'UTF-8') > 190
            || mb_strlen($address, 'UTF-8') > 255 || mb_strlen($city, 'UTF-8') > 100) {
            $this->backError('cai-dat', 'Một số trường vượt quá độ dài cho phép.');
        }

        $this->userRepository->updateDisplayName($userId, $name);
        $this->userRepository->updateProfile($userId, [
            'phone'        => $phone,
            'address'      => $address,
            'city'         => $city,
            'tax_type'     => $taxType,
            'company_name' => $companyName,
            'tax_id'       => $taxId,
            'invoice_name' => $invoiceName,
        ]);

        $this->back('cai-dat');
    }

    private function currentUserOrRedirect(): array
    {
        $user = \App\current_user();
        if ($user === null) {
            \App\redirect(url_for('dang-nhap'), 302);
        }

        return $user;
    }

    /**
     * @return array{0:?int,1:?string,2:?string} [linkId, from, to] từ query
     */
    private function reportFilters(): array
    {
        $linkId = (isset($_GET['link_id']) && ctype_digit((string) $_GET['link_id'])) ? (int) $_GET['link_id'] : null;
        $from = null;
        $to = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) === 1) {
            $from = $_GET['from'] . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) === 1) {
            $to = $_GET['to'] . ' 23:59:59';
        }

        return [$linkId, $from, $to];
    }

    public function exportReport(): never
    {
        $user = $this->currentUserOrRedirect();
        $userId = (int) $user['id'];

        [$linkId, $from, $to] = $this->reportFilters();
        $rows = $this->clickEventRepository->reportEvents($userId, $linkId, $from, $to);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bao-cao-luot-mo.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 cho Excel
        fputcsv($out, ['Thời gian', 'Link', 'Quốc gia', 'Thiết bị', 'Trình duyệt', 'Hệ điều hành', 'IP', 'Nguồn vào', 'IP hash']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['opened_at'],
                $r['slug'],
                \App\country_label($r['country'] ?? null),
                $r['device'] ?? '—',
                $r['browser'] ?? '—',
                $r['os'] ?? '—',
                $r['ip_address'] ?? '—',
                $r['referrer'] ?: '(trực tiếp)',
                $r['ip_hash'],
            ]);
        }

        fclose($out);
        exit;
    }

    private function requireCsrf(): void
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('dashboard'), 302);
        }
    }

    public function changePassword(): never
    {
        $user = $this->currentUserOrRedirect();
        $this->requireCsrf();

        try {
            $this->authService->changePassword(
                (int) $user['id'],
                (string) ($_POST['current_password'] ?? ''),
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['new_password_confirm'] ?? '')
            );
            $this->back('tai-khoan');
        } catch (\App\Service\AuthException $e) {
            $this->backError('tai-khoan', $e->getMessage());
        }
    }

    public function deactivateAccount(): never
    {
        $user = $this->currentUserOrRedirect();
        $this->requireCsrf();

        try {
            $this->authService->deactivate((int) $user['id'], (string) ($_POST['current_password'] ?? ''));
        } catch (\App\Service\AuthException $e) {
            $this->backError('tai-khoan', $e->getMessage());
        }

        // Soft delete: đăng xuất, tài khoản status=disabled (không xoá dữ liệu).
        $this->authService->logout();
        \App\redirect(url_for('dang-nhap') . '?disabled=1', 302);
    }

    private function back(string $tab, ?int $folder = null): never
    {
        $url = url_for('dashboard') . '?tab=' . $tab;
        if ($folder !== null) {
            $url .= '&folder=' . $folder;
        }
        $url .= '&ok=1';

        \App\redirect($url, 302);
    }

    private function backError(string $tab, string $message): never
    {
        \App\redirect(url_for('dashboard') . '?tab=' . $tab . '&error=' . rawurlencode($message), 302);
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\DomainRepository;
use App\Repository\DemographicRepository;
use App\Repository\PixelRepository;
use App\Repository\UserSettingsRepository;
use App\Repository\UtmProfileRepository;
use App\Security\Csrf;
use App\Security\PixelPlatform;
use App\Service\DomainService;
use App\Service\LinkValidationException;
use App\Service\MetaAudienceService;
use App\Service\UserPlanService;

final class SettingsController
{
    public function __construct(
        private readonly PixelRepository $pixelRepository,
        private readonly UtmProfileRepository $utmProfileRepository,
        private readonly UserSettingsRepository $userSettingsRepository,
        private readonly DemographicRepository $demographicRepository,
        private readonly DomainService $domainService,
        private readonly MetaAudienceService $metaAudience,
        private readonly UserPlanService $plan,
        private readonly Csrf $csrf
    ) {
    }

    public function createPixel(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $code = strtolower(trim((string) ($_POST['code'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $platform = trim((string) ($_POST['platform'] ?? ''));

        if ($platform === '' || !isset(PixelPlatform::LIST[$platform])) {
            $this->back('pixels', 'Vui lòng chọn nền tảng (Platform).');
        }
        if (preg_match('/^[0-9a-z_\-]{2,32}$/', $code) !== 1) {
            $this->back('pixels', 'Pixel ID chỉ gồm 2-32 ký tự (a-z, 0-9, _ hoặc -).');
        }
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
            $this->back('pixels', 'Tên Pixel không hợp lệ.');
        }
        if ($this->pixelRepository->existsCode($code)) {
            $this->back('pixels', 'Pixel ID này đã tồn tại.');
        }
        if (!$this->plan->canAddPixel((int) $user['id'])) {
            $this->back('pixels', 'Bạn đã đạt giới hạn pixel của gói hiện tại. Hãy xoá bớt hoặc nâng cấp gói.');
        }

        $this->pixelRepository->create((int) $user['id'], $code, $name, $platform);
        $this->back('pixels');
    }

    public function updatePixel(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $id = (int) ($_POST['pixel_id'] ?? 0);
        if ($id <= 0 || $this->pixelRepository->findById($id, (int) $user['id']) === null) {
            $this->back('pixels');
        }

        $code = strtolower(trim((string) ($_POST['code'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $platform = trim((string) ($_POST['platform'] ?? ''));

        if ($platform === '' || !isset(PixelPlatform::LIST[$platform])) {
            $this->back('pixels', 'Vui lòng chọn nền tảng (Platform).');
        }
        if (preg_match('/^[0-9a-z_\-]{2,32}$/', $code) !== 1) {
            $this->back('pixels', 'Pixel ID chỉ gồm 2-32 ký tự (a-z, 0-9, _ hoặc -).');
        }
        if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
            $this->back('pixels', 'Tên Pixel không hợp lệ.');
        }
        if ($this->pixelRepository->existsCode($code, $id)) {
            $this->back('pixels', 'Pixel ID này đã tồn tại.');
        }

        $this->pixelRepository->update($id, (int) $user['id'], $code, $name, $platform);
        $this->back('pixels');
    }

    public function deletePixel(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $this->pixelRepository->delete((int) ($_POST['pixel_id'] ?? 0), (int) $user['id']);
        $this->back('pixels');
    }

    public function createDomain(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        if (!$this->plan->canAddDomain((int) $user['id'])) {
            $this->back('domains', 'Bạn đã đạt giới hạn custom domain của gói hiện tại. Hãy xoá bớt hoặc nâng cấp gói.');
        }

        try {
            $this->domainService->register((int) $user['id'], (string) ($_POST['domain'] ?? ''));
            $this->back('domains');
        } catch (LinkValidationException $e) {
            $this->back('domains', $e->getMessage());
        }
    }

    public function verifyDomain(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $id = (int) ($_POST['domain_id'] ?? 0);
        $result = $this->domainService->verify($id, (int) $user['id']);

        if ($result['verified']) {
            $this->back('domains');
        }

        $this->back('domains', $result['error'] ?? 'Xác minh thất bại, vui lòng thử lại.');
    }

    public function deleteDomain(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $this->domainService->delete((int) ($_POST['domain_id'] ?? 0), (int) $user['id']);
        $this->back('domains');
    }

    public function storeUtm(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $data = $this->utmFields();
        if (trim((string) $data['name']) === '' || mb_strlen((string) $data['name'], 'UTF-8') > 100) {
            $this->back('utms', 'Tên profile không hợp lệ.');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($this->utmProfileRepository->findById($id, (int) $user['id']) === null) {
                $this->back('utms');
            }
            $this->utmProfileRepository->update($id, (int) $user['id'], (string) $data['name'], $data);
        } else {
            $this->utmProfileRepository->create((int) $user['id'], (string) $data['name'], $data);
        }

        $this->back('utms');
    }

    public function deleteUtm(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $this->utmProfileRepository->delete((int) ($_POST['utm_id'] ?? 0), (int) $user['id']);
        $this->back('utms');
    }

    public function saveMeta(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $adAccount = trim((string) ($_POST['meta_ad_account'] ?? ''));
        $token = trim((string) ($_POST['meta_token'] ?? ''));

        $this->userSettingsRepository->set((int) $user['id'], 'meta_ad_account', $adAccount);
        if ($token !== '') {
            $this->userSettingsRepository->set((int) $user['id'], 'meta_token', $token);
        }

        $this->back('demographics');
    }

    public function fetchDemographics(): never
    {
        $user = $this->guard();
        $this->requireCsrf();
        $userId = (int) $user['id'];

        $adAccount = $this->userSettingsRepository->get($userId, 'meta_ad_account') ?? '';
        $token = $this->userSettingsRepository->get($userId, 'meta_token') ?? '';

        if ($adAccount === '' || $token === '') {
            $this->back('demographics', 'Cần cấu hình Ad Account ID và Access Token trước.');
        }

        try {
            $payload = $this->metaAudience->fetch($adAccount, $token);
            $this->demographicRepository->saveSnapshot($userId, $payload);
            $this->back('demographics');
        } catch (\RuntimeException $e) {
            $this->back('demographics', $e->getMessage());
        }
    }

    public function clearDemographics(): never
    {
        $user = $this->guard();
        $this->requireCsrf();
        $userId = (int) $user['id'];

        $this->demographicRepository->deleteAll($userId);
        $this->userSettingsRepository->delete($userId, 'meta_ad_account');
        $this->userSettingsRepository->delete($userId, 'meta_token');

        $this->back('demographics');
    }

    private function utmFields(): array
    {
        return [
            'name'         => trim((string) ($_POST['name'] ?? '')),
            'utm_campaign' => trim((string) ($_POST['utm_campaign'] ?? '') ?: ''),
            'utm_medium'   => trim((string) ($_POST['utm_medium'] ?? '') ?: ''),
            'utm_source'   => trim((string) ($_POST['utm_source'] ?? '') ?: ''),
            'utm_term'     => trim((string) ($_POST['utm_term'] ?? '') ?: ''),
            'utm_content'  => trim((string) ($_POST['utm_content'] ?? '') ?: ''),
        ];
    }

    private function guard(): array
    {
        $user = \App\current_user();
        if ($user === null) {
            \App\redirect(url_for('dang-nhap'), 302);
        }

        return $user;
    }

    private function requireCsrf(): void
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('dashboard'), 302);
        }
    }

    private function back(string $tab, ?string $error = null): never
    {
        $url = url_for('dashboard') . '?tab=' . $tab;
        if ($error !== null) {
            $url .= '&error=' . urlencode($error);
        } else {
            $url .= '&ok=1';
        }

        \App\redirect($url, 302);
    }
}

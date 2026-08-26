<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\FolderRepository;
use App\Repository\UrlRepository;
use App\Repository\UserRepository;
use App\Security\Csrf;

final class DashboardController
{
    /** Các tab hợp lệ — menu sidebar. */
    private const TABS = ['tong-quan', 'links', 'folder', 'tai-khoan', 'cai-dat'];

    public function __construct(
        private readonly UrlRepository $urlRepository,
        private readonly FolderRepository $folderRepository,
        private readonly UserRepository $userRepository,
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
            'flashOk'      => isset($_GET['ok']) && $_GET['ok'] === '1',
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

        $name = trim((string) ($_POST['display_name'] ?? ''));
        if (mb_strlen($name, 'UTF-8') <= 100) {
            $this->userRepository->updateDisplayName((int) $user['id'], $name);
        }

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

    private function requireCsrf(): void
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('dashboard'), 302);
        }
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
}

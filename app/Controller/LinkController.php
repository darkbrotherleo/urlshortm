<?php
declare(strict_types=1);

namespace App\Controller;

use App\Config;
use App\Repository\FolderRepository;
use App\Repository\PixelRepository;
use App\Repository\UrlRepository;
use App\Security\Csrf;
use App\Security\LinkType;
use App\Service\LinkService;
use App\Service\LinkValidationException;

final class LinkController
{
    private const FORM_FIELDS = [
        'link_type', 'target', 'title', 'description', 'thumbnail', 'pixels',
        'utm_campaign', 'utm_medium', 'utm_source', 'utm_term', 'utm_content',
        'custom_slug', 'folder_id', 'password', 'password_enabled', 'starts_at', 'ends_at', 'domain',
    ];

    public function __construct(
        private readonly UrlRepository $urlRepository,
        private readonly FolderRepository $folderRepository,
        private readonly PixelRepository $pixelRepository,
        private readonly LinkService $linkService,
        private readonly LinkType $linkType,
        private readonly Csrf $csrf
    ) {
    }

    public function createForm(): never
    {
        $user = $this->guard();

        $this->renderForm('create', $this->defaultValues(), $user, null, null);
    }

    public function store(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        try {
            $data = $_POST;
            $data['thumbnail'] = $this->handleThumbnailUpload(null) ?? '';

            $this->linkService->create($data, (int) $user['id']);
            $this->back();
        } catch (LinkValidationException $e) {
            $this->renderForm('create', $this->valuesFromPost(), $user, $e->getMessage(), null);
        }
    }

    public function editForm(int $id): never
    {
        $user = $this->guard();

        $link = $this->urlRepository->findById($id, (int) $user['id']);
        if ($link === null) {
            $this->back();
        }

        $this->renderForm('edit', $this->valuesFromRow($link), $user, null, $link);
    }

    public function update(int $id): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $existing = $this->urlRepository->findById($id, (int) $user['id']);
        if ($existing === null) {
            $this->back();
        }

        try {
            $data = $_POST;
            $thumb = $this->handleThumbnailUpload($existing);
            $data['thumbnail'] = $thumb ?? ($existing['thumbnail'] ?? '');

            $this->linkService->update($id, $data, (int) $user['id']);
            $this->back();
        } catch (LinkValidationException $e) {
            $this->renderForm('edit', $this->valuesFromPost(), $user, $e->getMessage(), $existing);
        }
    }

    public function destroy(int $id): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $this->linkService->delete($id, (int) $user['id']);
        $this->back();
    }

    public function bulk(): never
    {
        $user = $this->guard();
        $this->requireCsrf();

        $userId = (int) $user['id'];
        $ids = $this->parseIds($_POST['ids'] ?? '');
        $action = (string) ($_POST['bulk_action'] ?? 'delete');

        if ($ids !== []) {
            if ($action === 'move') {
                $folderId = null;
                if (isset($_POST['folder_id']) && ctype_digit((string) $_POST['folder_id'])) {
                    $candidate = (int) $_POST['folder_id'];
                    if ($this->folderRepository->findById($candidate, $userId) !== null) {
                        $folderId = $candidate;
                    }
                }
                $this->urlRepository->bulkMove($ids, $folderId, $userId);
            } else {
                $this->urlRepository->bulkDelete($ids, $userId);
            }
        }

        $this->back();
    }

    private function renderForm(string $mode, array $values, array $user, ?string $error, ?array $link): never
    {
        $folders = $this->folderRepository->findByUser((int) $user['id']);
        $pixels = $this->pixelRepository->findAllActive();

        http_response_code($error !== null ? 400 : 200);
        echo \App\render('link-form', [
            'title'    => $mode === 'create' ? 'Tạo Link Mới' : 'Chỉnh sửa link',
            'mode'     => $mode,
            'values'   => $values,
            'folders'  => $folders,
            'pixels'   => $pixels,
            'types'    => LinkType::LABELS,
            'error'    => $error,
            'link'     => $link,
            'base'     => \App\base_url(),
            'csrf'     => $this->csrf,
        ]);
        exit;
    }

    private function defaultValues(): array
    {
        return [
            'link_type'      => 'link',
            'target'         => '',
            'title'          => '',
            'description'    => '',
            'thumbnail'      => '',
            'pixels'         => '',
            'utm_campaign'   => '',
            'utm_medium'     => '',
            'utm_source'     => '',
            'utm_term'       => '',
            'utm_content'    => '',
            'custom_slug'    => '',
            'folder_id'      => '',
            'password'       => '',
            'password_enabled' => '0',
            'starts_at'      => '',
            'ends_at'        => '',
            'domain'         => '',
        ];
    }

    private function valuesFromPost(): array
    {
        $values = [];
        foreach (self::FORM_FIELDS as $key) {
            $values[$key] = (string) ($_POST[$key] ?? '');
        }

        return $values;
    }

    private function valuesFromRow(array $row): array
    {
        $pixels = '';
        $decoded = json_decode((string) ($row['pixels'] ?? ''), true);
        if (is_array($decoded)) {
            $pixels = implode(', ', $decoded);
        }

        return [
            'link_type'      => $row['link_type'] ?? 'link',
            'target'         => $row['target_url'] ?? '',
            'title'          => $row['title'] ?? '',
            'description'    => $row['description'] ?? '',
            'thumbnail'      => $row['thumbnail'] ?? '',
            'pixels'         => $pixels,
            'utm_campaign'   => $row['utm_campaign'] ?? '',
            'utm_medium'     => $row['utm_medium'] ?? '',
            'utm_source'     => $row['utm_source'] ?? '',
            'utm_term'       => $row['utm_term'] ?? '',
            'utm_content'    => $row['utm_content'] ?? '',
            'custom_slug'    => $row['slug'] ?? '',
            'folder_id'      => (string) ($row['folder_id'] ?? ''),
            'password'       => '',
            'password_enabled' => !empty($row['password_hash']) ? '1' : '0',
            'starts_at'      => $this->toDatetimeLocal((string) ($row['starts_at'] ?? '')),
            'ends_at'        => $this->toDatetimeLocal((string) ($row['ends_at'] ?? '')),
            'domain'         => $row['domain'] ?? '',
        ];
    }

    private function toDatetimeLocal(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ts = strtotime($value);

        return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
    }

    /**
     * Xử lý upload thumbnail. Trả về đường dẫn web nếu upload, null nếu không upload.
     *
     * @param array<string,mixed>|null $existing
     *
     * @throws LinkValidationException
     */
    private function handleThumbnailUpload(?array $existing): ?string
    {
        $file = $_FILES['thumbnail'] ?? null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new LinkValidationException('Tải ảnh thumbnail thất bại, vui lòng thử lại.');
        }

        $max = (int) Config::get('app.uploads.max_bytes', 5 * 1024 * 1024);
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $max) {
            throw new LinkValidationException('Ảnh thumbnail quá lớn (tối đa 5MB).');
        }

        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false) {
            throw new LinkValidationException('File không phải ảnh hợp lệ.');
        }

        $extensions = (array) Config::get('app.uploads.extensions', []);
        $mime = $info['mime'] ?? '';
        $ext = $extensions[$mime] ?? null;
        if ($ext === null) {
            throw new LinkValidationException('Định dạng ảnh không hỗ trợ (JPG, PNG, WEBP, GIF).');
        }

        $dir = (string) Config::get('app.uploads.dir', dirname(__DIR__, 2) . '/uploads');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new LinkValidationException('Không thể lưu ảnh thumbnail ngay lúc này.');
        }

        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            throw new LinkValidationException('Không thể lưu ảnh thumbnail.');
        }

        // Xoá ảnh cũ nếu là ảnh đã upload
        if ($existing !== null && str_starts_with((string) ($existing['thumbnail'] ?? ''), '/uploads/')) {
            @unlink(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . basename((string) $existing['thumbnail']));
        }

        return '/uploads/' . $name;
    }

    private function parseIds(string $raw): array
    {
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            if (ctype_digit(trim($part))) {
                $ids[] = (int) $part;
            }
        }

        return array_values(array_unique($ids));
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

    private function back(): never
    {
        \App\redirect(url_for('dashboard') . '?tab=links&ok=1', 302);
    }
}

<?php
declare(strict_types=1);

namespace App\Service;

use App\Config;
use App\Repository\UrlRepository;
use App\Security\LinkType;
use App\Security\SlugGenerator;
use App\Security\SlugValidator;

final class LinkService
{
    public function __construct(
        private readonly UrlRepository $repository,
        private readonly LinkType $linkType,
        private readonly SlugGenerator $slugGenerator,
        private readonly SlugValidator $slugValidator
    ) {
    }

    /**
     * @param array<string,mixed> $data từ form tạo link
     *
     * @return array{id:int,slug:string}
     *
     * @throws LinkValidationException
     */
    public function create(array $data, int $userId): array
    {
        $fields = $this->prepareFields($data, $userId, null);

        $slug = $this->resolveSlug((string) ($data['custom_slug'] ?? ''), null);
        $fields['slug'] = $slug;

        $id = $this->repository->create($fields);

        return ['id' => $id, 'slug' => $slug];
    }

    /**
     * @param array<string,mixed> $data từ form sửa link
     *
     * @throws LinkValidationException
     */
    public function update(int $id, array $data, int $userId): array
    {
        $existing = $this->repository->findById($id, $userId);
        if ($existing === null) {
            throw new LinkValidationException('Không tìm thấy link.');
        }

        $fields = $this->prepareFields($data, $userId, $existing);

        $customSlug = trim((string) ($data['custom_slug'] ?? ''));
        if ($customSlug !== '' && $customSlug !== $existing['slug']) {
            $this->assertSlugAvailable($customSlug, $id);
            $this->repository->updateSlug($id, $customSlug, $userId);
        }

        $this->repository->update($id, $fields, $userId);

        return ['id' => $id, 'slug' => $customSlug !== '' ? $customSlug : $existing['slug']];
    }

    public function delete(int $id, int $userId): void
    {
        $this->repository->delete($id, $userId);
    }

    /**
     * @param array<string,mixed>      $data
     * @param array<string,mixed>|null $existing
     *
     * @return array<string,mixed> fields sẵn sàng để insert/update
     */
    private function prepareFields(array $data, int $userId, ?array $existing): array
    {
        $type = (string) ($data['link_type'] ?? 'link');
        if (!$this->linkType->isSupported($type)) {
            throw new LinkValidationException('Loại link không được hỗ trợ.');
        }

        $target = $this->linkType->build($type, (string) ($data['target'] ?? ''));

        $folderId = null;
        if (isset($data['folder_id']) && $data['folder_id'] !== '' && ctype_digit((string) $data['folder_id'])) {
            $folderId = (int) $data['folder_id'];
        }

        $pixels = $this->parsePixels((string) ($data['pixels'] ?? ''));

        // Mật khẩu theo toggle bật/tắt. Bật + nhập pass -> đặt/đổi;
        // bật + bỏ trống (link đã có pass) -> giữ; tắt -> xoá mật khẩu.
        $passwordHash = null;
        $enabled = !empty($data['password_enabled']);
        $password = (string) ($data['password'] ?? '');

        if ($enabled || $password !== '') {
            if ($password !== '') {
                if (strlen($password) < 6) {
                    throw new LinkValidationException('Mật khẩu phải có ít nhất 6 ký tự.');
                }
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            } elseif ($existing !== null) {
                $passwordHash = $existing['password_hash'] ?? null;
            }
        }

        [$startsAt, $endsAt] = $this->resolveTimes(
            (string) ($data['starts_at'] ?? ''),
            (string) ($data['ends_at'] ?? '')
        );

        $normalize = static fn (?string $v): ?string => ($v === null ? null : trim($v));
        $text = static fn (string $key): ?string => $normalize((string) ($data[$key] ?? '') ?: null);

        return [
            'slug'          => '',
            'target_url'    => $target,
            'user_id'       => $userId,
            'folder_id'     => $folderId,
            'link_type'     => $type,
            'title'         => $text('title'),
            'description'   => $text('description'),
            'thumbnail'     => $text('thumbnail'),
            'pixels'        => $pixels,
            'utm_campaign'  => $text('utm_campaign'),
            'utm_medium'    => $text('utm_medium'),
            'utm_source'    => $text('utm_source'),
            'utm_term'      => $text('utm_term'),
            'utm_content'   => $text('utm_content'),
            'domain'        => $text('domain'),
            'password_hash' => $passwordHash,
            'starts_at'     => $startsAt,
            'ends_at'       => $endsAt,
        ];
    }

    private function parsePixels(string $raw): ?string
    {
        $ids = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $v): bool => $v !== ''));
        if ($ids === []) {
            return null;
        }

        return json_encode($ids, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{0:?string,1:?string} [starts_at, ends_at]
     */
    private function resolveTimes(string $start, string $end): array
    {
        $start = $this->toDatetime($start);
        $end = $this->toDatetime($end);

        if ($start !== null && $end !== null && $start >= $end) {
            throw new LinkValidationException('Thời gian kết thúc phải sau thời gian bắt đầu.');
        }

        return [$start, $end];
    }

    private function toDatetime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);
        $ts = strtotime($value);
        if ($ts === false) {
            throw new LinkValidationException('Thời gian không hợp lệ.');
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function resolveSlug(string $customSlug, ?int $excludeId): string
    {
        $customSlug = trim($customSlug);
        if ($customSlug !== '') {
            if (!$this->slugValidator->isValid($customSlug)) {
                throw new LinkValidationException('Phần sau của link chỉ gồm 3-16 ký tự (a-z, A-Z, 0-9, gạch ngang hoặc gạch dưới).');
            }
            if ($this->slugValidator->isReserved($customSlug)) {
                throw new LinkValidationException('Phần sau của link này đã được dùng cho hệ thống.');
            }
            $this->assertSlugAvailable($customSlug, $excludeId);

            return $customSlug;
        }

        $length = (int) Config::get('app.slug_length', 6);
        $charset = (string) Config::get('app.slug_charset', '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz');
        $retry = (int) Config::get('app.slug_retry', 5);

        for ($i = 0; $i < $retry; $i++) {
            $slug = $this->slugGenerator->generate($charset, $length);
            if ($this->repository->findBySlug($slug) === null) {
                return $slug;
            }
        }

        throw new LinkValidationException('Không thể sinh link ngay lúc này, vui lòng thử lại.');
    }

    private function assertSlugAvailable(string $slug, ?int $excludeId): void
    {
        $existing = $this->repository->findBySlug($slug);
        if ($existing === null) {
            return;
        }
        if ($excludeId !== null && (int) $existing['id'] === $excludeId) {
            return;
        }

        throw new LinkValidationException('Phần sau của link này đã được sử dụng, hãy chọn phần khác.');
    }
}

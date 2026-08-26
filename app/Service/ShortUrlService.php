<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\UrlRepository;
use App\Security\RateLimiter;
use App\Security\SlugGenerator;
use App\Security\UrlNormalizer;
use App\Config;

final class ShortUrlService
{
    public function __construct(
        private readonly UrlRepository $repository,
        private readonly UrlNormalizer $normalizer,
        private readonly SlugGenerator $slugGenerator,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    /**
     * @param string|null $userId gán link cho user đang đăng nhập (null = ẩn danh)
     *
     * @return array{slug:string,target_url:string,click_count:int}
     *
     * @throws UrlValidationException   URL không hợp lệ
     * @throws RateLimitExceededException vượt ngưỡng tạo link
     */
    public function create(string $rawTarget, string $ip, ?int $userId = null): array
    {
        $target = $this->normalizer->normalize($rawTarget);
        if ($target === null) {
            throw new UrlValidationException('URL không hợp lệ. Vui lòng nhập địa chỉ http:// hoặc https://.');
        }

        $limit = (int) Config::get('app.rate_limit.shorten.limit', 50);
        $window = (int) Config::get('app.rate_limit.shorten.window', 3600);

        if (!$this->rateLimiter->allow('shorten', $ip, $limit, $window)) {
            throw new RateLimitExceededException('Quá nhiều yêu cầu. Vui lòng thử lại sau 1 giờ.');
        }

        $length = (int) Config::get('app.slug_length', 6);
        $charset = (string) Config::get('app.slug_charset', '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz');
        $retry = (int) Config::get('app.slug_retry', 5);
        $created = false;

        for ($i = 0; $i < $retry; $i++) {
            $slug = $this->slugGenerator->generate($charset, $length);

            try {
                $this->repository->insert($slug, $target, $userId);
                $created = true;
                break;
            } catch (\PDOException $e) {
                if (!$this->isUniqueViolation($e)) {
                    throw $e;
                }
                // slug trùng -> thử lại
            }
        }

        if (!$created) {
            throw new \RuntimeException('Không thể tạo link ngắn ngay lúc này, vui lòng thử lại.');
        }

        return [
            'slug'        => $slug,
            'target_url'  => $target,
            'click_count' => 0,
        ];
    }

    /**
     * @return array{slug:string,target_url:string,click_count:int}|null
     */
    public function resolve(string $slug): ?array
    {
        $row = $this->repository->findBySlug($slug);
        if ($row === null) {
            return null;
        }

        $this->repository->incrementClicks($slug);

        return [
            'slug'        => $row['slug'],
            'target_url'  => $row['target_url'],
            'click_count' => (int) $row['click_count'] + 1,
        ];
    }

    private function isUniqueViolation(\PDOException $e): bool
    {
        $code = (string) $e->getCode();
        return str_starts_with($code, '23') || $code === 'HY000';
    }
}

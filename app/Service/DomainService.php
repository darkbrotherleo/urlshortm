<?php
declare(strict_types=1);

namespace App\Service;

use App\Config;
use App\Repository\DomainRepository;

final class DomainService
{
    public const TXT_PREFIX = 'urlshortm-verify=';

    public function __construct(private readonly DomainRepository $repository)
    {
    }

    /**
     * Đăng ký domain cho user. Trả về id.
     *
     * @throws LinkValidationException khi domain không hợp lệ / trùng
     */
    public function register(int $userId, string $domain): int
    {
        $domain = $this->normalizeDomain($domain);

        if (preg_match('/^(?=.{3,190}$)([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain) !== 1
            && !$this->isTestDomain($domain)) {
            throw new LinkValidationException('Tên miền không hợp lệ. Ví dụ: link.vidu.com');
        }
        if ($this->repository->existsDomain($domain)) {
            throw new LinkValidationException('Tên miền này đã được thêm.');
        }

        $token = bin2hex(random_bytes(16));
        $id = $this->repository->create($userId, $domain, $token);

        // Domain test (localhost) tự xác minh để dùng thử.
        if ($this->isTestDomain($domain)) {
            $this->repository->markVerified($id, $userId);
        }

        return $id;
    }

    /**
     * Xác minh domain: test domain tự đạt; nền tảng khác kiểm tra bản ghi DNS TXT.
     *
     * @return array{verified:bool,error:?string}
     */
    public function verify(int $domainId, int $userId): array
    {
        $domain = $this->repository->findById($domainId, $userId);
        if ($domain === null) {
            return ['verified' => false, 'error' => 'Không tìm thấy domain.'];
        }

        if ((int) $domain['is_verified'] === 1) {
            return ['verified' => true, 'error' => null];
        }

        if ($this->isTestDomain((string) $domain['domain'])) {
            $this->repository->markVerified($domainId, $userId);

            return ['verified' => true, 'error' => null];
        }

        $token = (string) $domain['verification_token'];
        $expected = self::TXT_PREFIX . $token;

        $found = false;
        if ($this->dnsCheckEnabled()) {
            $records = @dns_get_record((string) $domain['domain'], DNS_TXT);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['txt']) && stripos((string) $record['txt'], $expected) !== false) {
                        $found = true;
                        break;
                    }
                }
            }
        }

        if ($found) {
            $this->repository->markVerified($domainId, $userId);

            return ['verified' => true, 'error' => null];
        }

        $message = 'Chưa tìm thấy bản ghi TXT. Hãy thêm bản ghi rồi bấm Xác minh lại (DNS có thể mất vài phút).';
        $this->repository->setLastError($domainId, $userId, $message);

        return ['verified' => false, 'error' => $message];
    }

    public function delete(int $domainId, int $userId): void
    {
        $this->repository->delete($domainId, $userId);
    }

    public static function isTestDomain(string $domain): bool
    {
        $domain = strtolower($domain);

        if (in_array($domain, ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        // `.test` / `.localhost` là TLD dành riêng cho local (RFC 6761) — tự xác minh.
        return preg_match('/\.(test|localhost)$/', $domain) === 1;
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = rtrim((string) $domain, '/');

        return (string) $domain;
    }

    private function dnsCheckEnabled(): bool
    {
        return (bool) Config::get('app.domains.dns_check', true);
    }
}

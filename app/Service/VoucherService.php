<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\VoucherRepository;

/**
 * Xử lý voucher giảm giá: kiểm tra hợp lệ, tính giảm, ghi nhận sử dụng.
 */
final class VoucherService
{
    public function __construct(private readonly VoucherRepository $vouchers)
    {
    }

    /**
     * @return array{voucher:array<string,mixed>,discount:float,amount_after:float}
     *
     * @throws \RuntimeException khi voucher không hợp lệ
     */
    public function redeem(string $code, int $userId, float $amount): array
    {
        $voucher = $this->vouchers->findByCode(trim($code));
        if ($voucher === null) {
            throw new \RuntimeException('Mã voucher không tồn tại.');
        }
        if ((int) $voucher['is_active'] !== 1) {
            throw new \RuntimeException('Voucher đã bị ngừng chạy.');
        }
        if ((int) $voucher['used_count'] >= (int) $voucher['usage_limit']) {
            throw new \RuntimeException('Voucher đã hết lượt sử dụng.');
        }
        if (!empty($voucher['starts_at']) && strtotime((string) $voucher['starts_at']) > time()) {
            throw new \RuntimeException('Voucher chưa bắt đầu có hiệu lực.');
        }
        if (!empty($voucher['ends_at']) && strtotime((string) $voucher['ends_at']) < time()) {
            throw new \RuntimeException('Voucher đã hết hạn.');
        }
        if ($voucher['per_user'] === 'once' && $this->vouchers->countUserUsages((int) $voucher['id'], $userId) > 0) {
            throw new \RuntimeException('Bạn đã dùng voucher này rồi.');
        }

        $discount = $this->discount((string) $voucher['discount_type'], (float) $voucher['discount_value'], $amount);
        $amountAfter = max(0, round($amount - $discount, 2));

        return ['voucher' => $voucher, 'discount' => $discount, 'amount_before' => $amount, 'amount_after' => $amountAfter];
    }

    public function discount(string $type, float $value, float $amount): float
    {
        if ($type === 'percent') {
            return round($amount * min(100, max(0, $value)) / 100, 2);
        }

        return round(min($amount, max(0, $value)), 2);
    }

    public function consume(array $voucher, int $userId, int $orderId, float $before, float $after): void
    {
        $this->vouchers->recordUsage((int) $voucher['id'], $orderId, $userId, 'success', $before, $after);
        $this->vouchers->incrementUsed((int) $voucher['id']);
    }
}

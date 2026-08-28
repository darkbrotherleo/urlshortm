<?php
/** @var array<string,mixed> $user */
/** @var array<string,mixed> $order */
/** @var array<string,mixed>|null $plan */

if (!function_exists('__viet_num_words')) {
    function __viet_num_words(float $number): string
    {
        $words = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
        $units = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];
        $num = (int) round($number);
        if ($num === 0) {
            return 'Không đồng';
        }
        $result = '';
        $group = 0;
        while ($num > 0) {
            $three = $num % 1000;
            $num = intdiv($num, 1000);
            if ($three > 0) {
                $part = '';
                $hundreds = intdiv($three, 100);
                $tens = intdiv($three % 100, 10);
                $ones = $three % 10;
                if ($hundreds > 0) {
                    $part .= $words[$hundreds] . ' trăm ';
                    if ($tens === 0 && $ones > 0) {
                        $part .= 'lẻ ';
                    }
                }
                if ($tens === 1) {
                    $part .= 'mười ' . ($ones > 0 ? $words[$ones] : '');
                } elseif ($tens > 1) {
                    $part .= $words[$tens] . ' mươi ' . ($ones > 0 ? ($ones === 1 ? 'mốt' : $words[$ones]) : '');
                } elseif ($ones > 0) {
                    $part .= $words[$ones];
                }
                $result = trim($part) . ' ' . $units[$group] . ' ' . $result;
            }
            $group++;
        }

        return ucfirst(trim($result)) . ' đồng';
    }
}

$periodLabel = match ($order['billing_period'] ?? 'monthly') {
    'yearly' => '1 năm', 'lifetime' => 'trọn đời', default => '1 tháng',
};
$amount = (float) $order['amount'];
$vatRate = 0.1;
$vat = round($amount * $vatRate, 2);
$total = $amount + $vat;
$priceStr = fn (float $n): string => number_format($n, 0, ',', '.');
$invoiceNo = str_pad((string) $order['id'], 7, '0', STR_PAD_LEFT);
// Người bán lấy từ cài đặt "Hoá đơn" trong Cài đặt website.
$sellerName = trim((string) ($seller['name'] ?? ''));
if ($sellerName === '') {
    $sellerName = 'CÔNG TY TNHH URLSHORTM';
}
$sellerMst = trim((string) ($seller['tax_id'] ?? ''));
$sellerAddress = trim((string) ($seller['address'] ?? ''));
$sellerPhone = trim((string) ($seller['phone'] ?? ''));
$sellerType = (string) ($seller['tax_type'] ?? '');
$sellerLine = [];
if ($sellerMst !== '') {
    $sellerLine[] = 'Mã số thuế: ' . $sellerMst;
}
if ($sellerAddress !== '') {
    $sellerLine[] = 'Địa chỉ: ' . $sellerAddress;
}
if ($sellerPhone !== '') {
    $sellerLine[] = 'Điện thoại: ' . $sellerPhone;
}
$buyerName = ($user['invoice_name'] ?? '') ?: (($user['company_name'] ?? '') ?: ($user['display_name'] ?: $user['email']));
$buyerMst = ($user['tax_id'] ?? '') !== '' ? $user['tax_id'] : '(không có)';
$buyerAddress = trim((($user['address'] ?? '') . ($user['city'] ? ', ' . $user['city'] : '')) ?: '(không có)');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= \App\escape($title) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", serif; color: #111; margin: 0; background: #E5E7EB; }
        .invoice-toolbar { text-align: center; padding: 14px; }
        .invoice-toolbar button { font-size: 14px; padding: 8px 20px; cursor: pointer; }
        .invoice {
            width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff; padding: 18mm 16mm;
        }
        .inv-center { text-align: center; }
        .inv-title { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .inv-sub { font-size: 12px; color: #333; }
        .inv-meta { display: flex; justify-content: space-between; font-size: 13px; margin-top: 8px; }
        .inv-block { font-size: 13px; line-height: 1.6; }
        .inv-block h3 { font-size: 14px; margin: 14px 0 2px; text-transform: uppercase; }
        .inv-block p { margin: 0; }
        .inv-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 12px; }
        .inv-table th, .inv-table td { border: 1px solid #111; padding: 6px 8px; text-align: center; }
        .inv-table td.l, .inv-table th.l { text-align: left; }
        .inv-table td.r, .inv-table th.r { text-align: right; }
        .inv-totals { width: 100%; font-size: 13px; margin-top: 10px; }
        .inv-totals td { padding: 3px 8px; }
        .inv-totals td.lab { text-align: right; width: 60%; }
        .inv-totals td.val { text-align: right; width: 40%; font-weight: 700; }
        .inv-words { font-size: 13px; margin-top: 8px; font-style: italic; }
        .inv-signs { display: flex; justify-content: space-between; margin-top: 70px; font-size: 13px; }
        .inv-signs div { text-align: center; width: 45%; }
        .inv-note { font-size: 11px; margin-top: 30px; border-top: 1px solid #999; padding-top: 6px; color: #333; }
        @media print {
            body { background: #fff; }
            .invoice-toolbar { display: none; }
            .invoice { margin: 0; box-shadow: none; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="invoice-toolbar">
        <button onclick="window.print()">In hoá đơn</button>
        <a href="<?= \App\url_for('dashboard') ?>"><button>Về bảng điều khiển</button></a>
    </div>

    <div class="invoice">
        <div class="inv-center">
            <div class="inv-sub">Mẫu số: 01GTKT0/001</div>
            <div class="inv-title">HOÁ ĐƠN GIÁ TRỊ GIA TĂNG</div>
            <div class="inv-sub">Bản thể hiện của hóa đơn điện tử</div>
            <div class="inv-meta">
                <span>Ký hiệu: 1C<?= \App\escape($order['order_code']) ?>TAA</span>
                <span>Số hoá đơn: <?= \App\escape($invoiceNo) ?></span>
            </div>
        </div>

        <div class="inv-block">
            <h3>Người bán<?= $sellerType === 'individual' ? ' (Cá nhân)' : ($sellerType === 'business' ? ' (Doanh nghiệp)' : '') ?></h3>
            <p><b><?= \App\escape($sellerName) ?></b></p>
            <?php foreach ($sellerLine as $line): ?>
                <p><?= \App\escape($line) ?></p>
            <?php endforeach; ?>
            <?php if ($sellerLine === []): ?>
                <p>Mã số thuế: —</p>
            <?php endif; ?>
        </div>

        <div class="inv-block">
            <h3>Người mua hàng</h3>
            <p><b><?= \App\escape($buyerName) ?></b></p>
            <p>Mã số thuế: <?= \App\escape($buyerMst) ?></p>
            <p>Địa chỉ: <?= \App\escape($buyerAddress) ?></p>
            <p>Hình thức thanh toán: PayPal</p>
        </div>

        <table class="inv-table">
            <thead>
                <tr>
                    <th style="width:6%">STT</th>
                    <th class="l" style="width:48%">Tên hàng hoá, dịch vụ</th>
                    <th style="width:9%">ĐVT</th>
                    <th style="width:9%">SL</th>
                    <th class="r" style="width:14%">Đơn giá</th>
                    <th class="r" style="width:14%">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td class="l">Gói dịch vụ <?= \App\escape($order['plan_name']) ?> (<?= \App\escape($periodLabel) ?>)</td>
                    <td>gói</td>
                    <td>1</td>
                    <td class="r"><?= \App\escape($priceStr($amount)) ?></td>
                    <td class="r"><?= \App\escape($priceStr($amount)) ?></td>
                </tr>
            </tbody>
        </table>

        <table class="inv-totals">
            <tr><td class="lab">Cộng tiền hàng hoá, dịch vụ:</td><td class="val"><?= \App\escape($priceStr($amount)) ?></td></tr>
            <tr><td class="lab">Thuế suất GTGT (<?= (int) ($vatRate * 100) ?>%):</td><td class="val"><?= \App\escape($priceStr($vat)) ?></td></tr>
            <tr><td class="lab">Tổng cộng tiền thanh toán:</td><td class="val"><?= \App\escape($priceStr($total)) ?></td></tr>
        </table>

        <p class="inv-words">Số tiền viết bằng chữ: <?= \App\escape(__viet_num_words($total)) ?></p>

        <div class="inv-signs">
            <div>
                <p><b>Người mua hàng</b></p>
                <p>(Ký, ghi rõ họ tên)</p>
            </div>
            <div>
                <p><b>Người bán hàng</b></p>
                <p>(Ký, ghi rõ họ tên)</p>
            </div>
        </div>

        <div class="inv-note">
            Hoá đơn được xuất tự động từ hệ thống UrlShortM sau khi thanh toán thành công. Khách hàng cần đối chiếu
            thông tin mã số thuế, tên người mua trước khi yêu cầu điều chỉnh hoá đơn.
        </div>
    </div>
</body>
</html>

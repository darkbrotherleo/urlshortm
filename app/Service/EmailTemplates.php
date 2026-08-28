<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Khung thiết kế email (HTML, inline CSS cho email client) + các template có sẵn.
 */
final class EmailTemplates
{
    public function __construct(private readonly SiteSettingsService $site)
    {
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array{subject:string,html:string}
     */
    public function render(string $type, array $data = []): array
    {
        return ['subject' => $this->subject($type, $data), 'html' => $this->wrap($this->title($type), $this->body($type, $data))];
    }

    /**
     * @return array<string,string> danh sách template (key => tên hiển thị)
     */
    public function types(): array
    {
        return [
            'purchase_success' => 'Mua hàng thành công',
            'invoice'          => 'Gửi hoá đơn cho khách',
            'registration'     => 'Thông báo đăng ký thành công',
            'forgot_password'  => 'Thông báo lấy lại mật khẩu',
            'activate_account' => 'Kích hoạt tài khoản',
        ];
    }

    private function subject(string $type, array $data): string
    {
        $site = $this->site->siteName();
        return match ($type) {
            'purchase_success' => 'Đặt hàng thành công — ' . ($data['plan_name'] ?? 'Gói dịch vụ') . ' | ' . $site,
            'invoice'          => 'Hoá đơn ' . ($data['invoice_no'] ?? '') . ' | ' . $site,
            'registration'     => 'Chào mừng đến với ' . $site,
            'forgot_password'  => 'Hướng dẫn đặt lại mật khẩu | ' . $site,
            'activate_account' => 'Kích hoạt tài khoản ' . $site,
            default            => 'Email từ ' . $site,
        };
    }

    private function title(string $type): string
    {
        return match ($type) {
            'purchase_success' => 'Thanh toán thành công 🎉',
            'invoice'          => 'Hoá đơn điện tử',
            'registration'     => 'Chào mừng bạn!',
            'forgot_password'  => 'Đặt lại mật khẩu',
            'activate_account' => 'Kích hoạt tài khoản',
            default            => $this->site->siteName(),
        };
    }

    /**
     * @param array<string,mixed> $data
     */
    private function body(string $type, array $data): string
    {
        return match ($type) {
            'purchase_success' => $this->purchaseSuccess($data),
            'invoice'          => $this->invoice($data),
            'registration'     => $this->registration($data),
            'forgot_password'  => $this->forgotPassword($data),
            'activate_account' => $this->activateAccount($data),
            default            => '<p>Cảm ơn bạn đã sử dụng ' . $this->esc($this->site->siteName()) . '.</p>',
        };
    }

    private function purchaseSuccess(array $d): string
    {
        $plan = (string) ($d['plan_name'] ?? '');
        $amount = (string) ($d['amount'] ?? '');
        $orderCode = (string) ($d['order_code'] ?? '');
        $date = (string) ($d['paid_at'] ?? '');
        $name = (string) ($d['name'] ?? 'bạn');
        $link = (string) ($d['dashboard_link'] ?? '#');

        $rows = $this->kvRows([
            'Mã đơn hàng' => $orderCode,
            'Gói dịch vụ' => $plan,
            'Số tiền' => $amount,
            'Ngày thanh toán' => $date,
        ]);

        return '<p>Chào ' . $this->esc($name) . ',</p>'
            . '<p>Thanh toán của bạn đã thành công. Gói <strong>' . $this->esc($plan) . '</strong> đã được kích hoạt và sẵn sàng sử dụng ngay.</p>'
            . $rows
            . $this->button('Về bảng điều khiển', $link);
    }

    private function invoice(array $d): string
    {
        $invoiceNo = (string) ($d['invoice_no'] ?? '');
        $amount = (string) ($d['amount'] ?? '');
        $orderCode = (string) ($d['order_code'] ?? '');
        $link = (string) ($d['invoice_link'] ?? '#');

        $rows = $this->kvRows([
            'Số hoá đơn' => $invoiceNo,
            'Mã đơn hàng' => $orderCode,
            'Tổng tiền thanh toán' => $amount,
        ]);

        return '<p>Chào bạn,</p>'
            . '<p>Hoá đơn điện tử đã được phát hành cho đơn hàng của bạn.</p>'
            . $rows
            . $this->button('Xem / In hoá đơn', $link)
            . '<p style="color:#6B7280;font-size:12px;">Thông tin hoá đơn phù hợp với thông tin mã số thuế bạn đã khai báo trong tài khoản.</p>';
    }

    private function registration(array $d): string
    {
        $name = (string) ($d['name'] ?? 'bạn');
        $email = (string) ($d['email'] ?? '');
        $link = (string) ($d['login_link'] ?? '#');

        $rows = $this->kvRows([
            'Tên tài khoản' => $name,
            'Email đăng nhập' => $email,
        ]);

        return '<p>Xin chào ' . $this->esc($name) . ',</p>'
            . '<p>Tài khoản của bạn đã được tạo thành công tại ' . $this->esc($this->site->siteName()) . '. Bạn có thể bắt đầu rút gọn link và theo dõi lượt mở ngay bây giờ.</p>'
            . $rows
            . $this->button('Đăng nhập ngay', $link);
    }

    private function forgotPassword(array $d): string
    {
        $name = (string) ($d['name'] ?? 'bạn');
        $link = (string) ($d['reset_link'] ?? '#');

        return '<p>Chào ' . $this->esc($name) . ',</p>'
            . '<p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Bấm nút bên dưới để đặt mật khẩu mới.</p>'
            . $this->button('Đặt lại mật khẩu', $link)
            . '<p style="color:#6B7280;font-size:12px;">Nếu bạn không yêu cầu, vui lòng bỏ qua email này. Liên kết có hiệu lực trong 30 phút.</p>';
    }

    private function activateAccount(array $d): string
    {
        $name = (string) ($d['name'] ?? 'bạn');
        $link = (string) ($d['activation_link'] ?? '#');

        return '<p>Chào ' . $this->esc($name) . ',</p>'
            . '<p>Vui lòng bấm nút bên dưới để kích hoạt tài khoản và hoàn tất đăng ký tại ' . $this->esc($this->site->siteName()) . '.</p>'
            . $this->button('Kích hoạt tài khoản', $link)
            . '<p style="color:#6B7280;font-size:12px;">Liên kết kích hoạt có hiệu lực trong 24 giờ.</p>';
    }

    /**
     * @param array<string,string> $items
     */
    private function kvRows(array $items): string
    {
        $html = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:18px 0;font-size:14px;">';
        foreach ($items as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $html .= '<tr>'
                . '<td style="padding:10px 14px;background:#F9FAFB;color:#6B7280;border-bottom:1px solid #EEF0F3;width:45%;">' . $this->esc($label) . '</td>'
                . '<td style="padding:10px 14px;border-bottom:1px solid #EEF0F3;font-weight:700;color:#111827;">' . $this->esc((string) $value) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    private function button(string $label, string $url): string
    {
        return '<p style="margin:22px 0;"><a href="' . $this->esc($url) . '" style="display:inline-block;background:linear-gradient(135deg,#FF6B4A,#FF5E62);color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 26px;border-radius:8px;">' . $this->esc($label) . '</a></p>';
    }

    private function wrap(string $title, string $body): string
    {
        $siteName = $this->esc($this->site->siteName());
        $year = date('Y');

        return '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#F3F4F6;">'
            . '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;background:#ffffff;border-radius:12px;overflow:hidden;">'
            . '<div style="background:linear-gradient(135deg,#FF6B4A,#FF5E62);padding:26px 32px;">'
            . '<div style="color:#ffffff;font-size:19px;font-weight:bold;">' . $siteName . '</div>'
            . '</div>'
            . '<div style="padding:26px 32px;color:#1F2937;font-size:15px;line-height:1.7;">'
            . '<h1 style="margin:0 0 6px;font-size:20px;color:#111827;">' . $this->esc($title) . '</h1>'
            . $body
            . '</div>'
            . '<div style="padding:18px 32px;background:#F9FAFB;color:#6B7280;font-size:12px;text-align:center;">'
            . '© ' . $year . ' ' . $siteName . ' — Dịch vụ rút gọn &amp; theo dõi link<br>'
            . 'Nếu bạn không yêu cầu email này, vui lòng bỏ qua.'
            . '</div>'
            . '</div></body></html>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

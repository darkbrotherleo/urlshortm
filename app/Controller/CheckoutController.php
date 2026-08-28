<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\PackageRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\Csrf;
use App\Service\PayPalService;
use App\Service\VoucherService;

final class CheckoutController
{
    public function __construct(
        private readonly PackageRepository $packages,
        private readonly OrderRepository $orders,
        private readonly SettingRepository $settings,
        private readonly UserRepository $users,
        private readonly VoucherService $voucher,
        private readonly Csrf $csrf
    ) {
    }

    public function index(): never
    {
        $user = $this->guard();

        $planId = (isset($_GET['plan']) && ctype_digit((string) $_GET['plan'])) ? (int) $_GET['plan'] : null;
        $plans = $this->packages->findAll();
        $plan = null;
        if ($planId !== null) {
            $plan = $this->packages->findById($planId);
        }
        if ($planId !== null && ($plan === null || (int) $plan['is_active'] !== 1)) {
            $plan = null;
            $planId = null;
        }

        // Voucher áp dụng
        $voucherInfo = null;
        if ($plan !== null && trim((string) ($_GET['voucher'] ?? '')) !== '') {
            try {
                $voucherInfo = $this->voucher->redeem(
                    (string) $_GET['voucher'],
                    (int) $user['id'],
                    (float) $plan['price']
                );
            } catch (\RuntimeException $e) {
                $voucherInfo = ['error' => $e->getMessage()];
            }
        }

        http_response_code(200);
        echo \App\render('checkout', [
            'title' => 'Thanh toán — UrlShortM',
            'user'  => $user,
            'plans' => $plans,
            'plan'  => $plan,
            'voucherInfo' => $voucherInfo,
            'csrf'  => $this->csrf,
            'paypalConfigured' => $this->paypal()->isConfigured(),
        ]);
        exit;
    }

    public function pay(): never
    {
        $user = $this->guard();
        if (!$this->csrf->verify($_POST['csrf_token'] ?? null)) {
            \App\redirect(url_for('thanh-toan'), 302);
        }

        $planId = (int) ($_POST['plan_id'] ?? 0);
        $plan = $this->packages->findById($planId);
        if ($plan === null || (int) $plan['is_active'] !== 1) {
            \App\redirect(url_for('thanh-toan'), 302);
        }

        $amount = (float) $plan['price'];
        if ($amount <= 0) {
            \App\redirect(url_for('thanh-toan') . '?plan=' . $planId, 302);
        }

        // Voucher: tính giá sau giảm
        $voucherApplied = null;
        $voucherCode = trim((string) ($_POST['voucher'] ?? ''));
        if ($voucherCode !== '') {
            try {
                $voucherApplied = $this->voucher->redeem($voucherCode, (int) $user['id'], $amount);
                $amount = $voucherApplied['amount_after'];
            } catch (\RuntimeException $e) {
                \App\redirect(url_for('thanh-toan') . '?plan=' . $planId . '&voucher=' . rawurlencode($voucherCode) . '&error=' . rawurlencode($e->getMessage()), 302);
            }
        }

        $orderId = $this->orders->create(
            (int) $user['id'],
            (int) $plan['id'],
            (string) $plan['name'],
            (string) $plan['billing_period'],
            $amount,
            (string) ($plan['currency'] ?? 'VND')
        );
        $order = $this->orders->findById($orderId);

        // Lưu voucher để ghi nhận khi thanh toán thành công
        if ($voucherApplied !== null) {
            $_SESSION['voucher_by_order'][(int) $order['id']] = $voucherApplied;
        }

        $paypal = $this->paypal();
        if (!$paypal->isConfigured()) {
            // Chưa cấu hình cổng -> mock: thanh toán ngay để test luồng.
            $this->finalizePaid($order, null);
            \App\redirect(url_for('thanh-toan/thanh-cong') . '?order=' . rawurlencode($order['order_code']) . '&mock=1', 302);
        }

        try {
            $result = $paypal->createOrder(
                $amount,
                (string) $order['currency'],
                url_for('thanh-toan/thanh-cong') . '?order=' . rawurlencode($order['order_code']),
                url_for('thanh-toan/huy') . '?order=' . rawurlencode($order['order_code'])
            );
            $this->orders->setGateway((int) $order['id'], $result['id']);
            \App\redirect($result['approve_url'], 302);
        } catch (\RuntimeException $e) {
            \App\redirect(url_for('thanh-toan') . '?plan=' . $planId . '&error=' . rawurlencode($e->getMessage()), 302);
        }
    }

    public function success(): never
    {
        $user = $this->guard();

        $code = (string) ($_GET['order'] ?? '');
        $order = $code !== '' ? $this->orders->findByUserAndCode((int) $user['id'], $code) : null;
        if ($order === null) {
            \App\redirect(url_for('thanh-toan'), 302);
        }

        if ($order['status'] !== 'paid') {
            if (($order['gateway_order_id'] ?? '') === '' || ($_GET['mock'] ?? '') === '1') {
                $this->finalizePaid($order, null);
            } else {
                try {
                    $result = $this->paypal()->captureOrder((string) $order['gateway_order_id']);
                    if (strtoupper((string) $result['status']) === 'COMPLETED') {
                        $this->finalizePaid($order, $result['payer']);
                    } else {
                        $this->orders->markStatus((int) $order['id'], 'failed');
                        \App\redirect(url_for('thanh-toan') . '?error=' . rawurlencode('Thanh toán chưa hoàn tất, vui lòng thử lại.'), 302);
                    }
                } catch (\RuntimeException $e) {
                    \App\redirect(url_for('thanh-toan') . '?error=' . rawurlencode($e->getMessage()), 302);
                }
            }
        }

        $order = $this->orders->findByCode($code);

        http_response_code(200);
        echo \App\render('checkout-success', [
            'title' => 'Thanh toán thành công',
            'user'  => $user,
            'order' => $order,
            'plan'  => $this->packages->findById((int) $order['plan_id']),
        ]);
        exit;
    }

    public function cancel(): never
    {
        $user = $this->guard();

        $code = (string) ($_GET['order'] ?? '');
        $order = $code !== '' ? $this->orders->findByUserAndCode((int) $user['id'], $code) : null;
        if ($order !== null && $order['status'] === 'pending') {
            $this->orders->markStatus((int) $order['id'], 'canceled');
        }

        \App\redirect(url_for('thanh-toan'), 302);
    }

    public function invoice(string $code): never
    {
        $user = $this->guard();

        $order = $this->orders->findByUserAndCode((int) $user['id'], $code);
        if ($order === null) {
            \App\redirect(url_for('thanh-toan'), 302);
        }

        $site = \App\Container::getInstance()->siteSettingsService();
        http_response_code(200);
        echo \App\render('invoice', [
            'title' => 'Hoá đơn ' . $order['order_code'],
            'user'  => $user,
            'order' => $order,
            'plan'  => $this->packages->findById((int) $order['plan_id']),
            'seller' => [
                'name'    => (string) $site->get('invoice_name', '') !== '' ? (string) $site->get('invoice_name') : $site->siteName(),
                'tax_type' => (string) $site->get('invoice_tax_type', ''),
                'address' => (string) $site->get('invoice_address', ''),
                'phone'   => (string) $site->get('invoice_phone', ''),
                'tax_id'  => (string) $site->get('invoice_tax_id', ''),
            ],
        ]);
        exit;
    }

    private function finalizePaid(array $order, ?string $payer): void
    {
        $this->orders->markPaid((int) $order['id'], $payer);

        // Ghi nhận voucher đã dùng (nếu có)
        if (isset($_SESSION['voucher_by_order'][(int) $order['id']])) {
            $voucherApplied = $_SESSION['voucher_by_order'][(int) $order['id']];
            $this->voucher->consume(
                $voucherApplied['voucher'],
                (int) $order['user_id'],
                (int) $order['id'],
                (float) $voucherApplied['amount_before'],
                (float) $voucherApplied['amount_after']
            );
            unset($_SESSION['voucher_by_order'][(int) $order['id']]);
        }

        // Kích hoạt gói ngay cho user.
        $plan = $this->packages->findById((int) $order['plan_id']);
        if ($plan === null) {
            return;
        }
        $period = (string) ($plan['billing_period'] ?? 'monthly');
        $starts = date('Y-m-d 00:00:00');
        $ends = match ($period) {
            'yearly' => date('Y-m-d 00:00:00', strtotime('+1 year')),
            'lifetime' => null,
            default => date('Y-m-d 00:00:00', strtotime('+1 month')),
        };
        $this->users->setSubscription((int) $order['user_id'], (int) $plan['id'], $starts, $ends);

        $this->sendPaymentEmails($order);
    }

    /**
     * Gửi email "mua hàng thành công" + "hoá đơn" cho khách (chỉ khi cấu hình SMTP).
     *
     * @param array<string,mixed> $order
     */
    private function sendPaymentEmails(array $order): void
    {
        try {
            $c = \App\Container::getInstance();
            $mailer = $c->mailer();
            if (!$mailer->isConfigured()) {
                return;
            }
            $user = $this->users->findById((int) $order['user_id']);
            if ($user === null) {
                return;
            }

            $base = rtrim(\App\base_url(), '/');
            $data = [
                'name' => ($user['display_name'] ?? '') !== '' ? $user['display_name'] : $user['email'],
                'email' => $user['email'],
                'plan_name' => (string) $order['plan_name'],
                'amount' => number_format((float) $order['amount'], 0, ',', '.') . ' ' . $order['currency'],
                'order_code' => (string) $order['order_code'],
                'paid_at' => date('d/m/Y H:i', strtotime((string) $order['paid_at'])),
                'invoice_no' => str_pad((string) $order['id'], 7, '0', STR_PAD_LEFT),
                'dashboard_link' => $base . '/dashboard',
                'invoice_link' => $base . '/hoa-don/' . $order['order_code'],
            ];

            foreach (['purchase_success', 'invoice'] as $type) {
                $mail = $c->emailTemplates()->render($type, $data);
                $mailer->send($user['email'], $mail['subject'], $mail['html'], true);
            }
        } catch (\Throwable) {
            // Lỗi gửi email không được làm hỏng luồng thanh toán.
        }
    }

    private function paypal(): PayPalService
    {
        return new PayPalService(
            (string) ($this->settings->get('paypal_client_id') ?? ''),
            (string) ($this->settings->get('paypal_secret') ?? ''),
            (string) ($this->settings->get('paypal_mode') ?? 'sandbox')
        );
    }

    private function guard(): array
    {
        $user = \App\current_user();
        if ($user === null) {
            \App\redirect(url_for('dang-nhap'), 302);
        }

        return $user;
    }
}

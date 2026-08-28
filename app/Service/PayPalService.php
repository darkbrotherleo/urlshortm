<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Tích hợp PayPal Checkout (REST v2). Hỗ trợ sandbox + live.
 * Chưa cấu hình (thiếu client_id) -> isConfigured()=false, controller dùng luồng mock.
 */
final class PayPalService
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $secret,
        private readonly string $mode,
        private readonly mixed $http = null
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->secret !== '';
    }

    /**
     * @return array{id:string,approve_url:string} thông tin PayPal order
     *
     * @throws \RuntimeException lỗi gọi PayPal
     */
    public function createOrder(float $amount, string $currency, string $returnUrl, string $cancelUrl): array
    {
        $token = $this->token();
        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                ['amount' => ['currency_code' => $currency, 'value' => number_format($amount, 2, '.', '')]],
            ],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'brand_name' => 'UrlShortM',
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $this->request('POST', $this->base() . '/v2/checkout/orders', $body, $token);
        if (!isset($response['id'])) {
            throw new \RuntimeException('Không tạo được đơn hàng PayPal.');
        }

        $approve = '';
        foreach (($response['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve' && isset($link['href'])) {
                $approve = (string) $link['href'];
                break;
            }
        }
        if ($approve === '') {
            throw new \RuntimeException('Thiếu link xác nhận PayPal.');
        }

        return ['id' => (string) $response['id'], 'approve_url' => $approve];
    }

    /**
     * @return array{status:string,payer:?string}
     *
     * @throws \RuntimeException
     */
    public function captureOrder(string $gatewayOrderId): array
    {
        $token = $this->token();
        $response = $this->request(
            'POST',
            $this->base() . '/v2/checkout/orders/' . rawurlencode($gatewayOrderId) . '/capture',
            [],
            $token
        );

        $status = (string) ($response['status'] ?? '');
        $payer = null;
        foreach (($response['payer'] ?? []) as $key => $value) {
            if (is_string($value)) {
                $payer = $value;
                break;
            }
        }

        return ['status' => $status, 'payer' => $payer];
    }

    private function token(): string
    {
        $url = $this->base() . '/v1/oauth2/token';
        $body = http_build_query(['grant_type' => 'client_credentials']);

        if ($this->http !== null) {
            $raw = (string) call_user_func($this->http, $url, 'POST', $body, 'Basic ' . base64_encode($this->clientId . ':' . $this->secret));
            $data = json_decode($raw, true);

            return is_array($data) && isset($data['access_token']) ? (string) $data['access_token'] : '';
        }

        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Authorization: Basic " . base64_encode($this->clientId . ':' . $this->secret) . "\r\nContent-Type: application/x-www-form-urlencoded",
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new \RuntimeException('Không kết nối được PayPal.');
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['access_token'])) {
            throw new \RuntimeException('Lỗi xác thực PayPal (client_id/secret).');
        }

        return (string) $data['access_token'];
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    private function request(string $method, string $url, array $body, string $token): array
    {
        $json = $body !== [] ? json_encode($body) : '';

        if ($this->http !== null) {
            $raw = (string) call_user_func($this->http, $url, $method, $json, 'Bearer ' . $token);
            $data = json_decode($raw, true);

            return is_array($data) ? $data : [];
        }

        $ctx = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Authorization: Bearer " . $token . "\r\nContent-Type: application/json",
            'content' => $json,
            'timeout' => 20,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new \RuntimeException('Không gọi được PayPal.');
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function base(): string
    {
        return $this->mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }
}

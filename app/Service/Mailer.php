<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\SettingRepository;

/**
 * Gửi email qua SMTP (cấu hình trong Cài đặt website -> Email SMTP).
 * Hỗ trợ STARTTLS (587) và SSL (465). Không dùng thư viện ngoài.
 */
final class Mailer
{
    public function __construct(private readonly SettingRepository $settings)
    {
    }

    public function isConfigured(): bool
    {
        return (string) $this->settings->get('smtp_host', '') !== ''
            && (string) $this->settings->get('smtp_username', '') !== ''
            && (string) $this->settings->get('smtp_password', '') !== '';
    }

    /**
     * @throws \RuntimeException khi lỗi cấu hình hoặc SMTP từ chối
     */
    public function send(string $to, string $subject, string $body, bool $html = false): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Chưa cấu hình SMTP (máy chủ, tài khoản, mật khẩu).');
        }

        $host = (string) $this->settings->get('smtp_host', '');
        $port = (int) ($this->settings->get('smtp_port', '587') ?: 587);
        $user = (string) $this->settings->get('smtp_username', '');
        $pass = (string) $this->settings->get('smtp_password', '');
        $from = (string) $this->settings->get('smtp_from_email', '') !== '' ? (string) $this->settings->get('smtp_from_email') : $user;

        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('Email người nhận không hợp lệ.');
        }

        $secure = $port === 465 ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client(
            $secure . $host . ':' . $port,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT
        );
        if ($socket === false) {
            throw new \RuntimeException('Không kết nối được máy chủ SMTP (' . $host . ':' . $port . '). ' . (string) $errstr);
        }
        stream_set_timeout($socket, 20);

        try {
            $this->expect($socket, [220], $this->read($socket));

            $this->sendLine($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $ehlo = $this->readMultiline($socket);
            if (stripos($ehlo, 'STARTTLS') !== false && $port !== 465) {
                $this->sendLine($socket, 'STARTTLS');
                $this->expect($socket, [220], $this->read($socket));
                if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) === false) {
                    throw new \RuntimeException('Không bật được TLS với máy chủ SMTP.');
                }
                $this->sendLine($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                $this->readMultiline($socket);
            }

            $this->sendLine($socket, 'AUTH LOGIN');
            $this->expect($socket, [334], $this->read($socket));
            $this->sendLine($socket, base64_encode($user));
            $this->expect($socket, [334], $this->read($socket));
            $this->sendLine($socket, base64_encode($pass));
            $this->expect($socket, [235], $this->read($socket));

            $this->sendLine($socket, 'MAIL FROM: <' . $from . '>');
            $this->expect($socket, [250], $this->read($socket));
            $this->sendLine($socket, 'RCPT TO: <' . $to . '>');
            $this->expect($socket, [250, 251], $this->read($socket));

            $this->sendLine($socket, 'DATA');
            $this->expect($socket, [354], $this->read($socket));

            $message = 'Subject: ' . $this->encodeHeader($subject) . "\r\n"
                . 'To: ' . $to . "\r\n"
                . 'From: ' . $from . "\r\n"
                . "MIME-Version: 1.0\r\n"
                . ($html ? "Content-Type: text/html; charset=UTF-8\r\n" : "Content-Type: text/plain; charset=UTF-8\r\n")
                . "Content-Transfer-Encoding: 8bit\r\n"
                . "\r\n"
                . $body;

            $lines = preg_split('/\r\n|\n|\r/', $message) ?: [];
            foreach ($lines as $line) {
                // Chống SMTP injection: mọi dòng bắt đầu bằng "." phải thêm "." (dot-stuffing).
                if ($line !== '' && $line[0] === '.') {
                    $line = '.' . $line;
                }
                $this->sendLine($socket, $line);
            }
            $this->sendLine($socket, '.');
            $this->expect($socket, [250], $this->read($socket));

            $this->sendLine($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[\x80-\xFF]/', $value) === 1) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return $value;
    }

    private function sendLine($socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }

    private function read($socket): string
    {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 514 && $line[3] !== '-') {
                break;
            }
        }

        return $data;
    }

    private function readMultiline($socket): string
    {
        $data = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        return $data;
    }

    /**
     * @param array<int,int> $codes
     */
    private function expect($socket, array $codes, string $response): void
    {
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException('SMTP lỗi (mã ' . $code . '): ' . trim($response));
        }
    }
}

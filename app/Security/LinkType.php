<?php
declare(strict_types=1);

namespace App\Security;

use App\Service\LinkValidationException;

/**
 * Loại link và cách dựng đích đến (destination) tương ứng.
 */
final class LinkType
{
    public const TYPES = [
        'link', 'email', 'whatsapp', 'messenger', 'phone', 'sms',
        'telegram', 'skype', 'line', 'wechat', 'viber', 'vcard',
    ];

    public const LABELS = [
        'link'      => 'Link',
        'email'     => 'Email',
        'whatsapp'  => 'Whatsapp',
        'messenger' => 'Messenger',
        'phone'     => 'Phone',
        'sms'       => 'Sms',
        'telegram'  => 'Telegram',
        'skype'     => 'Skype',
        'line'      => 'Line',
        'wechat'    => 'Wechat',
        'viber'     => 'Viber',
        'vcard'     => 'vCard',
    ];

    public function __construct(private readonly UrlNormalizer $normalizer)
    {
    }

    public function isSupported(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    public function label(string $type): string
    {
        return self::LABELS[$type] ?? ucfirst($type);
    }

    /**
     * Dựng địa chỉ đích cho loại link. Ném LinkValidationException khi không hợp lệ.
     */
    public function build(string $type, string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new LinkValidationException('Vui lòng nhập địa chỉ.');
        }

        return match ($type) {
            'link'    => $this->normalUrl($raw),
            'vcard'   => $this->normalUrl($raw),
            'email'   => $this->email($raw),
            'phone'   => 'tel:+' . $this->digits($raw, 'số điện thoại'),
            'sms'     => 'sms:+' . $this->digits($raw, 'số điện thoại'),
            'whatsapp'=> 'https://wa.me/' . $this->digits($raw, 'số điện thoại'),
            'viber'   => 'viber://chat?number=%2B' . $this->digits($raw, 'số điện thoại'),
            'messenger' => 'https://m.me/' . $this->handle($raw, 'tên Messenger'),
            'telegram'  => 'https://t.me/' . $this->handle($raw, 'username Telegram'),
            'skype'     => 'skype:' . $this->handle($raw, 'tên Skype') . '?chat',
            'line'      => 'https://line.me/ti/p/' . $this->handle($raw, 'ID Line'),
            'wechat'    => 'weixin://dl/chat/' . $this->handle($raw, 'ID WeChat'),
            default     => throw new LinkValidationException('Loại link không được hỗ trợ.'),
        };
    }

    private function normalUrl(string $raw): string
    {
        $url = $this->normalizer->normalize($raw);
        if ($url === null) {
            throw new LinkValidationException('Địa chỉ không hợp lệ. Vui lòng dùng http:// hoặc https://.');
        }

        return $url;
    }

    private function digits(string $raw, string $label): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null || $digits === '') {
            throw new LinkValidationException('Vui lòng nhập đúng ' . $label . '.');
        }

        return $digits;
    }

    private function handle(string $raw, string $label): string
    {
        $clean = strtolower(preg_replace('/[^0-9a-zA-Z_.\-]/', '', $raw));
        $clean = ltrim($clean, '@');
        if ($clean === '') {
            throw new LinkValidationException('Vui lòng nhập đúng ' . $label . '.');
        }

        return $clean;
    }

    private function email(string $raw): string
    {
        $email = strtolower(trim($raw));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new LinkValidationException('Email không hợp lệ.');
        }

        return 'mailto:' . $email;
    }
}

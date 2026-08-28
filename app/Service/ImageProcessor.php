<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Xử lý ảnh khi upload theo cài đặt Media (nén + chuyển đổi WebP/AVIF).
 */
final class ImageProcessor
{
    /**
     * Kiểm tra định dạng có được phép không.
     *
     * @param array<int,string> $allowedFormats
     */
    public function isAllowed(string $mime, array $allowedFormats): bool
    {
        $ext = $this->extForMime($mime);

        return $ext !== null && in_array($ext, $allowedFormats, true);
    }

    /**
     * Nén + chuyển đổi file ảnh. Trả về [path, mime] của file mới, hoặc null nếu không làm gì.
     *
     * @param string $srcPath  đường dẫn file gốc
     * @param string $srcMime  MIME ảnh gốc
     */
    public function process(string $srcPath, string $srcMime, bool $compress, string $convert): ?array
    {
        if ($convert === 'webp' && function_exists('imagewebp')) {
            return $this->toWebP($srcPath, $srcMime);
        }
        if ($convert === 'avif' && function_exists('imageavif')) {
            return $this->toAvif($srcPath, $srcMime);
        }
        if ($compress) {
            return $this->compressInPlace($srcPath, $srcMime);
        }

        return null;
    }

    public function extForMime(string $mime): ?string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => null,
        };
    }

    private function load(string $path, string $mime): ?\GdImage
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/avif' => @imagecreatefromavif($path),
            default => null,
        };
    }

    private function toWebP(string $src, string $mime): ?array
    {
        $img = $this->load($src, $mime);
        if ($img === null) {
            return null;
        }
        $out = $src . '.webp';
        imagepalettetotruecolor($img);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        if (imagewebp($img, $out, 82) === false) {
            @unlink($out);
            return null;
        }
        imagedestroy($img);
        @unlink($src);

        return [$out, 'image/webp'];
    }

    private function toAvif(string $src, string $mime): ?array
    {
        $img = $this->load($src, $mime);
        if ($img === null) {
            return null;
        }
        $out = $src . '.avif';
        imagepalettetotruecolor($img);
        if (imageavif($img, $out, 50) === false) {
            @unlink($out);
            return null;
        }
        imagedestroy($img);
        @unlink($src);

        return [$out, 'image/avif'];
    }

    private function compressInPlace(string $src, string $mime): ?array
    {
        $img = $this->load($src, $mime);
        if ($img === null) {
            return null;
        }
        $tmp = $src . '.tmp';
        $ok = match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => imagejpeg($img, $tmp, 80),
            'image/png' => (static function () use ($img, $tmp): bool {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                return imagepng($img, $tmp, 6);
            })(),
            'image/webp' => imagewebp($img, $tmp, 80),
            default => false,
        };
        imagedestroy($img);
        if ($ok === false) {
            @unlink($tmp);
            return null;
        }
        @unlink($src);
        rename($tmp, $src);

        return [$src, $mime];
    }
}

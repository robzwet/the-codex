<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * Validated image uploads. Files are re-named randomly and written to
 * public/uploads (a persistent Docker volume). Only real image types are
 * accepted, and the uploads dir disallows script execution (.htaccess).
 */
final class Upload
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Handle one file from an array-style input (name="image_file[<key>]").
     * Returns the web path ("/uploads/xyz.png") on success, or null if no file
     * was provided. Throws on an invalid file.
     */
    public static function image(string $inputName, string $key, int $campaignId): ?string
    {
        if (empty($_FILES[$inputName]) || !isset($_FILES[$inputName]['error'][$key])) {
            return null;
        }
        $error = $_FILES[$inputName]['error'][$key];
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed (code ' . $error . ').');
        }

        $tmp = $_FILES[$inputName]['tmp_name'][$key];
        $size = (int) $_FILES[$inputName]['size'][$key];
        if ($size > self::MAX_BYTES) {
            throw new \RuntimeException('Image is too large (max 5 MB).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Only JP, PNG, GIF or WebP images are allowed.');
        }

        $ext = self::ALLOWED[$mime];
        $name = 'c' . $campaignId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir = dirname(__DIR__, 2) . '/public/uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            throw new \RuntimeException('Could not store the uploaded image.');
        }

        return '/uploads/' . $name;
    }
}

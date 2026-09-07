<?php

declare(strict_types=1);

namespace Sofiago\Core;

/**
 * Validates and stores an uploaded image under public/uploads/... (SOFIAGO_PUBLIC_PATH,
 * defined once in public/index.php — see the comment there for why it's a constant rather
 * than a guessed relative path). Re-encodes through GD when available (strips EXIF/GPS data
 * as a side effect, caps dimensions) and falls back to storing the original bytes unmodified
 * when GD isn't installed, which is only expected to happen in local dev.
 */
final class Upload
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_DIMENSION = 1600;
    private const JPEG_QUALITY = 85;

    /** Map icons replace a small pin graphic, not full photos — 256×256 is plenty. */
    private const ICON_MAX_DIMENSION = 256;

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     *   One entry from $_FILES — pass $_FILES['photos']['name'][$i] etc. reassembled per-file,
     *   see ListingManageController for how the multi-file $_FILES shape gets split up.
     * @return string|null Root-relative URL path to store in listing_media.path, or null if
     *   this slot was empty/invalid — check $error for which.
     */
    public static function storeImage(array $file, string $subdir, ?string &$error = null): ?string
    {
        [$tmpName, $mime, $dir] = self::validate($file, $subdir, $error);

        if ($tmpName === null) {
            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.jpg';
        $destination = $dir . '/' . $filename;

        if (extension_loaded('gd')) {
            if (!self::reencodeAsJpeg($tmpName, $mime, $destination)) {
                $error = t('upload.process_error');

                return null;
            }
        } else {
            // Local dev without GD: keep the original bytes/extension rather than failing outright.
            $filename = bin2hex(random_bytes(16)) . self::extensionFor($mime);
            $destination = $dir . '/' . $filename;

            if (!copy($tmpName, $destination)) {
                $error = t('upload.save_error');

                return null;
            }
        }

        return '/uploads/' . trim($subdir, '/') . '/' . $filename;
    }

    /**
     * Same input/validation as storeImage(), plus a square-aspect-ratio requirement, and
     * re-encodes as PNG with the alpha channel preserved instead of flattening to
     * JPEG-on-white — used for a listing's custom map marker icon (Listing::map_icon_path),
     * which needs a transparent background to look right sitting on top of map tiles rather
     * than showing up as an opaque white square. Capped at ICON_MAX_DIMENSION (256×256).
     *
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     * @return string|null Root-relative URL path to store in listings.map_icon_path, or null.
     */
    public static function storeIcon(array $file, string $subdir, ?string &$error = null): ?string
    {
        [$tmpName, $mime, $dir] = self::validate($file, $subdir, $error);

        if ($tmpName === null) {
            return null;
        }

        // Square only — this is a marker graphic, not a photo, and a non-square source would
        // get squashed/cropped unpredictably once the map widget actually renders it. Checked
        // via getimagesize() rather than inside reencodeAsPng() so the rule holds even in the
        // no-GD dev fallback below, which otherwise just copies the bytes through unchanged.
        $dimensions = @getimagesize($tmpName);
        if ($dimensions === false || $dimensions[0] !== $dimensions[1]) {
            $error = t('upload.icon_not_square');

            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.png';
        $destination = $dir . '/' . $filename;

        if (extension_loaded('gd')) {
            if (!self::reencodeAsPng($tmpName, $mime, $destination)) {
                $error = t('upload.process_error');

                return null;
            }
        } else {
            // Local dev without GD: keep the original bytes/extension rather than failing outright.
            $filename = bin2hex(random_bytes(16)) . self::extensionFor($mime);
            $destination = $dir . '/' . $filename;

            if (!copy($tmpName, $destination)) {
                $error = t('upload.save_error');

                return null;
            }
        }

        return '/uploads/' . trim($subdir, '/') . '/' . $filename;
    }

    /**
     * Shared front half of storeImage()/storeIcon(): checks $_FILES error/size/mime, ensures
     * the destination directory exists, and returns the bits both callers need to finish the
     * job themselves (they differ only in output format/dimensions from here on).
     *
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     * @return array{0: ?string, 1: string, 2: string} [tmpName, mime, dir] — tmpName is null
     *   (with $error set, or left null for an empty slot) when validation failed.
     */
    private static function validate(array $file, string $subdir, ?string &$error): array
    {
        $error = null;

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, '', ''];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $error = t('upload.generic_error');

            return [null, '', ''];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $error = t('upload.invalid_upload');

            return [null, '', ''];
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            $error = t('upload.too_large');

            return [null, '', ''];
        }

        $mime = (string) (mime_content_type($tmpName) ?: '');

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            $error = t('upload.invalid_type');

            return [null, '', ''];
        }

        $dir = SOFIAGO_PUBLIC_PATH . '/uploads/' . trim($subdir, '/');

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $error = t('upload.folder_error');

            return [null, '', ''];
        }

        return [$tmpName, $mime, $dir];
    }

    public static function deleteByPublicPath(string $publicPath): void
    {
        $full = SOFIAGO_PUBLIC_PATH . $publicPath;

        // Guard against a stray '..' turning this into an arbitrary-file delete — publicPath
        // always comes from our own DB rows, never directly from request input, but cheap insurance.
        if (str_contains($publicPath, '..')) {
            return;
        }

        if (is_file($full)) {
            @unlink($full);
        }
    }

    private static function reencodeAsJpeg(string $tmpName, string $mime, string $destination): bool
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpName),
            'image/png' => @imagecreatefrompng($tmpName),
            'image/webp' => @imagecreatefromwebp($tmpName),
            default => false,
        };

        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest > self::MAX_DIMENSION) {
            $scale = self::MAX_DIMENSION / $longest;
            $newWidth = (int) round($width * $scale);
            $newHeight = (int) round($height * $scale);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            // Flatten transparency onto white — we always save as JPEG, which has no alpha channel.
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefill($resized, 0, 0, $white);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $ok = imagejpeg($image, $destination, self::JPEG_QUALITY);
        imagedestroy($image);

        return $ok;
    }

    /** Like reencodeAsJpeg() but keeps transparency and caps at ICON_MAX_DIMENSION, not MAX_DIMENSION. */
    private static function reencodeAsPng(string $tmpName, string $mime, string $destination): bool
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpName),
            'image/png' => @imagecreatefrompng($tmpName),
            'image/webp' => @imagecreatefromwebp($tmpName),
            default => false,
        };

        if (!$image) {
            return false;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest > self::ICON_MAX_DIMENSION) {
            $scale = self::ICON_MAX_DIMENSION / $longest;
            $newWidth = (int) round($width * $scale);
            $newHeight = (int) round($height * $scale);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            // Fill with a fully transparent background instead of reencodeAsJpeg()'s opaque
            // white — this is the whole point of the PNG path (a JPEG source without its own
            // transparency just resizes onto nothing-to-preserve, which is fine).
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $ok = imagepng($image, $destination, 6);
        imagedestroy($image);

        return $ok;
    }

    private static function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/png' => '.png',
            'image/webp' => '.webp',
            default => '.jpg',
        };
    }
}

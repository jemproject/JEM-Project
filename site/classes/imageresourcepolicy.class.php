<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Performs bounded, header-only checks before JEM decodes a raster image.
 */
final class JemImageResourcePolicy
{
    public const ACCEPTED = 'accepted';
    public const NOT_IMAGE = 'not_image';
    public const FORMAT_MISMATCH = 'format_mismatch';
    public const DIMENSIONS_EXCEEDED = 'dimensions_exceeded';
    public const FRAME_LIMIT_EXCEEDED = 'frame_limit_exceeded';
    public const EXPANSION_LIMIT_EXCEEDED = 'expansion_limit_exceeded';
    public const MEMORY_LIMIT_EXCEEDED = 'memory_limit_exceeded';

    public const DEFAULT_MAX_DIMENSION = 3840;
    public const MIN_CONFIGURED_DIMENSION = 320;
    public const MAX_CONFIGURED_DIMENSION = 8192;

    private const MAX_PIXELS = 40000000;
    private const MAX_FRAMES = 100;
    private const MAX_ANIMATION_PIXELS = 80000000;
    private const MAX_EXPANSION_RATIO = 10000;
    private const DECODE_BYTES_PER_PIXEL = 8;
    private const FIXED_MEMORY_OVERHEAD = 2097152;
    private const MEMORY_RESERVE = 8388608;

    /**
     * @var array<string, int>
     */
    private const EXTENSION_TYPES = array(
        'bmp'  => IMAGETYPE_BMP,
        'gif'  => IMAGETYPE_GIF,
        'jpe'  => IMAGETYPE_JPEG,
        'jpeg' => IMAGETYPE_JPEG,
        'jfif' => IMAGETYPE_JPEG,
        'jpg'  => IMAGETYPE_JPEG,
        'png'  => IMAGETYPE_PNG,
        'webp' => IMAGETYPE_WEBP,
    );

    /**
     * Inspect an image without decoding its pixel data.
     *
     * @return array{accepted: bool, reason: string, width: int, height: int, type: int, frames: int, estimated_memory: int}
     */
    public static function inspect(
        string $path,
        string $extension = '',
        int $configuredMaxDimension = self::DEFAULT_MAX_DIMENSION,
        int $targetWidth = 0,
        int $targetHeight = 0,
        ?int $memoryBudget = null
    ): array {
        if (!is_file($path) || !is_readable($path)) {
            return self::result(false, self::NOT_IMAGE);
        }

        $fileSize = @filesize($path);
        $info = @getimagesize($path);

        if ($fileSize === false || $fileSize < 1 || !is_array($info) || count($info) < 3) {
            return self::result(false, self::NOT_IMAGE);
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $type = (int) ($info[2] ?? 0);
        $extension = strtolower(ltrim(trim($extension !== '' ? $extension : pathinfo($path, PATHINFO_EXTENSION)), '.'));

        if ($width < 1 || $height < 1 || !isset(self::EXTENSION_TYPES[$extension])
            || self::EXTENSION_TYPES[$extension] !== $type) {
            return self::result(false, self::FORMAT_MISMATCH, $width, $height, $type);
        }

        $configuredMaxDimension = self::normaliseConfiguredMaxDimension($configuredMaxDimension);
        $pixels = $width * $height;

        if ($width > $configuredMaxDimension || $height > $configuredMaxDimension || $pixels > self::MAX_PIXELS) {
            return self::result(false, self::DIMENSIONS_EXCEEDED, $width, $height, $type);
        }

        $frames = self::countFrames($path, $type);

        if ($frames < 1) {
            return self::result(false, self::NOT_IMAGE, $width, $height, $type);
        }

        $animationPixels = $pixels * $frames;

        if ($frames > self::MAX_FRAMES || $animationPixels > self::MAX_ANIMATION_PIXELS) {
            return self::result(false, self::FRAME_LIMIT_EXCEEDED, $width, $height, $type, $frames);
        }

        $expandedBytes = $animationPixels * 4;

        if (($expandedBytes / max(1, $fileSize)) > self::MAX_EXPANSION_RATIO) {
            return self::result(false, self::EXPANSION_LIMIT_EXCEEDED, $width, $height, $type, $frames);
        }

        $targetPixels = self::targetPixels($width, $height, $targetWidth, $targetHeight);
        $estimatedMemory = (($pixels + $targetPixels) * self::DECODE_BYTES_PER_PIXEL)
            + min($fileSize * 2, 16777216)
            + self::FIXED_MEMORY_OVERHEAD;
        $memoryBudget = $memoryBudget === null ? self::availableMemoryBudget() : max(0, $memoryBudget);

        if ($estimatedMemory > $memoryBudget) {
            return self::result(
                false,
                self::MEMORY_LIMIT_EXCEEDED,
                $width,
                $height,
                $type,
                $frames,
                $estimatedMemory
            );
        }

        return self::result(true, self::ACCEPTED, $width, $height, $type, $frames, $estimatedMemory);
    }

    public static function normaliseConfiguredMaxDimension(int $value): int
    {
        if ($value < self::MIN_CONFIGURED_DIMENSION) {
            return self::DEFAULT_MAX_DIMENSION;
        }

        return min($value, self::MAX_CONFIGURED_DIMENSION);
    }

    /**
     * @return array{accepted: bool, reason: string, width: int, height: int, type: int, frames: int, estimated_memory: int}
     */
    private static function result(
        bool $accepted,
        string $reason,
        int $width = 0,
        int $height = 0,
        int $type = 0,
        int $frames = 0,
        int $estimatedMemory = 0
    ): array {
        return array(
            'accepted' => $accepted,
            'reason' => $reason,
            'width' => $width,
            'height' => $height,
            'type' => $type,
            'frames' => $frames,
            'estimated_memory' => $estimatedMemory,
        );
    }

    private static function targetPixels(int $width, int $height, int $targetWidth, int $targetHeight): int
    {
        if ($targetWidth < 1 && $targetHeight < 1) {
            return 0;
        }

        $scaleWidth = $targetWidth > 0 ? $targetWidth / $width : 1.0;
        $scaleHeight = $targetHeight > 0 ? $targetHeight / $height : 1.0;
        $scale = min(1.0, $scaleWidth, $scaleHeight);

        return max(1, (int) floor($width * $scale)) * max(1, (int) floor($height * $scale));
    }

    private static function availableMemoryBudget(): int
    {
        $limit = self::parseIniBytes((string) ini_get('memory_limit'));

        if ($limit < 1) {
            return PHP_INT_MAX;
        }

        return max(0, $limit - memory_get_usage(true) - self::MEMORY_RESERVE);
    }

    private static function parseIniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        $multipliers = array('k' => 1024, 'm' => 1048576, 'g' => 1073741824, 't' => 1099511627776);

        if (isset($multipliers[$unit])) {
            $number *= $multipliers[$unit];
        }

        return $number > PHP_INT_MAX ? PHP_INT_MAX : max(0, (int) floor($number));
    }

    private static function countFrames(string $path, int $type): int
    {
        switch ($type) {
            case IMAGETYPE_GIF:
                return self::countGifFrames($path);

            case IMAGETYPE_PNG:
                return self::countPngFrames($path);

            case IMAGETYPE_WEBP:
                return self::countWebpFrames($path);

            case IMAGETYPE_BMP:
            case IMAGETYPE_JPEG:
                return 1;
        }

        return 0;
    }

    private static function countGifFrames(string $path): int
    {
        $handle = @fopen($path, 'rb');
        $fileSize = @filesize($path);

        if (!is_resource($handle) || $fileSize === false) {
            return 0;
        }

        try {
            $header = self::readExact($handle, 13);

            if ($header === false || !in_array(substr($header, 0, 6), array('GIF87a', 'GIF89a'), true)) {
                return 0;
            }

            $packed = ord($header[10]);

            if (($packed & 0x80) !== 0) {
                $globalTableBytes = 3 * (2 << ($packed & 0x07));

                if (!self::skipBytes($handle, $globalTableBytes, $fileSize)) {
                    return 0;
                }
            }

            $frames = 0;

            while (($marker = self::readExact($handle, 1)) !== false) {
                if ($marker === "\x3B") {
                    return $frames;
                }

                if ($marker === "\x21") {
                    if (self::readExact($handle, 1) === false || !self::skipGifSubBlocks($handle, $fileSize)) {
                        return 0;
                    }

                    continue;
                }

                if ($marker !== "\x2C") {
                    return 0;
                }

                $descriptor = self::readExact($handle, 9);

                if ($descriptor === false) {
                    return 0;
                }

                $frames++;

                if ($frames > self::MAX_FRAMES) {
                    return $frames;
                }

                $localPacked = ord($descriptor[8]);

                if (($localPacked & 0x80) !== 0
                    && !self::skipBytes($handle, 3 * (2 << ($localPacked & 0x07)), $fileSize)) {
                    return 0;
                }

                if (self::readExact($handle, 1) === false || !self::skipGifSubBlocks($handle, $fileSize)) {
                    return 0;
                }
            }
        } finally {
            fclose($handle);
        }

        return 0;
    }

    private static function skipGifSubBlocks($handle, int $fileSize): bool
    {
        while (($lengthByte = self::readExact($handle, 1)) !== false) {
            $length = ord($lengthByte);

            if ($length === 0) {
                return true;
            }

            if (!self::skipBytes($handle, $length, $fileSize)) {
                return false;
            }
        }

        return false;
    }

    private static function countPngFrames(string $path): int
    {
        $handle = @fopen($path, 'rb');
        $fileSize = @filesize($path);

        if (!is_resource($handle) || $fileSize === false) {
            return 0;
        }

        try {
            if (self::readExact($handle, 8) !== "\x89PNG\r\n\x1A\n") {
                return 0;
            }

            $frames = 1;

            while (true) {
                $lengthBytes = self::readExact($handle, 4);
                $chunkType = self::readExact($handle, 4);

                if ($lengthBytes === false || $chunkType === false) {
                    return 0;
                }

                $length = (int) unpack('Nvalue', $lengthBytes)['value'];

                if ($chunkType === 'acTL') {
                    if ($length !== 8 || ($animation = self::readExact($handle, 8)) === false) {
                        return 0;
                    }

                    $frames = (int) unpack('Nvalue', substr($animation, 0, 4))['value'];
                } elseif (!self::skipBytes($handle, $length, $fileSize)) {
                    return 0;
                }

                if (!self::skipBytes($handle, 4, $fileSize)) {
                    return 0;
                }

                if ($chunkType === 'IEND') {
                    return max(1, $frames);
                }

                if ($frames > self::MAX_FRAMES) {
                    return $frames;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private static function countWebpFrames(string $path): int
    {
        $handle = @fopen($path, 'rb');
        $fileSize = @filesize($path);

        if (!is_resource($handle) || $fileSize === false) {
            return 0;
        }

        try {
            $header = self::readExact($handle, 12);

            if ($header === false || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP') {
                return 0;
            }

            $frames = 0;
            $animated = false;

            while (ftell($handle) < $fileSize) {
                $chunkType = self::readExact($handle, 4);
                $lengthBytes = self::readExact($handle, 4);

                if ($chunkType === false || $lengthBytes === false) {
                    return 0;
                }

                $length = (int) unpack('Vvalue', $lengthBytes)['value'];

                if ($chunkType === 'VP8X') {
                    $flags = self::readExact($handle, 1);

                    if ($flags === false) {
                        return 0;
                    }

                    $animated = (ord($flags) & 0x02) !== 0;

                    if (!self::skipBytes($handle, $length - 1, $fileSize)) {
                        return 0;
                    }
                } else {
                    if ($chunkType === 'ANMF') {
                        $frames++;
                    }

                    if (!self::skipBytes($handle, $length, $fileSize)) {
                        return 0;
                    }
                }

                if (($length & 1) === 1 && !self::skipBytes($handle, 1, $fileSize)) {
                    return 0;
                }

                if ($frames > self::MAX_FRAMES) {
                    return $frames;
                }
            }

            return $animated ? $frames : 1;
        } finally {
            fclose($handle);
        }
    }

    private static function readExact($handle, int $length)
    {
        if ($length < 0) {
            return false;
        }

        if ($length === 0) {
            return '';
        }

        $data = fread($handle, $length);

        return is_string($data) && strlen($data) === $length ? $data : false;
    }

    private static function skipBytes($handle, int $length, int $fileSize): bool
    {
        $position = ftell($handle);

        if ($length < 0 || $position === false || $position + $length > $fileSize) {
            return false;
        }

        return fseek($handle, $length, SEEK_CUR) === 0;
    }
}

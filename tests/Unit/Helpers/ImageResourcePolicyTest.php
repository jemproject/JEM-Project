<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/imageresourcepolicy.class.php';

final class ImageResourcePolicyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/jem-image-resource-policy-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->root, 0777, true));
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->root . '/*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testAcceptsAValidImageWithinTheFixedBoundary(): void
    {
        $path = $this->write('allowed.png', $this->validPng());
        $result = JemImageResourcePolicy::inspect($path, 'png');

        self::assertTrue($result['accepted']);
        self::assertSame(JemImageResourcePolicy::ACCEPTED, $result['reason']);
        self::assertSame(1, $result['width']);
        self::assertSame(1, $result['height']);
        self::assertSame(1, $result['frames']);
    }

    public function testRejectsARealFormatThatDoesNotMatchTheExtension(): void
    {
        $path = $this->write('mismatch.jpg', $this->validPng());
        $result = JemImageResourcePolicy::inspect($path, 'jpg');

        self::assertFalse($result['accepted']);
        self::assertSame(JemImageResourcePolicy::FORMAT_MISMATCH, $result['reason']);
    }

    public function testRejectsEitherDimensionAboveTheFixedBoundary(): void
    {
        $atLimit = $this->write('at-limit.png', $this->pngImage(3840, 1));
        $wide = $this->write('wide.png', $this->pngHeader(3841, 1));
        $tall = $this->write('tall.png', $this->pngHeader(1, 3841));

        self::assertTrue(JemImageResourcePolicy::inspect($atLimit, 'png')['accepted']);
        self::assertSame(
            JemImageResourcePolicy::DIMENSIONS_EXCEEDED,
            JemImageResourcePolicy::inspect($wide, 'png')['reason']
        );
        self::assertSame(
            JemImageResourcePolicy::DIMENSIONS_EXCEEDED,
            JemImageResourcePolicy::inspect($tall, 'png')['reason']
        );
    }

    public function testRejectsMalformedContainersAndExcessiveAnimationFrames(): void
    {
        $malformed = $this->write('malformed.png', substr($this->pngHeader(10, 10), 0, -12));
        $animated = $this->write('animated.gif', $this->gifWithFrames(101));

        self::assertSame(
            JemImageResourcePolicy::NOT_IMAGE,
            JemImageResourcePolicy::inspect($malformed, 'png')['reason']
        );
        self::assertSame(
            JemImageResourcePolicy::FRAME_LIMIT_EXCEEDED,
            JemImageResourcePolicy::inspect($animated, 'gif')['reason']
        );
    }

    public function testRejectsAnImageWhenTheEstimatedDecodeMemoryIsUnavailable(): void
    {
        $path = $this->write('memory.png', $this->validPng());
        $result = JemImageResourcePolicy::inspect($path, 'png', 3840, 200, 200, 1);

        self::assertFalse($result['accepted']);
        self::assertSame(JemImageResourcePolicy::MEMORY_LIMIT_EXCEEDED, $result['reason']);
        self::assertGreaterThan(1, $result['estimated_memory']);
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->root . '/' . $name;
        self::assertNotFalse(file_put_contents($path, $contents));

        return $path;
    }

    private function validPng(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
    }

    private function pngHeader(int $width, int $height): string
    {
        $header = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1A\n" . $this->pngChunk('IHDR', $header) . $this->pngChunk('IEND', '');
    }

    private function pngImage(int $width, int $height): string
    {
        $rows = str_repeat("\x00" . str_repeat("\x00", $width * 4), $height);
        $header = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1A\n"
            . $this->pngChunk('IHDR', $header)
            . $this->pngChunk('IDAT', gzcompress($rows, 9))
            . $this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('H*', hash('crc32b', $type . $data));
    }

    private function gifWithFrames(int $frames): string
    {
        $gif = 'GIF89a' . pack('vvCCC', 1, 1, 0x80, 0, 0) . "\x00\x00\x00\xFF\xFF\xFF";
        $descriptor = "\x2C" . pack('vvvvC', 0, 0, 1, 1, 0) . "\x02\x01\x00\x00";

        return $gif . str_repeat($descriptor, $frames) . "\x3B";
    }
}

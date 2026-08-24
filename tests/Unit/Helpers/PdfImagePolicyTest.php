<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/pdfimagepolicy.class.php';

final class PdfImagePolicyTest extends TestCase
{
    private string $root;
    private string $siteRoot;
    private string $png;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/jem-pdf-image-policy-' . bin2hex(random_bytes(6));
        $this->siteRoot = $this->root . '/site';
        $this->png = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        self::assertNotSame('', $this->png);
        self::assertTrue(mkdir($this->siteRoot . '/images/jem/events', 0777, true));
        self::assertTrue(mkdir($this->siteRoot . '/media/com_jem/images/flags', 0777, true));
        self::assertTrue(mkdir($this->siteRoot . '/media/other_extension', 0777, true));
        self::assertTrue(mkdir($this->root . '/outside', 0777, true));
        file_put_contents($this->siteRoot . '/images/jem/events/allowed.png', $this->png);
        file_put_contents($this->siteRoot . '/media/com_jem/images/flags/allowed.png', $this->png);
        file_put_contents($this->siteRoot . '/media/other_extension/denied.png', $this->png);
        file_put_contents($this->siteRoot . '/private.png', $this->png);
        file_put_contents($this->root . '/outside/private.png', $this->png);
        file_put_contents($this->siteRoot . '/images/jem/events/not-image.txt', 'not an image');
        file_put_contents($this->siteRoot . '/images/jem/events/oversized.png', $this->pngHeader(3841, 1));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isLink() || $entry->isFile()) {
                unlink($entry->getPathname());
            } else {
                rmdir($entry->getPathname());
            }
        }

        rmdir($this->root);
    }

    public function testResolvesSupportedImagesInsideExplicitMediaRoots(): void
    {
        self::assertSame(
            realpath($this->siteRoot . '/images/jem/events/allowed.png'),
            JemPdfImagePolicy::resolveLocalImage('images/jem/events/allowed.png', 'event', $this->siteRoot)
        );
        self::assertSame(
            realpath($this->siteRoot . '/images/jem/events/allowed.png'),
            JemPdfImagePolicy::resolveLocalImage('allowed.png', 'event', $this->siteRoot)
        );
        self::assertSame(
            realpath($this->siteRoot . '/media/com_jem/images/flags/allowed.png'),
            JemPdfImagePolicy::resolveLocalImage('/media/com_jem/images/flags/allowed.png', 'event', $this->siteRoot)
        );
    }

    public function testAbsoluteHttpSourceContributesOnlyAContainedLocalPath(): void
    {
        self::assertSame(
            realpath($this->siteRoot . '/images/jem/events/allowed.png'),
            JemPdfImagePolicy::resolveLocalImage(
                'https://example.invalid/subdir/images/jem/events/allowed.png?cache=1',
                'event',
                $this->siteRoot,
                '/subdir'
            )
        );
    }

    #[DataProvider('unsafeSourceProvider')]
    public function testRejectsUnsafePathForms(string $source): void
    {
        self::assertSame('', JemPdfImagePolicy::resolveLocalImage($source, 'event', $this->siteRoot));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeSourceProvider(): array
    {
        return array(
            'parent segment' => array('images/../private.png'),
            'encoded parent segment' => array('images/%2e%2e/private.png'),
            'outside site root' => array('../outside/private.png'),
            'absolute filesystem path' => array('C:/private.png'),
            'file scheme' => array('file:///private.png'),
            'data scheme' => array('data:image/png;base64,AAAA'),
            'protocol relative URL' => array('//example.invalid/images/allowed.png'),
            'URL credentials' => array('https://user:pass@example.invalid/images/allowed.png'),
            'encoded null byte' => array('images/jem/events/allowed.png%00.jpg'),
            'dot segment' => array('images/jem/events/./allowed.png'),
        );
    }

    public function testRejectsImagesOutsideTheAllowedRootsAndNonImagesInsideThem(): void
    {
        self::assertSame('', JemPdfImagePolicy::resolveLocalImage('private.png', '', $this->siteRoot));
        self::assertSame('', JemPdfImagePolicy::resolveLocalImage('media/other_extension/denied.png', 'event', $this->siteRoot));
        self::assertSame('', JemPdfImagePolicy::resolveLocalImage('images/jem/events/not-image.txt', 'event', $this->siteRoot));
        self::assertSame('', JemPdfImagePolicy::resolveLocalImage('images/jem/events/oversized.png', 'event', $this->siteRoot));
    }

    private function pngHeader(int $width, int $height): string
    {
        $header = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1A\n" . $this->pngChunk('IHDR', $header) . $this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('H*', hash('crc32b', $type . $data));
    }

    public function testRejectsSymlinksThatEscapeAnAllowedRoot(): void
    {
        $link = $this->siteRoot . '/images/jem/events/escaped.png';

        if (!function_exists('symlink') || !@symlink($this->root . '/outside/private.png', $link)) {
            self::markTestSkipped('Symbolic links are not available in this test environment.');
        }

        self::assertSame('', JemPdfImagePolicy::resolveLocalImage('images/jem/events/escaped.png', 'event', $this->siteRoot));
    }
}

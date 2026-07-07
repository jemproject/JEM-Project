<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/helpers/attachmentdisplay.php';

final class AttachmentDisplayHelperTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, mixed, string}>
     */
    public static function layoutProvider(): iterable
    {
        yield 'override wins' => array('row_full', 'column', 'row_full');
        yield 'global used when override empty' => array('', 'column_uniform', 'column_uniform');
        yield 'invalid override falls back to valid global' => array('bad', 'row_uniform', 'row_uniform');
        yield 'invalid values fall back to default column' => array('bad', 'also_bad', 'column');
    }

    #[DataProvider('layoutProvider')]
    public function testResolveLayout($override, $global, string $expected): void
    {
        self::assertSame($expected, JemAttachmentDisplayHelper::resolveLayout($override, $global));
    }

    /**
     * @return iterable<string, array{mixed, mixed, mixed, string}>
     */
    public static function iconSizeProvider(): iterable
    {
        yield 'override wins' => array('large', 'normal', 0, 'large');
        yield 'global used when override empty' => array('', 'medium', 1, 'medium');
        yield 'legacy hidden icon becomes none' => array('', null, 0, 'none');
        yield 'legacy shown icon becomes normal' => array('', null, 1, 'normal');
        yield 'invalid values fall back to default normal' => array('huge', 'tiny', null, 'normal');
    }

    #[DataProvider('iconSizeProvider')]
    public function testResolveIconSize($override, $global, $legacyShowIcon, string $expected): void
    {
        self::assertSame($expected, JemAttachmentDisplayHelper::resolveIconSize($override, $global, $legacyShowIcon));
    }

    public function testFrameClass(): void
    {
        self::assertSame(' jem-attachments-frame', JemAttachmentDisplayHelper::frameClass(1));
        self::assertSame('', JemAttachmentDisplayHelper::frameClass(0));
        self::assertSame('', JemAttachmentDisplayHelper::frameClass(''));
    }
}

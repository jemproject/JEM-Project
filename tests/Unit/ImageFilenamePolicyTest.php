<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/imagefilenamepolicy.class.php';

final class ImageFilenamePolicyTest extends TestCase
{
    public function testUniqueModeRemainsTheDefault(): void
    {
        self::assertSame(JemImageFilenamePolicy::UNIQUE, JemImageFilenamePolicy::mode((object) array()));
        self::assertSame(
            JemImageFilenamePolicy::ORIGINAL,
            JemImageFilenamePolicy::mode((object) array('image_filename_policy' => 'original'))
        );
    }

    public function testOriginalNameIsSanitisedAndRetainsItsExtension(): void
    {
        self::assertSame('foto_prueba.jpg', JemImageFilenamePolicy::originalName('Foto Prueba.JPG'));
        self::assertSame('renamed_image.png', JemImageFilenamePolicy::originalName('archive.png', 'Renamed image'));
        self::assertSame('photo.webp', JemImageFilenamePolicy::originalName('folder\\Photo.WEBP'));
    }

    public function testOriginalNameFitsImageColumn(): void
    {
        self::assertLessThanOrEqual(100, strlen(JemImageFilenamePolicy::originalName(str_repeat('x', 130) . '.jpg')));
    }
}

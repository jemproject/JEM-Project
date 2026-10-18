<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SampleImageMetadataTest extends TestCase
{
    public function testPradoSessionIncludesBoundFictionalImageMetadata(): void
    {
        $sql = file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');
        preg_match("/SET `image_metadata` = '([^']+)'\s+WHERE `id` = 16 AND `alias` = 'museum-technology-talk-prado-3';/", $sql, $match);
        self::assertNotEmpty($match);
        $metadata = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['datimage'], array_keys($metadata));
        $image = $metadata['datimage'];
        self::assertSame('event-prado-museum-technology-talk.webp', $image['image']);
        self::assertNotEmpty($image['alt']);
        self::assertNotEmpty($image['caption']);
        self::assertStringContainsString('fictional credit', $image['credit']);
        self::assertStringContainsString('not a licence statement', $image['credit']);
        self::assertTrue($image['footer']);
        self::assertSame('left', $image['align'] ?? 'left');

        $zip = new ZipArchive();
        self::assertTrue($zip->open(JEM_TEST_ROOT . '/admin/assets/sampledata.zip'));
        try {
            self::assertSame(str_replace("\r\n", "\n", $sql), str_replace("\r\n", "\n", $zip->getFromName('sampledata.sql')));
        } finally {
            $zip->close();
        }
    }
}

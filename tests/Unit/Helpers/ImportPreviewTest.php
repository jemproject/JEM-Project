<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!defined('JPATH_CACHE')) {
    define('JPATH_CACHE', sys_get_temp_dir() . '/jem-import-preview-tests-' . getmypid());
}

require_once JEM_TEST_ROOT . '/admin/helpers/importpreview.php';

final class ImportPreviewTest extends TestCase
{
    private array $tokens = array();

    protected function tearDown(): void
    {
        foreach ($this->tokens as $token) {
            JemImportPreviewHelper::deletePreview($token, 42);
        }
    }

    public function testLargePreviewIsPagedAndReadInBoundedChunks(): void
    {
        $rows = array();

        for ($index = 1; $index <= 205; $index++) {
            $rows[] = array('title' => 'Event ' . $index);
        }

        $preview = JemImportPreviewHelper::storePreview(
            array('rows' => $rows, 'records' => $rows, 'source_records' => $rows),
            42,
            'events'
        );
        $this->tokens[] = $preview['payload_token'];

        self::assertTrue($preview['server_paginated']);
        self::assertTrue($preview['preview_limited']);
        self::assertSame(100, count($preview['rows']));
        self::assertSame(205, $preview['total_count']);
        self::assertSame(3, $preview['preview_pages']);

        $lastPage = JemImportPreviewHelper::loadPreviewPage($preview, 42, 3);
        self::assertSame(5, count($lastPage['rows']));
        self::assertSame('Event 201', $lastPage['rows'][0]['title']);

        $batchSizes = array();
        foreach (JemImportPreviewHelper::getPayloadBatches($preview, 42, 'records') as $batch) {
            $batchSizes[] = count($batch);
        }

        self::assertSame(array(100, 100, 5), $batchSizes);
    }

    public function testPreviewTokenCannotBeReadByAnotherUser(): void
    {
        $rows = array_fill(0, 101, array('title' => 'Private'));
        $preview = JemImportPreviewHelper::storePreview(
            array('rows' => $rows, 'records' => $rows, 'source_records' => $rows),
            42,
            'venues'
        );
        $this->tokens[] = $preview['payload_token'];

        $this->expectException(RuntimeException::class);
        JemImportPreviewHelper::loadPreviewPage($preview, 43, 1);
    }

    public function testStoredSourceCannotCreateAdditionalPhpBlocks(): void
    {
        $rows = array_fill(0, 101, array('title' => '<?php throw new RuntimeException("unsafe"); ?>'));
        $preview = JemImportPreviewHelper::storePreview(
            array('rows' => $rows, 'records' => $rows, 'source_records' => $rows),
            42,
            'specialdays'
        );
        $this->tokens[] = $preview['payload_token'];
        $files = glob(JPATH_CACHE . '/com_jem/' . $preview['payload_token'] . '-rows-*.php') ?: array();

        self::assertNotEmpty($files);
        self::assertStringNotContainsString('<?php throw', (string) file_get_contents($files[0]));
        self::assertSame('<?php throw new RuntimeException("unsafe"); ?>', $preview['rows'][0]['title']);
    }
}

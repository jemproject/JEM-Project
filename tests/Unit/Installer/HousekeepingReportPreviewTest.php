<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/classes/housekeepingreports.class.php';

final class HousekeepingReportPreviewTest extends TestCase
{
    private const REPORT = '{"schema":"jem-diagnostics/v1","created_at":"2026-10-28 10:00:00","report":{"notice":0,"warning":1,"error":0,"lines":[]}}';

    public function testJsonPreviewIsReadOnlyAndConsumedOnce(): void
    {
        $path = $this->temporaryFile(self::REPORT);
        try {
            $preview = JemHousekeepingReports::previewUpload($path, 'report.json', false);
            self::assertSame('json', $preview['source']);
            self::assertSame('jem-diagnostics/v1', $preview['data']['schema']);
            self::assertStringContainsString('"warning": 1', $preview['pretty']);

            JemHousekeepingReports::setRequestPreview($preview);
            self::assertSame($preview, JemHousekeepingReports::consumeRequestPreview());
            self::assertNull(JemHousekeepingReports::consumeRequestPreview());
        } finally {
            unlink($path);
        }
    }

    public function testUnsupportedJsonIsRejected(): void
    {
        $path = $this->temporaryFile('{"schema":"other","report":{}}');
        try {
            $this->expectException(InvalidArgumentException::class);
            JemHousekeepingReports::previewUpload($path, 'report.json', false);
        } finally {
            unlink($path);
        }
    }

    public function testZipPreviewRequiresDebugAllAndReadsOnlyTheReport(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP ZIP extension is unavailable.');
        }

        $path = $this->temporaryZip(array(
            'jem-diagnostics-20261028-100000.json' => self::REPORT,
            'logs/jem.log.txt' => 'Sensitive log content must not be shown by the JSON preview.',
        ));
        try {
            $preview = JemHousekeepingReports::previewUpload($path, 'report.zip', true);
            self::assertSame('zip', $preview['source']);
            self::assertStringNotContainsString('Sensitive log content', $preview['pretty']);

            $this->expectException(InvalidArgumentException::class);
            JemHousekeepingReports::previewUpload($path, 'report.zip', false);
        } finally {
            unlink($path);
        }
    }

    public function testZipWithUnsafeEntryIsRejectedWithoutExtraction(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP ZIP extension is unavailable.');
        }

        $path = $this->temporaryZip(array(
            'jem-diagnostics-20261028-100000.json' => self::REPORT,
            '../unexpected.ini' => 'Must never be extracted.',
        ));
        try {
            $this->expectException(InvalidArgumentException::class);
            JemHousekeepingReports::previewUpload($path, 'report.zip', true);
        } finally {
            unlink($path);
        }
    }

    public function testArchiveContainsJsonAndOnlySelectedJemFiles(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP ZIP extension is unavailable.');
        }

        $filename = 'jem-diagnostics-20261028-100000.json';
        $bytes = JemHousekeepingReports::createArchive(self::REPORT, $filename, array(
            array('path' => 'logs/jem.log.txt', 'content' => 'JEM trace'),
            array('path' => 'language/en-GB/com_jem.ini', 'content' => 'COM_JEM_TEST="Test"'),
        ));
        $path = $this->temporaryFile($bytes);
        try {
            $zip = new ZipArchive();
            self::assertTrue($zip->open($path));
            self::assertSame(self::REPORT, $zip->getFromName($filename));
            self::assertSame('JEM trace', $zip->getFromName('logs/jem.log.txt'));
            self::assertSame('COM_JEM_TEST="Test"', $zip->getFromName('language/en-GB/com_jem.ini'));
            self::assertSame(3, $zip->numFiles);
            $zip->close();

            self::assertSame('zip', JemHousekeepingReports::previewUpload($path, 'report.zip', true)['source']);
        } finally {
            unlink($path);
        }
    }

    public function testArchiveRejectsUnexpectedPaths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemHousekeepingReports::createArchive(self::REPORT, 'jem-diagnostics-20261028-100000.json', array(
            array('path' => '../outside.ini', 'content' => 'not allowed'),
        ));
    }

    private function temporaryFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jem-preview-');
        self::assertNotFalse($path);
        file_put_contents($path, $content);

        return $path;
    }

    private function temporaryZip(array $entries): string
    {
        $path = $this->temporaryFile('');
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        foreach ($entries as $name => $content) {
            self::assertTrue($zip->addFromString($name, $content));
        }
        self::assertTrue($zip->close());

        return $path;
    }
}

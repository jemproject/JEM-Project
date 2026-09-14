<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CsvOutputWriterTest extends TestCase
{
    public function testOnlyTheCentralWriterCallsFputcsv(): void
    {
        $sinks = array();

        foreach (array('admin', 'modules', 'plugins', 'site') as $root) {
            $directory = JEM_TEST_ROOT . '/' . $root;

            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                $count = preg_match_all('/\bfputcsv\s*\(/', $source);

                if ($count > 0) {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(JEM_TEST_ROOT) + 1));
                    $sinks[$relative] = $count;
                }
            }
        }

        ksort($sinks);

        self::assertSame(array('site/classes/csv.class.php' => 1), $sinks);
    }

    public function testEveryCsvExporterUsesTheCentralWriterExplicitly(): void
    {
        $exporters = array(
            'admin/controllers/export.php' => 2,
            'admin/models/attachments.php' => 2,
            'admin/models/attendees.php' => 2,
            'admin/models/export.php' => 1,
            'site/controllers/attendees.php' => 2,
        );

        foreach ($exporters as $relative => $expectedCalls) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relative);

            self::assertSame($expectedCalls, substr_count($source, 'JemCsv::putRow('), $relative);
            self::assertStringContainsString(
                "require_once JPATH_SITE . '/components/com_jem/classes/csv.class.php';",
                $source,
                $relative
            );
        }
    }
}

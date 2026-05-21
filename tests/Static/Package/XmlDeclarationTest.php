<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class XmlDeclarationTest extends TestCase
{
    public function testProjectXmlFilesUseUtf8XmlDeclaration(): void
    {
        $expected = '<?xml version="1.0" encoding="UTF-8"?>';
        $invalid = array();

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(JEM_TEST_ROOT, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'xml') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(JEM_TEST_ROOT) + 1));

            if (preg_match('#^(build|vendor)/#', $relative) === 1) {
                continue;
            }

            $handle = fopen($file->getPathname(), 'rb');
            self::assertIsResource($handle, $relative . ' should be readable.');
            $firstLine = rtrim((string) fgets($handle), "\r\n");
            fclose($handle);

            if ($firstLine !== $expected) {
                $invalid[] = $relative . ': ' . $firstLine;
            }
        }

        self::assertSame(array(), $invalid, "XML files must start with {$expected}:\n" . implode("\n", $invalid));
    }
}

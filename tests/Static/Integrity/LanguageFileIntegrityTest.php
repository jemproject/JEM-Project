<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LanguageFileIntegrityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function languageFileProvider(): iterable
    {
        foreach (array(
            JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini',
            JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.sys.ini',
            JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini',
        ) as $path) {
            yield self::relativePath($path) => array($path);
        }
    }

    #[DataProvider('languageFileProvider')]
    public function testLanguageFilesDoNotContainDuplicateKeys(string $path): void
    {
        $seen = array();
        $duplicates = array();
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: array();

        foreach ($lines as $lineNumber => $line) {
            if (!preg_match('/^([A-Z0-9_]+)=/', $line, $match)) {
                continue;
            }

            if (isset($seen[$match[1]])) {
                $duplicates[] = $match[1] . ' on line ' . ($lineNumber + 1);
            }

            $seen[$match[1]] = true;
        }

        self::assertSame(
            array(),
            $duplicates,
            self::relativePath($path) . " contains duplicate language keys:\n" . implode("\n", $duplicates)
        );
    }

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SharedXmlTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function sharedXmlProvider(): iterable
    {
        yield from self::xmlFilesIn(JEM_TEST_ROOT . '/modules');
        yield from self::xmlFilesIn(JEM_TEST_ROOT . '/plugins');
    }

    #[DataProvider('sharedXmlProvider')]
    public function testSharedXmlIsWellFormed(string $path): void
    {
        self::assertXmlFileIsWellFormed($path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    private static function xmlFilesIn(string $directory): iterable
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                yield self::relativePath($file->getPathname()) => array($file->getPathname());
            }
        }
    }

    private static function assertXmlFileIsWellFormed(string $path): void
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $xml = simplexml_load_file($path);
        $errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $messages = array_map(
            static fn (LibXMLError $error): string => trim($error->message) . ' on line ' . $error->line,
            $errors
        );

        self::assertNotFalse($xml, self::relativePath($path) . " is not valid XML:\n" . implode("\n", $messages));
    }

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}

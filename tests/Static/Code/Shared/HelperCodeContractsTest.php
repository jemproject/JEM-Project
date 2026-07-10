<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HelperCodeContractsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function helperProvider(): iterable
    {
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/admin/helpers');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/helpers');
    }

    #[DataProvider('helperProvider')]
    public function testHelperFilesExposeAClassOrFunction(string $path): void
    {
        self::assertMatchesRegularExpression(
            '/\b(?:class|function)\s+[A-Za-z_][A-Za-z0-9_]*/',
            self::read($path),
            self::relativePath($path) . ' should expose a helper class or function.'
        );
    }

    #[DataProvider('helperProvider')]
    public function testHelperClassesDoNotDeclareDuplicateMethods(string $path): void
    {
        $duplicates = array();

        foreach (self::classMethods(self::read($path)) as $class => $methods) {
            $counts = array_count_values(array_map('strtolower', $methods));

            foreach ($counts as $method => $count) {
                if ($count > 1) {
                    $duplicates[] = $class . '::' . $method . '()';
                }
            }
        }

        self::assertSame(
            array(),
            $duplicates,
            self::relativePath($path) . ' must not redeclare helper methods.'
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    private static function phpFilesIn(string $directory): iterable
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                yield self::relativePath($file->getPathname()) => array($file->getPathname());
            }
        }
    }

    private static function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function classMethods(string $code): array
    {
        $tokens = token_get_all($code);
        $classes = array();
        $currentClass = null;
        $pendingClass = null;
        $classBraceLevel = null;
        $braceLevel = 0;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_CLASS) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $pendingClass = $tokens[$j][1];
                        $classes[$pendingClass] = $classes[$pendingClass] ?? array();
                        break;
                    }
                }
                continue;
            }

            if ($token === '{') {
                $braceLevel++;

                if ($pendingClass !== null) {
                    $currentClass = $pendingClass;
                    $pendingClass = null;
                    $classBraceLevel = $braceLevel;
                }
                continue;
            }

            if ($token === '}') {
                if ($classBraceLevel !== null && $braceLevel === $classBraceLevel) {
                    $currentClass = null;
                    $classBraceLevel = null;
                }

                $braceLevel--;
                continue;
            }

            if ($currentClass !== null && is_array($token) && $token[0] === T_FUNCTION) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }

                    if ($tokens[$j] === '&') {
                        continue;
                    }

                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $classes[$currentClass][] = $tokens[$j][1];
                    }

                    break;
                }
            }
        }

        return $classes;
    }

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}

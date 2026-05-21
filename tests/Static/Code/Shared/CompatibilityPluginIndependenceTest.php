<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CompatibilityPluginIndependenceTest extends TestCase
{
    public function testRuntimeCodeDoesNotUseBackwardCompatibilityAliases(): void
    {
        $offenders = array();
        $patterns = array(
            '/Joomla\\\\CMS\\\\Object\\\\CMSObject/',
            '/class_alias\s*\(/',
            '/\bJFactory\b/',
            '/\bJRoute\b/',
            '/\bJText\b/',
            '/\bJHtml\b/',
            '/\bJUri\b/',
            '/\bJRequest\b/',
            '/\bJError\b/',
            '/\bJCache(?:Controller)?\b/',
            '/\bJModuleHelper\b/',
            '/\bJPlugin\b/',
            '/\bJTable\b/',
            '/\bJModel(?:Legacy)?\b/',
            '/\bJController(?:Legacy)?\b/',
            '/\bJView(?:Legacy)?\b/',
            '/^use\s+Joomla\\\\[^;]+\s+as\s+J[A-Za-z0-9_]+\s*;/m',
            '/jimport\s*\(/',
        );

        foreach ($this->runtimePhpFiles() as $path) {
            $code = (string) file_get_contents($path);

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $code) === 1) {
                    $offenders[] = $this->relativePath($path) . ' matches ' . $pattern;
                }
            }
        }

        self::assertSame(array(), $offenders, "Runtime code should not depend on Joomla's backward compatibility plugin aliases:\n" . implode("\n", $offenders));
    }

    public function testRuntimeCodeUsesImportsInsteadOfDirectJoomlaFqcns(): void
    {
        $offenders = array();

        foreach ($this->runtimePhpFiles() as $path) {
            $code = (string) file_get_contents($path);

            if (preg_match('/\\\\Joomla\\\\(?:CMS|Filesystem|Registry|Utilities|String|DI|Event|Component)\\\\/', $code) === 1) {
                $offenders[] = $this->relativePath($path);
            }
        }

        self::assertSame(array(), $offenders, "Runtime PHP should import Joomla classes with use statements instead of direct FQCN calls:\n" . implode("\n", $offenders));
    }

    public function testRuntimeCodeDoesNotLoadLegacyExternalJquery(): void
    {
        $offenders = array();
        $patterns = array(
            '/ajax\.googleapis\.com\/ajax\/libs\/jquery/i',
            '/jquery\/1\.\d+\.\d+\/jquery\.min\.js/i',
            '/jQuery\.noConflict\s*\(/',
        );

        foreach ($this->runtimePhpFiles() as $path) {
            $code = (string) file_get_contents($path);

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $code) === 1) {
                    $offenders[] = $this->relativePath($path) . ' matches ' . $pattern;
                }
            }
        }

        self::assertSame(array(), $offenders, "Joomla 6 views should use WebAssetManager's jquery asset instead of loading legacy external jQuery:\n" . implode("\n", $offenders));
    }


    /**
     * @return iterable<string>
     */
    private function runtimePhpFiles(): iterable
    {
        foreach (array('admin', 'site', 'modules', 'plugins', 'package') as $directory) {
            $root = JEM_TEST_ROOT . '/' . $directory;

            if (!is_dir($root)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    yield $file->getPathname();
                }
            }
        }

        yield JEM_TEST_ROOT . '/script.php';
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}

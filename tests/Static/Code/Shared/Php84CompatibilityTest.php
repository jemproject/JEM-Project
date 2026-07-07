<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Php84CompatibilityTest extends TestCase
{
    public function testRuntimeCodeDoesNotAllowDynamicProperties(): void
    {
        $offenders = array();

        foreach ($this->runtimePhpFiles() as $path) {
            $code = (string) file_get_contents($path);

            if (preg_match('/#\[\s*\\\\?AllowDynamicProperties\s*\]/', $code) === 1) {
                $offenders[] = $this->relativePath($path);
            }
        }

        self::assertSame(array(), $offenders, "Runtime classes must declare properties instead of allowing dynamic properties:\n" . implode("\n", $offenders));
    }

    public function testRuntimeCodeDoesNotUseDeprecatedUtf8Helpers(): void
    {
        $offenders = array();
        $patterns = array(
            '/\butf8_encode\s*\(/',
            '/\butf8_decode\s*\(/',
        );

        foreach ($this->runtimePhpFiles() as $path) {
            $code = (string) file_get_contents($path);

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $code) === 1) {
                    $offenders[] = $this->relativePath($path) . ' matches ' . $pattern;
                }
            }
        }

        self::assertSame(array(), $offenders, "Runtime code should avoid PHP 8.4-deprecated UTF-8 helper functions:\n" . implode("\n", $offenders));
    }

    public function testCalendarClassesDeclareAssignedInstanceProperties(): void
    {
        $offenders = array();
        $parentDeclarations = $this->declaredPropertiesFromFile(JEM_TEST_ROOT . '/site/classes/calendar.class.php');

        foreach (array(JEM_TEST_ROOT . '/site/classes/calendar.class.php', JEM_TEST_ROOT . '/site/classes/activecalendarweek.php') as $path) {
            $code = (string) file_get_contents($path);
            preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)(?:\s*(?:=|\+\+|--)|\s*\[[^\]]*\]\s*=)/', $code, $assignedMatches);

            $declared = $this->declaredPropertiesFromFile($path);
            if (basename($path) === 'activecalendarweek.php') {
                $declared = array_values(array_unique(array_merge($parentDeclarations, $declared)));
            }
            $assigned = array_unique($assignedMatches[1]);
            $missing = array_values(array_diff($assigned, $declared));

            foreach ($missing as $property) {
                $offenders[] = $this->relativePath($path) . ' uses undeclared $' . $property;
            }
        }

        self::assertSame(array(), $offenders, "Calendar classes must declare assigned properties for PHP 8.4 compatibility:\n" . implode("\n", $offenders));
    }

    public function testJemViewChildrenDoNotNarrowTemplatePropertyVisibility(): void
    {
        $offenders = array();
        $adminPublicProperties = $this->publicPropertiesFromFile(JEM_TEST_ROOT . '/admin/classes/admin.view.class.php');
        $sitePublicProperties = $this->publicPropertiesFromFile(JEM_TEST_ROOT . '/site/classes/view.class.php');

        foreach ($this->viewPhpFiles() as $path) {
            $code = (string) file_get_contents($path);

            if (preg_match('/class\s+\w+\s+extends\s+(Jem(?:Admin)?View)\b/', $code, $classMatch) !== 1) {
                continue;
            }

            $publicProperties = $classMatch[1] === 'JemAdminView' ? $adminPublicProperties : $sitePublicProperties;
            $propertyPattern = '/\b(?:protected|private)\s+\$(' . implode('|', array_map(static fn ($property) => preg_quote($property, '/'), $publicProperties)) . ')\b/';

            if (preg_match_all($propertyPattern, $code, $matches) > 0) {
                foreach ($matches[0] as $match) {
                    $offenders[] = $this->relativePath($path) . ' declares ' . trim($match);
                }
            }
        }

        self::assertSame(array(), $offenders, "JEM view children must keep inherited template properties public for PHP 8.4 compatibility:\n" . implode("\n", $offenders));
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

    /**
     * @return iterable<string>
     */
    private function viewPhpFiles(): iterable
    {
        foreach (array('admin/views', 'site/views') as $directory) {
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
    }

    /**
     * @return list<string>
     */
    private function publicPropertiesFromFile(string $path): array
    {
        $code = (string) file_get_contents($path);
        preg_match_all('/\bpublic\s+\$([A-Za-z_][A-Za-z0-9_]*)\b/', $code, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return list<string>
     */
    private function declaredPropertiesFromFile(string $path): array
    {
        $code = (string) file_get_contents($path);
        preg_match_all('/\b(?:var|public|protected|private)\s+\$([A-Za-z_][A-Za-z0-9_]*)\b/', $code, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}

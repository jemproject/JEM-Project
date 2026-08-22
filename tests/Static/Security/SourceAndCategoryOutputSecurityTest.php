<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SourceAndCategoryOutputSecurityTest extends TestCase
{
    public function testSourceEditorOnlyWritesExistingValidatedStylesheets(): void
    {
        $model = $this->read('/admin/models/source.php');
        $resolver = $this->method($model, 'resolveSourceFile');
        $save = $this->method($model, 'save');

        self::assertStringContainsString('JemCssFilePolicy::isValidFileName($file)', $resolver);
        self::assertStringContainsString('!is_file($filePath) || is_link($filePath)', $resolver);
        self::assertStringContainsString("'path'   => \$realFile", $resolver);
        self::assertStringContainsString("'base'   => \$realBase", $resolver);
        self::assertStringContainsString('!is_file($filePath) || is_link($filePath)', $save);
        self::assertStringContainsString('$realBase = realpath($source->base);', $save);
        self::assertStringContainsString('$realFile = realpath($filePath);', $save);

        $write = strpos($save, 'File::write($filePath');
        $existing = strpos($save, '!is_file($filePath) || is_link($filePath)');
        $containment = strpos($save, 'strpos($fileCheck, $baseCheck) !== 0');

        self::assertNotFalse($write);
        self::assertNotFalse($existing);
        self::assertNotFalse($containment);
        self::assertLessThan($write, $existing);
        self::assertLessThan($write, $containment);
    }

    public function testRelatedCssOperationsUseTheSameFilePolicy(): void
    {
        $manager = $this->read('/admin/models/cssmanager.php');
        $helper = $this->read('/site/helpers/helper.php');

        self::assertGreaterThanOrEqual(6, substr_count($manager, 'JemCssFilePolicy::isValidFileName'));
        self::assertStringContainsString("array('JemCssFilePolicy', 'isValidFileName')", $manager);
        self::assertGreaterThanOrEqual(5, substr_count($manager, 'is_link('));
        self::assertStringContainsString('$realPath = realpath($path);', $manager);
        self::assertStringContainsString('JemCssFilePolicy::isValidFileName($file)', $helper);
        self::assertStringContainsString('!is_link($customPath)', $helper);
    }

    public function testCategoryListEscapesNamesBeforeBuildingMarkup(): void
    {
        $method = $this->method($this->read('/site/classes/output.class.php'), 'getCategoryList');

        self::assertStringContainsString("htmlspecialchars((string) (\$category->catname ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')", $method);
        self::assertStringContainsString('$categoryName .', $method);
        self::assertStringContainsString('(int) $category->id', $method);
        self::assertStringNotContainsString('$category->catname .', $method);
        self::assertStringNotContainsString('= $category->catname;', $method);
    }

    public function testOtherCategoryMarkupEscapesNamesAtItsHtmlSinks(): void
    {
        $output = $this->read('/site/classes/output.class.php');
        $flyer = $this->method($output, 'flyer');

        self::assertStringContainsString(
            "\$info = htmlspecialchars((string) \$info, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');",
            $flyer
        );
        self::assertLessThan(strpos($flyer, '<img '), strpos($flyer, '$info = htmlspecialchars'));

        $calendarTemplates = array(
            '/site/views/calendar/tmpl/default.php',
            '/site/views/calendar/tmpl/responsive/default.php',
            '/site/views/category/tmpl/calendar.php',
            '/site/views/category/tmpl/responsive/calendar.php',
            '/site/views/venue/tmpl/calendar.php',
            '/site/views/venue/tmpl/responsive/calendar.php',
            '/site/views/weekcal/tmpl/default.php',
            '/site/views/weekcal/tmpl/responsive/default.php',
        );

        foreach ($calendarTemplates as $template) {
            $contents = $this->read($template);

            self::assertStringContainsString('$this->escape($category->catname)', $contents, $template);
            self::assertDoesNotMatchRegularExpression('/(?:\.\s*|echo\s+)\$category->catname/', $contents, $template);
            self::assertStringNotContainsString('echo $cat->catname', $contents, $template);
        }
    }

    private function method(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        self::assertNotFalse($start, 'Method not found: ' . $name);

        $next = strpos($source, "\n    /**", $start);

        return substr($source, $start, $next === false ? null : $next - $start);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}

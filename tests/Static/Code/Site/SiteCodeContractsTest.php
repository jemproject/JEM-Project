<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SiteCodeContractsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function siteCodeProvider(): iterable
    {
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/controllers');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/models');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/helpers');
    }

    #[DataProvider('siteCodeProvider')]
    public function testSiteCodeFilesBlockDirectAccess(string $path): void
    {
        self::assertMatchesRegularExpression(
            '/defined\s*\(\s*[\'"](?:_JEXEC|JPATH_BASE|JPATH_PLATFORM)[\'"]\s*\)\s*or\s+die\s*;?/i',
            self::read($path),
            self::relativePath($path) . ' should block direct access with a Joomla guard.'
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function siteControllerProvider(): iterable
    {
        yield from self::rootPhpFilesWithExpectedClass(JEM_TEST_ROOT . '/site/controllers', 'JemController');
    }

    #[DataProvider('siteControllerProvider')]
    public function testSiteControllersKeepExpectedClassName(string $path, string $expectedClass): void
    {
        $this->assertExpectedClassByPrefix($path, $expectedClass);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function siteModelProvider(): iterable
    {
        yield from self::rootPhpFilesWithExpectedClass(JEM_TEST_ROOT . '/site/models', 'JemModel');
    }

    #[DataProvider('siteModelProvider')]
    public function testSiteModelsKeepExpectedClassName(string $path, string $expectedClass): void
    {
        $this->assertExpectedClassByPrefix($path, $expectedClass);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function siteFormFieldProvider(): iterable
    {
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/models/fields');
        yield from self::phpFilesIn(JEM_TEST_ROOT . '/site/models/links');
    }

    #[DataProvider('siteFormFieldProvider')]
    public function testSiteFormFieldsDefineJFormFieldClass(string $path): void
    {
        self::assertMatchesRegularExpression(
            '/(?:class\s+JFormField[A-Za-z0-9_]+\s+extends\s+[A-Za-z0-9_\\\\]+|require_once\s+JPATH_ADMINISTRATOR\s*\.\s*[\'"]\/components\/com_jem\/models\/fields\/[A-Za-z0-9_]+\.php[\'"])/',
            self::read($path),
            self::relativePath($path) . ' should define or delegate to a JFormField class.'
        );
    }

    public function testCategoryCalendarModelResolvesCategoryForAccessChecks(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/models/categorycal.php');

        self::assertStringContainsString('protected $_item = null;', $code);
        self::assertStringContainsString('public function getCategory()', $code);
        self::assertStringContainsString("new JemCategories(\$this->_id, array('countItems' => 0))", $code);
        self::assertStringContainsString('$this->_item = $categories->get($this->_id);', $code);
        self::assertStringContainsString('$this->_item = null;', $code);
    }

    public function testCategoryCalendarViewPassesResolvedCategoryIdToModel(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/views/category/view.html.php');

        self::assertStringContainsString('$catid = (int) $catid;', $code);
        self::assertStringContainsString('$model->setId($catid);', $code);
        self::assertMatchesRegularExpression(
            '/\$model->setId\(\$catid\);\s*\$model->setDate\(/',
            $code
        );
    }

    public function testCategoryNodeDeclaresComputedAccessFlag(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/classes/categories.class.php');

        self::assertStringContainsString('public $user_has_access_category = null;', $code);
        self::assertStringContainsString('property_exists($this, $name)', $code);
        self::assertStringContainsString('END as user_has_access_category', $code);
    }

    public function testCategoryNodeDeclaresJemCategoryAndPluginFields(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/classes/categories.class.php');

        foreach (array(
            'catname',
            'meta_keywords',
            'meta_description',
            'image',
            'color',
            'text',
            'jcfields',
            'type_id',
            'email',
            'emailacljl',
        ) as $property) {
            self::assertMatchesRegularExpression(
                '/public\s+\$' . preg_quote($property, '/') . '\b/',
                $code,
                'JemCategoryNode must declare $' . $property . ' to avoid PHP 8.4 dynamic/undefined property warnings.'
            );
        }
    }

    public function testCategoryViewAvoidsNullMetadataAndImageArguments(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/views/category/view.html.php');

        self::assertStringContainsString("\$document->setMetadata('keywords', (string) \$category->meta_keywords);", $code);
        self::assertStringContainsString("JemImage::flyercreator((string) \$category->image,'category')", $code);
    }

    public function testAttachmentClassUsesCmsInputFilterFactory(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/classes/attachment.class.php');

        self::assertStringContainsString('use Joomla\CMS\Filter\InputFilter;', $code);
        self::assertStringNotContainsString('use Joomla\Filter\InputFilter;', $code);
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

    /**
     * @return iterable<string, array{string, string}>
     */
    private static function rootPhpFilesWithExpectedClass(string $directory, string $prefix): iterable
    {
        foreach (new DirectoryIterator($directory) as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $expectedClass = $prefix . self::classSuffixFromFilename($file->getBasename('.php'));
            yield self::relativePath($file->getPathname()) => array($file->getPathname(), $expectedClass);
        }
    }

    private static function classSuffixFromFilename(string $filename): string
    {
        return implode('', array_map('ucfirst', preg_split('/[^A-Za-z0-9]+/', strtolower($filename)) ?: array()));
    }

    private static function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private static function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }

    private function assertExpectedClassByPrefix(string $path, string $expectedClass): void
    {
        preg_match('/class\s+([A-Za-z0-9_]+)\s+extends\s+[A-Za-z0-9_\\\\]+/', self::read($path), $match);

        self::assertNotSame(array(), $match, self::relativePath($path) . ' should define a class with an extends clause.');
        self::assertSame(
            strtolower($expectedClass),
            strtolower($match[1]),
            self::relativePath($path) . ' should define ' . $expectedClass . ' ignoring camel-case word boundaries.'
        );
    }
}

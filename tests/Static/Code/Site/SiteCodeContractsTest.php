<?php

declare(strict_types=1);

use JemProject\Tests\Support\PhpClassContract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SiteCodeContractsTest extends TestCase
{
    /** @var array<string, array{string, string}> Approved format bridges and their targets. */
    private const CONTROLLER_BRIDGES = [
        'imagefilename.json.php' => ['imagefilename.php', 'JemControllerImagefilename'],
    ];

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
        foreach (self::rootPhpFilesWithExpectedClass(JEM_TEST_ROOT . '/site/controllers', 'JemController') as $name => $case) {
            if (!isset(self::CONTROLLER_BRIDGES[basename($case[0])])) {
                yield $name => $case;
            }
        }
    }

    #[DataProvider('siteControllerProvider')]
    public function testSiteControllersKeepExpectedClassName(string $path, string $expectedClass): void
    {
        $this->assertExpectedClassByPrefix($path, $expectedClass);
    }

    /**
     * List approved bridges independently of discovery so missing files still fail.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function siteControllerBridgeProvider(): iterable
    {
        foreach (self::CONTROLLER_BRIDGES as $bridge => [$target, $class]) {
            $directory = JEM_TEST_ROOT . '/site/controllers/';
            yield $bridge => [$directory . $bridge, $directory . $target, $class];
        }
    }

    /**
     * Verify that an approved JSON bridge delegates to the expected controller.
     *
     * @param string $bridge        Bridge file path.
     * @param string $target        Expected implementation path.
     * @param string $expectedClass Controller class required by Joomla.
     * @return void
     */
    #[DataProvider('siteControllerBridgeProvider')]
    public function testSiteControllerBridgesLoadExpectedClass(string $bridge, string $target, string $expectedClass): void
    {
        PhpClassContract::assertBridge($bridge, $target, $expectedClass);
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
        self::assertStringContainsString("(string) (\$category->image_path ?? '')", $code);
    }

    public function testVenueViewAvoidsNullMetadataArguments(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/views/venue/view.html.php');

        self::assertStringContainsString(
            "\$document->setMetadata('keywords', (string) (\$venue->meta_keywords ?? ''));",
            $code
        );
        self::assertStringContainsString(
            "\$document->setDescription(strip_tags((string) (\$venue->meta_description ?? '')));",
            $code
        );
    }

    public function testCategoryListPaginationKeepsItsRouteContext(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/views/category/view.html.php');

        self::assertStringContainsString("\$pagination->setAdditionalUrlParam('option', 'com_jem');", $code);
        self::assertStringContainsString("\$pagination->setAdditionalUrlParam('view', 'category');", $code);
        self::assertStringContainsString("\$pagination->setAdditionalUrlParam('layout', 'default');", $code);
        self::assertStringContainsString("\$pagination->setAdditionalUrlParam('id', (string) \$category->slug);", $code);
        self::assertStringContainsString("\$pagination->setAdditionalUrlParam('Itemid', (int) \$menuitem->id);", $code);
    }

    public function testVenueListPaginationKeepsItsRouteContext(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/views/venue/view.html.php');

        self::assertStringContainsString("\$pagination->setAdditionalUrlParam('option', 'com_jem');", $code);
        self::assertStringContainsString("\$pagination->setAdditionalUrlParam('view', 'venue');", $code);
        self::assertStringContainsString("\$pagination->setAdditionalUrlParam('layout', 'default');", $code);
        self::assertStringContainsString("\$pagination->setAdditionalUrlParam('id', (string) \$venue->slug);", $code);
        self::assertStringContainsString("\$pagination->setAdditionalUrlParam('Itemid', (int) \$menuitem->id);", $code);
    }

    public function testAttachmentClassUsesCmsInputFilterFactory(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/classes/attachment.class.php');

        self::assertStringContainsString('use Joomla\CMS\Filter\InputFilter;', $code);
        self::assertStringNotContainsString('use Joomla\Filter\InputFilter;', $code);
    }

    public function testNominatimRequestsUseAStableIdentifyingUserAgent(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/classes/output.class.php');

        self::assertStringNotContainsString('JemHelper::config()->get(', $code);
        self::assertSame(2, substr_count($code, '"header" => "User-Agent: " . self::nominatimUserAgent()'));
        self::assertStringContainsString(
            "return 'JEM (+https://www.joomlaeventmanager.net; site=' . Uri::root() . ')';",
            $code
        );
    }

    public function testPdfLinkBuilderDoesNotMutateTheSharedRequestUri(): void
    {
        $code = self::read(JEM_TEST_ROOT . '/site/classes/output.class.php');
        $start = strpos($code, 'static protected function buildCurrentPdfLink()');
        $end = strpos($code, 'static public function archivebutton', $start);

        self::assertNotFalse($start);
        self::assertNotFalse($end);

        $method = substr($code, $start, $end - $start);

        self::assertStringContainsString('$uri = clone Uri::getInstance();', $method);
        self::assertStringNotContainsString('$uri = Uri::getInstance();', $method);
    }

    public function testMergedGlobalAttributesAreClonedPerItem(): void
    {
        $expectedCloneCounts = array(
            '/site/helpers/association.php' => 1,
            '/site/models/event.php' => 4,
            '/site/models/venue.php' => 1,
            '/site/views/event/view.raw.php' => 1,
        );

        foreach ($expectedCloneCounts as $path => $expectedCount) {
            $code = self::read(JEM_TEST_ROOT . $path);

            self::assertSame(
                $expectedCount,
                substr_count($code, 'clone JemHelper::globalattribs()'),
                $path . ' must clone the shared Registry before merging item-specific parameters.'
            );
        }

        $rawView = self::read(JEM_TEST_ROOT . '/site/views/event/view.raw.php');
        self::assertStringContainsString('$row->params = clone $row->params;', $rawView);
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

    /**
     * Validate the expected class without assuming it is the first declaration.
     *
     * @param string $path          PHP implementation path.
     * @param string $expectedClass Class name derived from the file and prefix.
     * @return void
     */
    private function assertExpectedClassByPrefix(string $path, string $expectedClass): void
    {
        PhpClassContract::assertClass($path, $expectedClass);
    }
}

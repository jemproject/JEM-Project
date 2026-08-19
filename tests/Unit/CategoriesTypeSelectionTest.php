<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

namespace Joomla\CMS\MVC\Model {
    if (!class_exists(BaseDatabaseModel::class, false)) {
        class BaseDatabaseModel
        {
        }
    }
}

namespace {
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;

    require_once JEM_TEST_ROOT . '/site/models/categories.php';

    final class CategoriesTypeSelectionTest extends TestCase
    {
        public static function selectionProvider(): iterable
        {
            yield 'missing selection uses normal categories' => array(false, '', null, false, 0);
            yield 'blank menu selection uses normal categories' => array(false, '', '', false, 0);
            yield 'blank request overrides an existing menu value' => array(true, '', 0, false, 0);
            yield 'explicit zero groups all category types' => array(true, '0', null, true, 0);
            yield 'existing zero menu value remains grouped' => array(false, '', 0, true, 0);
            yield 'positive selection filters one category type' => array(true, '12', null, true, 12);
            yield 'invalid selection does not enable grouping' => array(true, 'invalid', 0, false, 0);
        }

        #[DataProvider('selectionProvider')]
        public function testCategoryTypeModesAreDistinct(
            bool $requestHasTypeId,
            $requestTypeId,
            $menuTypeId,
            bool $expectedActive,
            int $expectedTypeId
        ): void {
            $method = new ReflectionMethod(JemModelCategories::class, 'resolveTypeSelection');
            $result = $method->invoke(null, $requestHasTypeId, $requestTypeId, $menuTypeId);

            self::assertSame(array($expectedActive, $expectedTypeId), $result);
        }

        public function testMenuSelectorOffersNormalGroupedAndSpecificTypeModes(): void
        {
            $xml = simplexml_load_file(JEM_TEST_ROOT . '/site/views/categories/tmpl/default.xml');
            self::assertNotFalse($xml);

            $fields = $xml->xpath("//field[@name='typeid']");
            self::assertCount(1, $fields);

            $field = $fields[0];
            self::assertSame('', (string) $field->option['value']);
            self::assertSame('COM_JEM_TYPECATEGORIES_NO_GROUPING', trim((string) $field->option));
            self::assertStringContainsString(
                "SELECT 0 AS value, 'COM_JEM_TYPECATEGORIES_ALL_TYPES' AS text",
                (string) $field['query']
            );
            self::assertSame('true', (string) $field['translate']);
        }

        public function testGeneratedMenusKeepNormalAndGroupedCategoriesSeparate(): void
        {
            $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');

            self::assertStringContainsString(
                "'Categories', 'categories-list', 'index.php?option=com_jem&view=categories'",
                $controller
            );
            self::assertStringContainsString(
                "'Categories by Type', 'categories-by-type', 'index.php?option=com_jem&view=categories&id=1&typeid=0'",
                $controller
            );
        }

        public function testBothCategoryLayoutsOnlyRenderSectionHeadersInGroupedMode(): void
        {
            foreach (array(
                '/site/views/categories/tmpl/default.php',
                '/site/views/categories/tmpl/responsive/default.php',
            ) as $path) {
                $layout = (string) file_get_contents(JEM_TEST_ROOT . $path);

                self::assertStringContainsString(
                    "if (!empty(\$this->isGroupedTypeCategoryView))",
                    $layout
                );
                self::assertStringContainsString('COM_JEM_TYPECATEGORIES_UNASSIGNED', $layout);
            }
        }

        public function testMenuModeLabelsExistInTheAdministratorLanguageFile(): void
        {
            $language = (string) file_get_contents(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini');

            self::assertStringContainsString(
                'COM_JEM_TYPECATEGORIES_NO_GROUPING="- No type grouping -"',
                $language
            );
            self::assertStringContainsString(
                'COM_JEM_TYPECATEGORIES_ALL_TYPES="All category types"',
                $language
            );
        }
    }
}

<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModJemTemplateStyleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function browserLayoutProvider(): iterable
    {
        yield 'JEM Basic default' => array('/modules/mod_jem/tmpl/default.php');
        yield 'JEM Basic responsive' => array('/modules/mod_jem/tmpl/responsive.php');
        yield 'JEM Basic table' => array('/modules/mod_jem/tmpl/table.php');
        yield 'JEM Basic table style' => array('/modules/mod_jem/tmpl/table-style.php');
        yield 'JEM Basic table advanced' => array('/modules/mod_jem/tmpl/table-advanced.php');
        yield 'JEM Banner cards' => array('/modules/mod_jem_banner/tmpl/cards.php');
    }

    #[DataProvider('browserLayoutProvider')]
    public function testBrowserLinksDoNotOverrideTemplatePresentation(string $layout): void
    {
        $path = JEM_TEST_ROOT . $layout;
        self::assertFileExists($path);

        $template = (string) file_get_contents($path);

        self::assertDoesNotMatchRegularExpression(
            '/<a\b[^>]*\bstyle\s*=/i',
            $template,
            basename($layout) . ' must leave link colors and decoration to the Joomla site template.'
        );
    }
}

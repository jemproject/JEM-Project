<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrontendListActionsTest extends TestCase
{
    public static function layoutProvider(): iterable
    {
        foreach (['myevents' => 'events', 'myvenues' => 'venues'] as $view => $entity) {
            foreach (['', 'responsive/'] as $layout) {
                yield $view . '/' . $layout => [$view, $layout . 'default_' . $entity . '.php'];
            }
        }
    }

    #[DataProvider('layoutProvider')]
    public function testStatusActionsKeepTheProtectedPostForm(string $view, string $layout): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/tmpl/' . $layout);

        self::assertStringNotContainsString('listItemTask(', $source);
        self::assertStringContainsString('class="jem-status-link"', $source);
        self::assertStringContainsString('data-jem-item-id="cb<?php echo (int) $i; ?>"', $source);
        self::assertStringContainsString('data-jem-task="' . $view . '.<?php echo $statusTask; ?>"', $source);
        self::assertStringContainsString("? 'unpublish' : 'publish'", $source);
        self::assertStringContainsString('method="post"', $source);
        self::assertStringContainsString("HTMLHelper::_('form.token')", $source);
        self::assertStringContainsString("get('access-change', false)", $source);
        self::assertStringContainsString('empty($this->print)', $source);
        self::assertStringContainsString('Joomla.checkAll(this)', $source);
    }

    public function testBothViewsExplicitlyLoadTheSharedScriptWithJoomlaCore(): void
    {
        foreach (['myevents', 'myvenues'] as $view) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php');
            self::assertMatchesRegularExpression(
                "/registerAndUseScript\(\s*'com_jem.frontend-list-actions',\s*"
                . "'media\/com_jem\/js\/frontend-list-actions.js',\s*array\(\),\s*"
                . "array\('defer' => true\),\s*array\('core'\)\s*\)/",
                $source
            );
        }
        self::assertFileExists(JEM_TEST_ROOT . '/media/js/frontend-list-actions.js');
    }
}

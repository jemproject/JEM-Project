<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TeaserReadMoreRouteTest extends TestCase
{
    private string $helper;

    protected function setUp(): void
    {
        $this->helper = (string) file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem_teaser/helper.php');
    }

    public function testReadMoreOpensTheDetailsLayout(): void
    {
        self::assertStringContainsString('$readMoreParams = clone $params;', $this->helper);
        self::assertStringContainsString(
            '$readMoreParams->set(\'event_link_event_layout\', \'details\');',
            $this->helper
        );
        self::assertStringContainsString(
            'JemHelperRoute::getEventRoute($row->slug), $readMoreParams',
            $this->helper
        );
    }

    public function testTitleLinkKeepsTheConfiguredEventLayout(): void
    {
        self::assertStringContainsString(
            '$lists[$i]->eventlink   = ($hasEventAccess && $params->get(\'linkevent\', 1)) ? '
                . 'Route::_(JemHelper::applyEventRouteLayout(JemHelperRoute::getEventRoute($row->slug), $params)) : \'\';',
            $this->helper
        );
    }
}

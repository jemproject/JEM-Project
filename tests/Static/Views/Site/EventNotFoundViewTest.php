<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

namespace JEM\Tests\Static\Views\Site;

use PHPUnit\Framework\TestCase;

/**
 * Prevent invalid event IDs from reaching property-dependent view setup.
 */
class EventNotFoundViewTest extends TestCase
{
    public function testMissingEventIsHandledBeforeAnyEventPropertyIsUsed(): void
    {
        $path = JEM_TEST_ROOT . '/site/views/event/view.html.php';
        $source = file_get_contents($path);

        $this->assertNotFalse($source);

        $itemLoad = strpos($source, "\$this->item        = \$this->get('Item');");
        $missingGuard = strpos($source, 'if (empty($this->item))', $itemLoad);
        $contactsLoad = strpos($source, "\$this->contacts    = \$this->get('Contacts');");
        $categoryRead = strpos($source, 'isset($this->item->categories)');
        $categoryWrite = strpos($source, '$this->item->categories = $categories;');

        $this->assertNotFalse($itemLoad, 'The event item load was not found.');
        $this->assertNotFalse($missingGuard, 'The missing-event guard was not found.');
        $this->assertNotFalse($contactsLoad, 'The contacts load was not found.');
        $this->assertNotFalse($categoryRead, 'The category property read was not found.');
        $this->assertNotFalse($categoryWrite, 'The category property assignment was not found.');
        $this->assertGreaterThan($itemLoad, $missingGuard);
        $this->assertLessThan($contactsLoad, $missingGuard);
        $this->assertLessThan($categoryRead, $missingGuard);
        $this->assertLessThan($categoryWrite, $missingGuard);

        $guardEnd = strpos($source, '$this->contacts', $missingGuard);
        $guardSource = substr($source, $missingGuard, $guardEnd - $missingGuard);

        $this->assertStringContainsString("Text::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND')", $guardSource);
        $this->assertMatchesRegularExpression('/throw new \\\\Exception\\([^;]+,\\s*404\\);/', $guardSource);
    }
}

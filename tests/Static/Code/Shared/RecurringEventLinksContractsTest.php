<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RecurringEventLinksContractsTest extends TestCase
{
    public function testRegularRecurrencesStoreAndCopyLinksInTheRequiredOrder(): void
    {
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');

        $validation = strpos($model, "isset(\$data['event_links']) && !\$this->validateLinkData");
        $recurrenceBranch = strpos($model, 'if($isInitialEvent)');
        $rootSave = strpos($model, "saveLinks(\$pk, \$data['event_links'])");
        $cleanup = strpos($model, 'JemHelper::cleanup(2)');

        self::assertIsInt($validation);
        self::assertIsInt($recurrenceBranch);
        self::assertIsInt($rootSave);
        self::assertIsInt($cleanup);
        self::assertLessThan($recurrenceBranch, $validation);
        self::assertLessThan($cleanup, $rootSave);
        self::assertStringContainsString("saveLinks((int) \$event['id'], \$data['event_links'])", $model);
        self::assertStringContainsString('static public function copyEventLinks($sourceEventId, $targetEventId)', $helper);
        self::assertStringContainsString("'INSERT INTO ' . \$db->quoteName('#__jem_links')", $helper);
        self::assertStringContainsString("' WHERE ' . \$db->quoteName('event_id') . ' = ' . \$sourceEventId", $helper);
        self::assertStringContainsString('self::copyEventLinks((int) $ref_event->id, (int) $new_event->id)', $helper);
    }

    public function testCustomSeriesCopyAndSynchroniseLinks(): void
    {
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');

        self::assertStringContainsString('JemHelper::copyEventLinks($rootEventId, (int) $copy->id)', $model);
        self::assertStringContainsString('JemHelper::copyEventLinks($sourceEventId, (int) $event->id)', $model);
        self::assertStringContainsString("saveLinks(\$eventId, \$data['event_links'])", $model);
    }

    public function testFrontendUsesTheSharedEventSaveModel(): void
    {
        $frontendModel = file_get_contents(JEM_TEST_ROOT . '/site/models/editevent.php');

        self::assertStringContainsString("require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/event.php';", $frontendModel);
        self::assertStringContainsString('class JemModelEditevent extends JemModelEvent', $frontendModel);
    }
}

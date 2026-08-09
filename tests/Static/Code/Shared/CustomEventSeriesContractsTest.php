<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CustomEventSeriesContractsTest extends TestCase
{
    public function testSchemaIsAdditiveAndKeepsArithmeticRecurrenceSeparate(): void
    {
        $install = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.1.sql');
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_event_series`', $install);
        self::assertStringContainsString('`series_id` int(11) unsigned NULL DEFAULT NULL', $install);
        self::assertStringContainsString('ADD COLUMN `series_id`', $update);
        self::assertStringContainsString("\$data['recurrence_type'] = 0;", $model);
        self::assertStringContainsString('createCustomEventSeries', $model);
        self::assertStringContainsString("'series_order' => \$index + 1", $model);
        self::assertStringContainsString('repairCustomSeriesAfterDelete', $model);
    }

    public function testFrontendAndBackendExposeTheSameCustomScheduleMode(): void
    {
        foreach (array(
            '/admin/models/forms/event.xml',
            '/site/models/forms/event.xml',
        ) as $file) {
            $xml = file_get_contents(JEM_TEST_ROOT . $file);
            self::assertStringContainsString('<option value="7">COM_JEM_CUSTOM_DATES</option>', $xml);
        }

        $javascript = file_get_contents(JEM_TEST_ROOT . '/media/js/recurrence.js');
        self::assertStringContainsString("if (\$select_value === '7')", $javascript);
        self::assertStringContainsString('custom_schedule_json', $javascript);
        self::assertStringNotContainsString('custom_schedule_seed_row', $javascript);
        self::assertStringContainsString("rows.push({event_id: 0, date: '', time: '', end_date: '', end_time: ''});", $javascript);
    }

    public function testSeriesBookingAcceptsRegularAndCustomSeries(): void
    {
        $siteModel = file_get_contents(JEM_TEST_ROOT . '/site/models/event.php');
        $adminModel = file_get_contents(JEM_TEST_ROOT . '/admin/models/attendee.php');
        $frontendController = file_get_contents(JEM_TEST_ROOT . '/site/controllers/attendees.php');

        self::assertStringContainsString("\$event->recurrence_type || !empty(\$event->series_id)", $siteModel);
        self::assertStringContainsString("\$event->recurrence_type || !empty(\$event->series_id)", $adminModel);
        self::assertStringContainsString("\$event->recurrence_type || !empty(\$event->series_id)", $frontendController);
        self::assertStringContainsString("a.series_id = ", $siteModel);
        self::assertStringContainsString('a.start_utc IS NOT NULL AND a.start_utc >=', $siteModel);
        self::assertStringContainsString('a.times IS NULL OR a.times >=', $siteModel);
        self::assertStringContainsString('a.start_utc IS NOT NULL AND a.start_utc >=', $adminModel);

        foreach (array(
            '/admin/views/attendee/tmpl/default.php',
            '/site/views/attendees/tmpl/addusers.php',
            '/site/views/attendees/tmpl/responsive/addusers.php',
            '/site/views/event/tmpl/default_regform.php',
            '/site/views/event/tmpl/responsive/default_regform.php',
        ) as $file) {
            self::assertStringContainsString('series_id', file_get_contents(JEM_TEST_ROOT . $file));
        }
    }

    public function testSeriesWritesShareOneOuterTransaction(): void
    {
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');

        self::assertStringContainsString('$seriesTransactionActive = false;', $model);
        self::assertStringContainsString('$seriesDb->transactionStart();', $model);
        self::assertStringContainsString('completeCustomSeriesSchedule($savedId, $customSchedule, $new || $customSeriesIsRoot)', $model);
        self::assertStringContainsString('createCustomEventSeries($savedId, $completeCustomSchedule, $cats, $backend, false)', $model);
        self::assertStringContainsString('synchroniseCustomSeriesSchedule($existingSeriesId, $savedId, $completeCustomSchedule, $cats, $backend, false)', $model);
        self::assertStringContainsString('getCustomSeriesSchedule((int) $item->series_id, (int) $item->id)', $model);
        self::assertStringContainsString('$seriesDb->transactionCommit();', $model);
        self::assertStringContainsString('$seriesDb->transactionRollback();', $model);
        self::assertStringContainsString(
            "if (\$manageTransaction) {\n                Factory::getApplication()->enqueueMessage",
            str_replace("\r\n", "\n", $model)
        );
    }

    public function testCustomSeriesRootPropagatesWhileChildrenRemainIndependent(): void
    {
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $adminView = file_get_contents(JEM_TEST_ROOT . '/admin/views/event/tmpl/edit.php');
        $siteView = file_get_contents(JEM_TEST_ROOT . '/site/views/editevent/tmpl/edit_customschedule.php');

        self::assertStringContainsString("->select(\$db->quoteName('root_event_id'))", $model);
        self::assertStringContainsString("\$customSeriesScope = \$customSeriesIsRoot ? 'all' : 'occurrence';", $model);
        self::assertStringContainsString('propagateCustomSeriesFields($existingSeriesId, $savedId, $data, $cats, $backend)', $model);
        self::assertStringContainsString("'introtext', 'fulltext'", $model);

        foreach (array($adminView, $siteView) as $view) {
            self::assertStringContainsString('COM_JEM_CUSTOM_SERIES_ROOT_NOTICE', $view);
            self::assertStringContainsString('COM_JEM_CUSTOM_SERIES_CHILD_NOTICE', $view);
            self::assertStringContainsString('type="hidden" name="custom_series_scope"', $view);
            self::assertStringNotContainsString('<select name="custom_series_scope"', $view);
        }
    }
}

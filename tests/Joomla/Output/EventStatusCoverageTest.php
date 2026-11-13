<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventStatusCoverageTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();
        require_once dirname(__DIR__, 3) . '/site/classes/output.class.php';
        Factory::getApplication()->loadLanguage(Language::getInstance('en-GB'));
        Factory::getApplication()->loadDocument();
        JemHelper::loadComponentLanguage();
        self::assertSame(realpath(dirname(__DIR__, 3) . '/site/classes/output.class.php'),
            realpath((new ReflectionClass(JemOutput::class))->getFileName()));
    }

    private function event(string $status = 'scheduled'): object
    {
        return (object) array('id' => 0, 'title' => 'Status test', 'dates' => '2099-01-01',
            'times' => '10:00:00', 'event_status' => $status, 'ticket_availability' => 'instock',
            'created' => '2026-11-12 12:00:00', 'regCount' => 0, 'registra' => 1,
            'maxplaces' => 100, 'reservedplaces' => 0, 'waitinglist' => 0);
    }

    public function testExceptionalStatesWinOverAvailabilityAndNew(): void
    {
        foreach (array('cancelled', 'postponed', 'rescheduled', 'moved_online') as $state) {
            $event = $this->event($state);
            $event->ticket_availability = 'soldout';
            JemOutput::prepareEventStatusItems(array($event), true, (object) array('module_status_ribbons' => 1), strtotime('2026-11-13 12:00:00'));
            self::assertSame($state, $event->module_event_status['status']);
            self::assertNotSame('', JemOutput::eventStatusLabel($event));
            self::assertNotSame('', JemOutput::moduleEventStatusBadge($event));
            self::assertSame(JemOutput::moduleEventStatusBadge($event), JemOutput::moduleEventStatusBadge($event));
        }
    }

    public function testAvailabilityAndNewUseTheExistingPriorityAndPeriod(): void
    {
        $settings = (object) array('module_status_ribbons' => 1, 'module_status_new_days' => 7);
        $now = strtotime('2026-11-13 12:00:00');
        foreach (array('preorder' => 'preorder', 'soldout' => 'soldout', 'waitinglist' => 'waitinglist', 'limited' => 'last_places', 'instock' => 'new') as $availability => $expected) {
            $event = $this->event();
            $event->ticket_availability = $availability;
            if ($availability === 'waitinglist') {
                $event->ticket_availability = 'instock';
                $event->regCount = 100;
                $event->waitinglist = 1;
            }
            if ($availability === 'limited') {
                $event->regCount = 95;
            }
            JemOutput::prepareEventStatusItems(array($event), true, $settings, $now);
            self::assertSame($expected, $event->module_event_status['status']);
        }
        foreach (array('2026-10-01 12:00:00', '2026-11-14 12:00:00') as $created) {
            $event = $this->event();
            $event->created = $created;
            JemOutput::prepareEventStatusItems(array($event), true, $settings, $now);
            self::assertNull($event->module_event_status);
        }
    }

    public function testDisabledIndicatorsDoNotLeakThroughLegacyListBadges(): void
    {
        foreach (array(false, true) as $enabled) {
            foreach (array(0, 1) as $global) {
                $event = $this->event('cancelled');
                JemOutput::prepareEventStatusItems(array($event), $enabled, (object) array('module_status_ribbons' => $global));
                self::assertSame($enabled && $global === 1, JemOutput::eventStateBadges($event) !== '');
                self::assertNotSame('', JemOutput::eventStatusNotice($event));
            }
        }
    }

    public function testNestedEventsKeepTheirOwnStatusAndCanBePreparedAgain(): void
    {
        $parent = $this->event('cancelled');
        $child = $this->event('postponed');
        $parent->child_events = array($child);
        $group = (object) array('title' => 'Category', 'events' => array($parent, $child));
        $settings = (object) array('module_status_ribbons' => 1);
        JemOutput::prepareEventStatusItems(array($group), true, $settings);
        self::assertFalse(isset($group->module_event_status));
        self::assertSame('cancelled', $parent->module_event_status['status']);
        self::assertSame('postponed', $child->module_event_status['status']);
        self::assertNotSame('', JemOutput::eventStateBadges($child));
        JemOutput::prepareEventStatusItems(array($group), false, $settings);
        self::assertSame('', JemOutput::eventStateBadges($child));
        JemOutput::prepareEventStatusItems(array($group), true, $settings);
        self::assertNotSame('', JemOutput::eventStateBadges($child));
    }

    public function testBadgeFallbackAndImageDoNotDuplicateIndicators(): void
    {
        $event = $this->event('rescheduled');
        JemOutput::prepareEventStatusItems(array($event), true);
        self::assertStringContainsString('jem-module-event-status-ribbon', JemOutput::eventStatusImage($event, '<img src="test.jpg" alt="">'));
        self::assertSame('', JemOutput::eventStatusFallbackBadge($event));
        JemOutput::prepareEventStatusItems(array($event), true);
        self::assertNotSame('', JemOutput::eventStatusFallbackBadge($event));
        self::assertSame('', JemOutput::eventStatusFallbackBadge($event));
    }

    public function testBasicLayoutsRetainStatusWithoutTitle(): void
    {
        $event = $this->event('cancelled');
        $event->link = '#';
        $event->dateinfo = '13/11/2026';
        $event->venue = $event->venueurl = $event->catname = $event->country = '';
        $event->featured = 0;
        $event->eventid = 0;
        $event->dateschema = '';
        JemOutput::prepareEventStatusItems(array($event), true);
        $list = array($event);
        $settings = JemHelper::config();
        $module = (object) array('id' => 0);
        $params = new Registry(array('showtitle' => 0, 'showvenue' => 0, 'showcategory' => 0));
        foreach (array('default', 'responsive', 'table', 'table-style', 'acymailing') as $layout) {
            ob_start();
            include dirname(__DIR__, 3) . '/modules/mod_jem/tmpl/' . $layout . '.php';
            $html = ob_get_clean();
            self::assertStringContainsString('jem-module-event-status-badge', $html, $layout);
            self::assertStringNotContainsString('Status test', strip_tags($html), $layout);
        }
    }

    public function testCalendarAndPdfLabelsRespectPreparedState(): void
    {
        require_once dirname(__DIR__, 3) . '/site/helpers/calendaragenda.php';
        $candidate = dirname(__DIR__, 3) . '/site/classes/pdfview.class.php';
        $imagePolicyPath = dirname(__DIR__, 3) . '/site/classes/imageprofilepolicy.class.php';
        $imagePolicy = is_file($imagePolicyPath) ? file_get_contents($imagePolicyPath) : '';
        if (str_contains($imagePolicy, "require_once __DIR__ . '/imageresourcepolicy.class.php'")) {
            // The installed image policy is already loaded by the Joomla helpers.
            // Use the deployed identical PDF source to avoid loading it twice.
            $deployed = JPATH_SITE . '/components/com_jem/classes/pdfview.class.php';
            self::assertSame(hash_file('sha256', $candidate), hash_file('sha256', $deployed));
            require_once $deployed;
        } else {
            require_once $candidate;
        }
        $event = $this->event('postponed');
        $event->slug = '';
        $event->categories = array();
        JemOutput::prepareEventStatusItems(array($event), true);
        $html = JemCalendarAgendaHelper::renderAgenda(array($event), '2099-01-01', '2099-01-01');
        self::assertStringContainsString('jem-event-state-badge--postponed', $html);
        $method = new ReflectionMethod(JemPdfView::class, 'buildPdfEventIndicators');
        self::assertStringContainsString(JemOutput::eventStatusLabel($event), $method->invoke(null, $event));
        JemOutput::prepareEventStatusItems(array($event), false);
        self::assertStringNotContainsString('jem-module-event-status-badge', JemCalendarAgendaHelper::renderAgenda(array($event), '2099-01-01', '2099-01-01'));
        self::assertSame('', $method->invoke(null, $event));
    }
}

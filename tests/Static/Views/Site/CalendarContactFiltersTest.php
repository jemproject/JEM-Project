<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CalendarContactFiltersTest extends TestCase
{
    private const MENU_XML_FILES = array(
        'site/views/calendar/tmpl/default.xml',
        'site/views/weekcal/tmpl/default.xml',
        'site/views/annualcalendar/tmpl/default.xml',
        'site/views/day/tmpl/default.xml',
        'site/views/day/tmpl/timeline.xml',
        'site/views/day/tmpl/timetable.xml',
        'site/views/category/tmpl/calendar.xml',
        'site/views/venue/tmpl/calendar.xml',
    );

    public function testEveryCalendarMenuDefinesRelatedOptionalContactFilters(): void
    {
        foreach (self::MENU_XML_FILES as $relativePath) {
            $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertNotFalse($xml, $relativePath);
            $categoryFields = $xml->xpath('//field[@name="calendar_contact_category"]');
            $contactFields = $xml->xpath('//field[@name="calendar_contacts"]');

            self::assertCount(1, $categoryFields, $relativePath);
            self::assertCount(1, $contactFields, $relativePath);
            self::assertSame('category', (string) $categoryFields[0]['type'], $relativePath);
            self::assertSame('com_contact', (string) $categoryFields[0]['extension'], $relativePath);
            self::assertSame('0', (string) $categoryFields[0]['default'], $relativePath);
            self::assertSame('modal_contact', (string) $contactFields[0]['type'], $relativePath);
            self::assertSame('calendar_contact_category', (string) $contactFields[0]['categoryfield'], $relativePath);

            $source = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);
            self::assertLessThan(
                strpos($source, 'name="calendar_contacts"'),
                strpos($source, 'name="calendar_contact_category"'),
                $relativePath
            );
        }
    }

    public function testCalendarModelsApplyIndependentContactFiltersWithoutDuplicatingEvents(): void
    {
        $eventsList = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');
        $day = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/day.php');

        self::assertStringContainsString('protected function applyMenuContactFilters($params)', $eventsList);
        self::assertStringContainsString("get('calendar_contact_category', 0)", $eventsList);
        self::assertStringContainsString("get('calendar_contacts', array())", $eventsList);
        self::assertStringContainsString("setState('filter.contact_category_id'", $eventsList);
        self::assertStringContainsString("setState('filter.contact_ids'", $eventsList);
        self::assertStringContainsString("getState('filter.contact_category_id'", $eventsList);
        self::assertStringContainsString("getState('filter.contact_ids'", $eventsList);
        self::assertStringContainsString("\$query->where('EXISTS (' . \$contactQuery . ')')", $eventsList);
        self::assertStringContainsString('FIND_IN_SET(', $eventsList);
        self::assertStringContainsString("quoteName('jem_contact_category.lft')", $eventsList);
        self::assertStringContainsString("quoteName('jem_contact_root.lft')", $eventsList);
        self::assertStringContainsString("quoteName('jem_contact_category.rgt')", $eventsList);
        self::assertStringContainsString("quoteName('jem_contact_root.rgt')", $eventsList);
        self::assertStringContainsString("\$query->where('1 = 0')", $eventsList);
        self::assertStringContainsString('$this->applyMenuContactFilters($params);', $day);
    }

    public function testContactSelectorTracksTheCategoryBranchAndClearsStaleContacts(): void
    {
        $field = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/fields/modal/contact.php');
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/contactelement.php');
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/contactelement/view.html.php');
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/contactelement/tmpl/default.php');

        self::assertStringContainsString("\$this->element['categoryfield']", $field);
        self::assertStringContainsString('contact_category_id=', $field);
        self::assertStringContainsString('categoryField.addEventListener("change"', $field);
        self::assertStringContainsString(".value = \"\";", $field);
        self::assertStringContainsString("getInt('contact_category_id', 0)", $model);
        self::assertStringContainsString("quoteName('cat.lft')", $model);
        self::assertStringContainsString("quoteName('cat_root.lft')", $model);
        self::assertStringContainsString("quoteName('cat.rgt')", $model);
        self::assertStringContainsString("quoteName('cat_root.rgt')", $model);
        self::assertStringContainsString('$this->contactCategoryId =', $view);
        self::assertStringContainsString('name="contact_category_id"', $template);
    }

    public function testCalendarRendersAReadOnlyAccessibleFilterContext(): void
    {
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        $directTemplates = array(
            'site/views/calendar/tmpl/default.php',
            'site/views/calendar/tmpl/responsive/default.php',
            'site/views/weekcal/tmpl/default.php',
            'site/views/weekcal/tmpl/responsive/default.php',
            'site/views/annualcalendar/tmpl/default.php',
            'site/views/day/tmpl/default.php',
            'site/views/day/tmpl/responsive/default.php',
            'site/views/day/tmpl/timeline.php',
            'site/views/day/tmpl/responsive/timeline.php',
            'site/views/day/tmpl/timetable.php',
            'site/views/category/tmpl/calendar.php',
            'site/views/category/tmpl/responsive/calendar.php',
            'site/views/venue/tmpl/calendar.php',
            'site/views/venue/tmpl/responsive/calendar.php',
        );

        self::assertStringContainsString('renderCalendarContactFilterContext($params = null)', $helper);
        self::assertStringContainsString('role="note"', $helper);
        self::assertStringContainsString('COM_JEM_CALENDAR_CONTACT_FILTER_CONTEXT', $helper);

        foreach ($directTemplates as $relativePath) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);
            self::assertStringContainsString(
                'JemHelper::renderCalendarContactFilterContext($this->params)',
                $source,
                $relativePath
            );
        }

        self::assertStringContainsString(
            "require JPATH_COMPONENT . '/views/annualcalendar/tmpl/default.php';",
            (string) file_get_contents(JEM_TEST_ROOT . '/site/views/annualcalendar/tmpl/responsive/default.php')
        );
        self::assertStringContainsString(
            "include dirname(__DIR__) . '/timetable.php';",
            (string) file_get_contents(JEM_TEST_ROOT . '/site/views/day/tmpl/responsive/timetable.php')
        );

        foreach (array('media/css/jem.css', 'media/css/jem-responsive.css') as $relativePath) {
            $css = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);
            self::assertStringContainsString('.jem-calendar-contact-filter-context', $css, $relativePath);
        }
    }

    public function testContactFilterLanguageKeysExistInAdministratorAndSiteEnglish(): void
    {
        foreach (array('admin/language/en-GB/com_jem.ini', 'site/language/en-GB/com_jem.ini') as $relativePath) {
            $language = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertStringContainsString('COM_JEM_CALENDAR_FILTER_CONTACT_CATEGORY_LABEL=', $language, $relativePath);
            self::assertStringContainsString('COM_JEM_CALENDAR_FILTER_CONTACTS_LABEL=', $language, $relativePath);
            self::assertStringContainsString('COM_JEM_CALENDAR_CONTACT_FILTER_CONTEXT=', $language, $relativePath);
            self::assertStringContainsString('COM_JEM_CALENDAR_FILTER_UNAVAILABLE=', $language, $relativePath);
        }
    }
}

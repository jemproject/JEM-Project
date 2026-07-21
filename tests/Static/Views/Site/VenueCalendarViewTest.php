<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VenueCalendarViewTest extends TestCase
{
    public function testVenueCalendarSelectorIsOptInForExistingMenuItems(): void
    {
        $xml = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venue/tmpl/calendar.xml');
        $systemLanguage = (string) file_get_contents(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.sys.ini');

        self::assertMatchesRegularExpression(
            '/name="show_venue_selector"[^>]*type="radio"[^>]*default="0"/s',
            $xml
        );
        self::assertStringContainsString('COM_JEM_VENUE_CALENDAR_SHOW_SELECTOR', $xml);
        self::assertStringContainsString('COM_JEM_VENUE_CALENDAR_SHOW_SELECTOR_DESC', $xml);
        self::assertMatchesRegularExpression(
            '/name="show_venue_title"[^>]*type="radio"[^>]*default="1"/s',
            $xml
        );
        self::assertStringContainsString('COM_JEM_VENUE_CALENDAR_SHOW_SELECTOR=', $systemLanguage);
        self::assertStringContainsString('COM_JEM_VENUE_CALENDAR_SHOW_SELECTOR_DESC=', $systemLanguage);
        self::assertStringContainsString('COM_JEM_VENUE_CALENDAR_SHOW_TITLE=', $systemLanguage);
        self::assertStringContainsString('COM_JEM_VENUE_CALENDAR_SHOW_TITLE_DESC=', $systemLanguage);
    }

    public function testVenueOptionsAreLimitedToPublishedAccessibleVenues(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/venue.php');

        self::assertStringContainsString('public function getVenueOptions()', $model);
        self::assertStringContainsString("quoteName('v.published') . ' = 1'", $model);
        self::assertStringContainsString("quoteName('v.access') . ' IN ('", $model);
        self::assertStringContainsString("implode(',', \$levels) ?: '0'", $model);
        self::assertStringContainsString("get('timeline_filter_venues', array())", $model);
        self::assertStringContainsString("quoteName('v.id') . ' IN (' . implode(',', \$allowedIds) . ')'", $model);
        self::assertStringContainsString("quoteName('v.venue') . ' ASC'", $model);
    }

    public function testVenueCalendarViewProvidesCurrentVenueAndSelectorOptions(): void
    {
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venue/view.html.php');

        self::assertStringContainsString("get('show_venue_selector', 0)", $view);
        self::assertStringContainsString("\$this->get('VenueOptions')", $view);
        self::assertStringContainsString('$this->venue         = $venue;', $view);
        self::assertStringContainsString('$this->venueOptions  = $venueOptions;', $view);
        self::assertStringContainsString('count($venueOptions) >= 8', $view);
        self::assertStringContainsString("->usePreset('choicesjs')", $view);
        self::assertStringContainsString("->useScript('webcomponent.field-fancy-select')", $view);
        self::assertStringNotContainsString('$pagetitle .= \' - \' . $venueTitle;', $view);
    }

    public function testBothVenueCalendarTemplatesShowContextAndPreserveNavigation(): void
    {
        foreach (array('calendar.php', 'responsive/calendar.php') as $template) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venue/tmpl/' . $template);

            self::assertStringContainsString('class="jem-venue-calendar-context"', $source);
            self::assertStringContainsString('$this->showVenueSelector && !$this->print', $source);
            self::assertMatchesRegularExpression(
                '/if \(\$this->params->get\(\'show_page_heading\', 1\)\).*?elseif \(\$this->params->get\(\'show_venue_title\', 1\)\)/s',
                $source
            );
            self::assertStringContainsString("Text::_('COM_JEM_VENUE') . ': ' . \$this->escape(\$this->venue->venue)", $source);
            self::assertStringContainsString("'onchange' => 'this.form.submit();'", $source);
            self::assertStringContainsString('class="visually-hidden"', $source);
            self::assertStringContainsString("'aria-label' => Text::_('COM_JEM_VENUE')", $source);
            self::assertStringContainsString('<joomla-field-fancy-select', $source);
            self::assertStringNotContainsString('jem-venue-calendar-name', $source);
            self::assertStringNotContainsString('type="submit"', $source);
            self::assertMatchesRegularExpression(
                '/<div class="buttons">.*?JemCalendarAgendaHelper::renderToggle\(\).*?<\/div>/s',
                $source
            );
            self::assertStringContainsString("'select.genericlist'", $source);
            self::assertStringContainsString('name="yearID"', $source);
            self::assertStringContainsString('name="monthID"', $source);
            self::assertStringContainsString('name="Itemid"', $source);
            self::assertStringContainsString('name="task"', $source);
        }
    }

    public function testVenueCalendarPdfUsesTheVenueNameAndChecksAccess(): void
    {
        $rawView = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venue/view.raw.php');

        self::assertStringContainsString("\$venue = \$this->get('Venue');", $rawView);
        self::assertStringContainsString('empty($venue->user_has_access_venue)', $rawView);
        self::assertStringContainsString("(string) \$venue->venue . ' - ' . \$year", $rawView);
        self::assertStringNotContainsString("Text::_('COM_JEM_VENUE') . ' ' . \$venueid", $rawView);
    }

    public function testVenueSelectorHidesItsAccessibleLabelAndKeepsSpaceAboveCalendar(): void
    {
        foreach (array('calendar.css', 'calendar-responsive.css') as $stylesheet) {
            $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/' . $stylesheet);

            self::assertStringContainsString('grid-template-columns: minmax(0, 22rem);', $css);
            self::assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $css);
            self::assertStringContainsString('margin: 0 0 1.25rem;', $css);
            self::assertStringContainsString('#jem .jem-venue-calendar-selector .form-select', $css);
        }
    }
}

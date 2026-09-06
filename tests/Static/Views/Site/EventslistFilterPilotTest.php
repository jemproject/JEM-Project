<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventslistFilterPilotTest extends TestCase
{
    public function testEventslistMenuHasADedicatedFilterTabAndSharedEditor(): void
    {
        $relativePath = 'site/views/eventslist/tmpl/default.xml';
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $relativePath);

        self::assertNotFalse($xml);
        self::assertCount(1, $xml->xpath('//fieldset[@name="filters"]'));
        self::assertCount(1, $xml->xpath('//fieldset[@name="filters"]/field[@name="event_filters"]'));
        self::assertSame(
            'eventfilters',
            (string) $xml->xpath('//field[@name="event_filters"]')[0]['type']
        );

        foreach (array(
            'event_tree_mode',
            'show_archived_events',
            'onlyfeatured',
            'tablefiltereventfrom',
            'tablefiltereventuntil',
            'categoryswitch',
            'categoryswitchcats',
            'includesubcategories',
            'showopendates',
        ) as $fieldName) {
            self::assertCount(
                1,
                $xml->xpath('//fieldset[@name="filters"]/field[@name="' . $fieldName . '"]'),
                $fieldName
            );
            self::assertCount(
                0,
                $xml->xpath('//fieldset[@name="basic"]/field[@name="' . $fieldName . '"]'),
                $fieldName
            );
        }

        foreach (array(
            'eventlist_show_venue',
            'eventlist_show_city',
            'eventlist_show_county',
            'eventlist_show_type',
            'eventlist_show_category',
            'eventlist_show_contact',
            'eventlist_contact_category_mode',
        ) as $fieldName) {
            self::assertCount(
                1,
                $xml->xpath('//fieldset[@name="basic"]/field[@name="' . $fieldName . '"]'),
                $fieldName
            );
        }
    }

    public function testEditorImplementsTheOrderedThreeStateContract(): void
    {
        $field = $this->read('admin/models/fields/eventfilters.php');
        $policy = $this->read('site/classes/eventfilterconfig.class.php');
        $script = $this->read('media/js/event-filters-admin.js');

        foreach (array('condition', 'active', 'visible', 'editable') as $column) {
            self::assertStringContainsString($column, $field);
            self::assertStringContainsString($column, $policy);
        }

        self::assertStringContainsString('draggable="true"', $field);
        self::assertStringContainsString("renderNestedField(\$contactForm, 'contact_category')", $field);
        self::assertStringContainsString("renderNestedField(\$contactForm, 'contacts')", $field);
        self::assertStringContainsString("\$field->renderField(array(", $field);
        self::assertStringNotContainsString("getField('contact_category')->input", $field);
        self::assertStringNotContainsString("getField('contacts')->input", $field);
        self::assertStringContainsString("addEventListener('dragstart'", $script);
        self::assertStringContainsString("addEventListener('dragover'", $script);
        self::assertStringContainsString('JSON.stringify({version: 2, rows: rows})', $script);
        self::assertStringContainsString('data-jem-event-filter-add', $field);
        self::assertStringContainsString('data-jem-event-filter-remove', $field);
        self::assertStringContainsString('jem-event-filter-actions', $field);
        self::assertStringContainsString('class="icon-trash"', $field);
        self::assertStringContainsString('data-filter-template=', $field);
        self::assertStringContainsString('MAX_CUSTOM_FIELDS = 10', $policy);
        self::assertStringContainsString('addCustomField(root)', $script);
        self::assertStringContainsString('customRows(root).length >= maximum', $script);
        self::assertStringContainsString('if ($editable)', $policy);
        self::assertStringContainsString('if (!$visible)', $policy);
    }

    public function testEventslistEnforcesFixedValuesAndOnlyReadsEditableRows(): void
    {
        $model = $this->read('site/models/eventslist.php');

        self::assertStringContainsString('applyConfiguredEventFilters($params, $itemid)', $model);
        self::assertStringContainsString("'filter_contact_category'", $model);
        self::assertStringContainsString("'filter_contacts'", $model);
        self::assertStringContainsString("if (!\$row['visible'] || !\$row['editable'])", $model);
        self::assertStringContainsString('return $configured;', $model);
        self::assertStringContainsString("input->exists(\$requestName)", $model);
        self::assertStringContainsString("'filter.contact_category.include_children'", $model);
        self::assertStringContainsString("quoteName('jem_contact.catid') . ' = ' . \$contactCategoryId", $model);
        self::assertStringContainsString("quoteName('jem_contact_category.lft')", $model);
        self::assertStringContainsString("\$query->where('EXISTS (' . \$contactQuery . ')')", $model);
        self::assertStringContainsString("input->get('filter_custom', array(), 'array')", $model);
        self::assertStringContainsString('JemEventFilterConfig::isCustomKey((string) $key)', $model);
        self::assertStringContainsString("'filter.enabled_custom_fields'", $model);
        self::assertStringContainsString('!isset($enabledCustomFields[$key])', $model);
        self::assertStringContainsString("quoteName('a.' . \$key)", $model);
        self::assertStringContainsString("escape(\$value, true)", $model);
    }

    public function testBothEventslistLayoutsRenderEditableOrReadOnlyFilters(): void
    {
        $view = $this->read('site/views/eventslist/view.html.php');
        $template = $this->read('site/common/views/tmpl/default_event_filters.php');

        self::assertStringContainsString('prepareEventFilters($model, $params)', $view);
        self::assertStringContainsString('getJoomlaContactCategoryOptions()', $view);
        self::assertStringContainsString('getJoomlaContactOptions($categoryId, $includeChildren)', $view);
        self::assertStringContainsString("if (\$row['editable'])", $template);
        self::assertStringContainsString("elseif (\$row['active']", $template);
        self::assertStringContainsString('name="filter_contact_category"', $template);
        self::assertStringContainsString('name="filter_contacts[]"', $template);
        self::assertStringContainsString('multiple size="5"', $template);
        self::assertStringContainsString('filter_contacts_hint', $template);
        self::assertStringContainsString("Text::_('COM_JEM_EVENT_FILTER_CONTACT_MULTIPLE_HINT')", $template);
        self::assertStringContainsString("Text::_('COM_JEM_EVENT_FILTER_ALL_CONTACTS')", $template);
        self::assertStringContainsString('class="form-control jem-event-filter-readonly"', $template);
        self::assertStringContainsString('readonly aria-readonly="true"', $template);
        self::assertStringContainsString('name="filter_custom[', $template);
        self::assertStringContainsString('data-jem-custom-filter', $template);
        self::assertStringNotContainsString('data-jem-event-filters-clear', $template);

        $script = $this->read('media/js/event-filters.js');
        self::assertStringContainsString('clearContactSelection(contacts)', $script);
        self::assertStringContainsString('normaliseContactSelection(contacts)', $script);

        foreach (array(
            'site/common/views/tmpl/default_events_table.php',
            'site/common/views/tmpl/responsive/default_jem_eventslist.php',
            'site/common/views/tmpl/responsive/default_jem_eventslist_small.php',
        ) as $relativePath) {
            self::assertStringContainsString(
                "loadTemplate('event_filters')",
                $this->read($relativePath),
                $relativePath
            );
        }

        $legacyLayout = $this->read('site/common/views/tmpl/default_events_table.php');
        self::assertGreaterThan(
            strpos($legacyLayout, 'id="jem_filter"'),
            strpos($legacyLayout, "loadTemplate('event_filters')")
        );
        self::assertSame(1, substr_count($legacyLayout, 'JSEARCH_FILTER_SUBMIT'));
        self::assertSame(1, substr_count($legacyLayout, 'JSEARCH_FILTER_CLEAR'));

        $responsiveLayout = $this->read('site/common/views/tmpl/responsive/default_jem_eventslist.php');
        self::assertStringContainsString('if (!$filterBelow && !empty($this->eventFilters', $responsiveLayout);
        self::assertStringContainsString('if ($filterBelow && !empty($this->eventFilters', $responsiveLayout);
        self::assertSame(2, substr_count($responsiveLayout, 'JSEARCH_FILTER_SUBMIT'));
        self::assertSame(2, substr_count($responsiveLayout, 'JSEARCH_FILTER_CLEAR'));

        $smallLayout = $this->read('site/common/views/tmpl/responsive/default_jem_eventslist_small.php');
        self::assertStringContainsString('if (!$filterBelow && !empty($this->eventFilters', $smallLayout);
        self::assertStringContainsString('if ($filterBelow && !empty($this->eventFilters', $smallLayout);
        self::assertSame(2, substr_count($smallLayout, 'JSEARCH_FILTER_SUBMIT'));
        self::assertSame(2, substr_count($smallLayout, 'JSEARCH_FILTER_CLEAR'));
    }

    public function testSelectorChangesSynchroniseAndContactContextUsesAccessChecks(): void
    {
        $field = $this->read('admin/models/fields/modal/contact.php');
        $helper = $this->read('site/helpers/helper.php');
        $frontendScript = $this->read('media/js/event-filters.js');

        self::assertStringContainsString('dispatchEvent(new Event("change", {bubbles: true}))', $field);
        self::assertStringContainsString('getJoomlaContactCategoryOptions()', $helper);
        self::assertStringContainsString('getJoomlaContactOptions(', $helper);
        self::assertStringContainsString("quoteName('contact.access')", $helper);
        self::assertStringContainsString("quoteName('contact_category.access')", $helper);
        self::assertStringNotContainsString("quoteName('contact.language')", $helper);
        self::assertStringNotContainsString("quoteName('contact_category.language')", $helper);
        self::assertStringContainsString("category.addEventListener('change'", $frontendScript);
        self::assertStringContainsString('clearContactSelection(contacts)', $frontendScript);
        self::assertStringContainsString("option.selected = option.value === ''", $frontendScript);
        self::assertStringContainsString("contacts.addEventListener('change'", $frontendScript);
        self::assertStringContainsString('normaliseContactSelection(contacts)', $frontendScript);
        self::assertStringContainsString("querySelectorAll('[data-jem-main-filters-clear]')", $frontendScript);
        self::assertStringContainsString("querySelectorAll('[data-jem-custom-filter]')", $frontendScript);
    }

    public function testEventItemInformationIsMenuControlledAcrossAllLayouts(): void
    {
        $view = $this->read('site/views/eventslist/view.html.php');
        $model = $this->read('site/models/eventslist.php');

        self::assertStringContainsString('prepareItemDisplay($params, $jemsettings)', $view);
        self::assertStringContainsString("'eventlist_show_venue'", $view);
        self::assertStringContainsString("'eventlist_show_city'", $view);
        self::assertStringContainsString("'eventlist_show_county'", $view);
        self::assertStringContainsString("'eventlist_show_type'", $view);
        self::assertStringContainsString("'eventlist_show_category'", $view);
        self::assertStringContainsString("'eventlist_show_contact'", $view);
        self::assertStringContainsString("setState('filter.show_contact_names'", $view);
        self::assertStringContainsString("'eventlist_contact_category_mode'", $view);
        self::assertStringContainsString("setState('filter.contact_category_mode'", $view);

        self::assertStringContainsString('GROUP_CONCAT(DISTINCT ', $model);
        self::assertStringContainsString("quoteName('jem_item_contact.access')", $model);
        self::assertStringContainsString("quoteName('jem_item_contact_category.access')", $model);
        self::assertStringContainsString("quoteName('contact_labels')", $model);
        self::assertStringContainsString("getState('filter.contact_category_mode', 1)", $model);
        self::assertStringContainsString("quoteName('jem_item_contact_category_path.lft')", $model);
        self::assertStringContainsString("quoteName('jem_item_contact_category_path.rgt')", $model);
        self::assertStringContainsString("quote(' / ')", $model);
        self::assertStringNotContainsString("quoteName('jem_contact.language')", $model);
        self::assertStringNotContainsString("quoteName('jem_contact_category.language')", $model);

        foreach (array(
            'site/common/views/tmpl/default_events_table.php',
            'site/common/views/tmpl/responsive/default_jem_eventslist.php',
            'site/common/views/tmpl/responsive/default_jem_eventslist_small.php',
            'site/common/views/tmpl/responsive/default_jem_eventslist_item.php',
        ) as $relativePath) {
            $layout = $this->read($relativePath);
            self::assertStringContainsString('$itemDisplay', $layout, $relativePath);
            self::assertStringContainsString("['contact']", $layout, $relativePath);
        }
    }

    public function testContactCategoryPrecedesContactNameAndDetailLookupEnforcesAccess(): void
    {
        $model = $this->read('site/models/event.php');

        self::assertStringContainsString("quoteName('con.published') . ' = 1'", $model);
        self::assertStringContainsString("quoteName('con.access') . ' IN ('", $model);
        self::assertStringContainsString("quoteName('cat.published') . ' = 1'", $model);
        self::assertStringContainsString("quoteName('cat.access') . ' IN ('", $model);

        foreach (array(
            'site/views/event/tmpl/default.php',
            'site/views/event/tmpl/responsive/default.php',
        ) as $relativePath) {
            $layout = $this->read($relativePath);
            self::assertStringContainsString('class="con-category"', $layout, $relativePath);
            self::assertStringContainsString("category_name ?: Text::_('COM_JEM_NO_CATEGORY')", $layout, $relativePath);
        }
    }

    public function testEnglishLanguageCatalogsContainTheSharedFilterKeys(): void
    {
        foreach (array(
            'admin/language/en-GB/com_jem.ini',
            'site/language/en-GB/com_jem.ini',
        ) as $relativePath) {
            $language = $this->read($relativePath);

            foreach (array(
                'COM_JEM_EVENT_FILTERS_TAB=',
                'COM_JEM_EVENT_FILTER_COLUMN_ACTIVE=',
                'COM_JEM_EVENT_FILTER_COLUMN_VISIBLE=',
                'COM_JEM_EVENT_FILTER_COLUMN_EDITABLE=',
                'COM_JEM_EVENT_FILTER_CONTACT_CATEGORY=',
                'COM_JEM_EVENT_FILTER_CONTACT=',
                'COM_JEM_EVENT_FILTER_CUSTOM_SELECT=',
                'COM_JEM_EVENT_FILTER_CUSTOM_ADD=',
                'COM_JEM_EVENT_FILTER_CUSTOM_REMOVE=',
                'COM_JEM_EVENT_FILTER_CONDITION_CONTAINS=',
                'COM_JEM_EVENT_FILTER_CONDITION_EXACT_VALUE=',
                'COM_JEM_EVENTSLIST_ITEM_INFORMATION=',
                'COM_JEM_EVENTSLIST_ITEM_SHOW_VENUE=',
                'COM_JEM_EVENTSLIST_ITEM_SHOW_CITY=',
                'COM_JEM_EVENTSLIST_ITEM_SHOW_COUNTY=',
                'COM_JEM_EVENTSLIST_ITEM_SHOW_TYPE=',
                'COM_JEM_EVENTSLIST_ITEM_SHOW_CATEGORY=',
                'COM_JEM_EVENTSLIST_ITEM_SHOW_CONTACT=',
                'COM_JEM_EVENTSLIST_ITEM_CONTACT_CATEGORY=',
                'COM_JEM_EVENTSLIST_ITEM_CONTACT_CATEGORY_DESC=',
                'COM_JEM_EVENTSLIST_ITEM_CONTACT_CATEGORY_HIDE=',
                'COM_JEM_EVENTSLIST_ITEM_CONTACT_CATEGORY_PARENT=',
                'COM_JEM_EVENTSLIST_ITEM_CONTACT_CATEGORY_PATH=',
                'COM_JEM_EVENTSLIST_ITEM_CONTACT=',
            ) as $key) {
                self::assertStringContainsString($key, $language, $relativePath . ': ' . $key);
            }
        }
    }

    private function read(string $relativePath): string
    {
        return (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);
    }
}

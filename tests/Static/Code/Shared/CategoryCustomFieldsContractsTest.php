<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CategoryCustomFieldsContractsTest extends TestCase
{
    public function testCategorySchemaAndPrereleaseRepairAreAdditive(): void
    {
        $install = $this->read('/admin/sql/install.mysql.utf8.sql');
        $update = $this->read('/admin/sql/updates/mysql/5.1.0.sql');
        $installer = $this->read('/script.php');

        self::assertStringContainsString('`custom_fields` mediumtext DEFAULT NULL', $install);
        self::assertStringContainsString('ADD COLUMN `custom_fields` MEDIUMTEXT NULL DEFAULT NULL', $update);
        self::assertStringContainsString('repair510CategoryCustomFieldsSchema()', $installer);
    }

    public function testEventSaveFiltersInactiveValuesBeforePersistence(): void
    {
        $eventModel = $this->read('/admin/models/event.php');
        $helper = $this->read('/site/classes/categorycustomfields.class.php');

        self::assertStringContainsString('JemCategoryCustomFields::filterEventData($data, $cats);', $eventModel);
        self::assertLessThan(
            strpos($eventModel, '$saved = parent::save($data);'),
            strpos($eventModel, 'JemCategoryCustomFields::filterEventData($data, $cats);')
        );
        self::assertStringContainsString("unset(\$data[\$fieldName]);", $helper);
        self::assertStringContainsString("unset(\$data['com_fields'][\$fieldName]);", $helper);
        self::assertStringContainsString('FieldsHelper::getFields(self::CONTEXT_EVENT', $helper);
        self::assertStringNotContainsString("insertObject('#__fields_values'", $helper);
        self::assertStringNotContainsString("updateObject('#__fields_values'", $helper);
    }

    public function testFrontendAndBackendFormsUseTheSameCategoryResolver(): void
    {
        $eventModel = $this->read('/admin/models/event.php');
        $frontendModel = $this->read('/site/models/editevent.php');
        $frontendController = $this->read('/site/controllers/event.php');
        $helper = $this->read('/site/classes/categorycustomfields.class.php');
        $script = $this->read('/media/js/categorycustomfields.js');

        self::assertStringContainsString('JemCategoryCustomFields::prepareEventForm($form, $data);', $eventModel);
        self::assertStringContainsString('$value->cats = $itemId > 0', $frontendModel);
        self::assertStringContainsString("parent::reload(\$key, \$urlVar);", $frontendController);
        self::assertStringContainsString("window.Joomla.submitform('event.reload', form);", $script);
        self::assertStringContainsString('Inactive stored values never enter the edit-form DOM.', $helper);
        self::assertStringContainsString('restoreMissingLegacyValues($form, $eventData, $activeLegacyNames);', $helper);
        self::assertStringNotContainsString('getConfiguredJoomlaFieldIds()', $helper);
        self::assertStringNotContainsString(
            "JemCustomFields::applyFormLabels(\$form, 'event', 'frontend_edit');",
            $frontendModel
        );
    }

    public function testVenueFormsSaveAndRenderOnlyVenueContextFields(): void
    {
        $venueModel = $this->read('/admin/models/venue.php');
        $helper = $this->read('/site/classes/categorycustomfields.class.php');
        $classicDetail = $this->read('/site/views/venue/tmpl/default.php');
        $responsiveDetail = $this->read('/site/views/venue/tmpl/responsive/default.php');

        self::assertStringContainsString('JemCategoryCustomFields::prepareVenueForm($form, $data);', $venueModel);
        self::assertStringContainsString('JemCategoryCustomFields::filterVenueData($data);', $venueModel);
        self::assertLessThan(
            strpos($venueModel, '$saved = parent::save($data);'),
            strpos($venueModel, 'JemCategoryCustomFields::filterVenueData($data);')
        );
        self::assertStringContainsString("FieldsHelper::getFields(self::CONTEXT_VENUE", $helper);
        self::assertStringContainsString('getJoomlaVenueDetailPresentation($this->venue)', $classicDetail);
        self::assertStringContainsString('getJoomlaVenueDetailPresentation(', $responsiveDetail);
    }

    public function testJoomlaFieldGroupsAreRenderedAsEditTabs(): void
    {
        $helper = $this->read('/site/classes/categorycustomfields.class.php');
        $templates = array(
            '/admin/views/event/tmpl/edit.php',
            '/admin/views/venue/tmpl/edit.php',
            '/site/views/editevent/tmpl/edit.php',
            '/site/views/editevent/tmpl/responsive/edit.php',
            '/site/views/editvenue/tmpl/edit.php',
            '/site/views/editvenue/tmpl/responsive/edit.php',
        );

        self::assertStringContainsString("getFieldsets('com_fields')", $helper);
        self::assertStringContainsString("HTMLHelper::_('uitab.addTab'", $helper);
        self::assertStringContainsString("'fields-' . \$groupId", $helper);
        self::assertStringContainsString("Text::_('COM_JEM_CUSTOMFIELDS')", $helper);
        self::assertStringNotContainsString("Text::_('JGLOBAL_FIELDS')", $helper);

        foreach ($templates as $template) {
            $contents = $this->read($template);
            self::assertStringContainsString(
                'JemCategoryCustomFields::renderJoomlaFormTabs(',
                $contents,
                $template
            );
        }

        self::assertStringNotContainsString(
            "getGroup('com_fields')",
            $this->read('/site/views/editevent/tmpl/edit_other.php')
        );
        self::assertStringNotContainsString(
            "getGroup('com_fields')",
            $this->read('/site/views/editevent/tmpl/responsive/edit_other.php')
        );
    }

    public function testCategoryEditorUsesTheGlobalJemFieldPresentation(): void
    {
        $view = $this->read('/admin/views/category/view.html.php');
        $edit = $this->read('/admin/views/category/tmpl/edit.php');
        $template = $this->read('/admin/views/category/tmpl/edit_customfields.php');
        $layout = $this->read('/admin/layouts/category/customfields.php');
        $language = $this->read('/admin/language/en-GB/com_jem.ini');

        self::assertStringContainsString('$this->featurePolicy = JemFeaturePolicy::current();', $view);
        self::assertStringContainsString('$this->featurePolicy->isAdvanced()', $view);
        self::assertStringContainsString('$this->featurePolicy->isAdvanced()', $edit);
        self::assertStringContainsString('COM_JEM_CATEGORY_CUSTOM_FIELDS="Custom fields"', $language);
        self::assertStringContainsString("JemCustomFields::getOrderedFields('event')", $view);
        self::assertStringContainsString("JemCustomFields::getFieldConfig('event', \$fieldName)", $view);
        self::assertStringContainsString("JemCustomFields::getLabel('event', \$fieldName", $view);
        self::assertStringContainsString("JemCustomFields::getDescription('event', \$fieldName", $view);
        self::assertStringContainsString("LayoutHelper::render('category.customfields'", $template);
        self::assertStringContainsString('$field->label', $layout);
        self::assertStringContainsString('$field->description', $layout);
        self::assertStringContainsString('data-jem-legacy-field-id', $layout);
        self::assertStringContainsString('data-jem-joomla-field-id', $layout);
        self::assertStringContainsString('data-jem-joomla-group-id', $layout);
        self::assertStringNotContainsString('type="radio"', $layout);
        self::assertStringNotContainsString('COM_JEM_CATEGORY_MANAGE_JOOMLA_CUSTOM_FIELDS', $layout);
        self::assertStringNotContainsString('option=com_fields&amp;view=fields', $layout);
    }

    public function testEventEditorOpensCustomFieldsAccordionByDefault(): void
    {
        $template = $this->read('/admin/views/event/tmpl/edit.php');

        self::assertStringContainsString('$customFieldsAccordionOpen = true;', $template);
        self::assertStringContainsString(
            'id="custom" class="accordion-collapse collapse show"',
            $template
        );
        self::assertStringContainsString(
            'class="accordion-collapse collapse<?php echo $customFieldsAccordionOpen',
            $template
        );
    }

    public function testFrontendLegacyFieldTabsUseTheCustomFieldsLabel(): void
    {
        $language = $this->read('/site/language/en-GB/com_jem.ini');

        self::assertStringContainsString('COM_JEM_EVENT_OTHER_TAB="Custom fields"', $language);
        self::assertStringContainsString('COM_JEM_EDITVENUE_OTHER_TAB="Custom fields"', $language);

        foreach (array(
            '/site/views/editevent/tmpl/edit.php',
            '/site/views/editevent/tmpl/responsive/edit.php',
            '/site/views/editvenue/tmpl/edit.php',
            '/site/views/editvenue/tmpl/responsive/edit.php',
        ) as $template) {
            self::assertStringContainsString('<!-- CUSTOM FIELDS TAB -->', $this->read($template));
        }
    }

    public function testPublicDetailsRenderThreeOrderedCustomFieldSections(): void
    {
        $helper = $this->read('/site/classes/categorycustomfields.class.php');
        $settings = $this->read('/admin/models/forms/settings.xml');
        $language = $this->read('/admin/language/en-GB/com_jem.ini');
        $style = $this->read('/media/css/jem.css');
        $responsiveStyle = $this->read('/media/css/jem-responsive.css');

        self::assertStringContainsString("'jem_joomla_groups'", $helper);
        self::assertStringContainsString('renderOrderedDetailSections', $helper);
        self::assertStringContainsString('renderOrderedDetailRows', $helper);
        self::assertStringContainsString('addDetailSeparator', $helper);
        self::assertStringContainsString('jem-custom-field-group-card', $helper);
        self::assertStringContainsString('renderJoomlaGroupedDetailRow', $helper);
        self::assertStringContainsString('jem-custom-field-group__field-label', $helper);
        self::assertStringContainsString(". \$escapedLabel . ':</span> '", $helper);
        self::assertStringContainsString('id="' . "' . \$groupDomId . '" . '-label"', $helper);
        self::assertStringContainsString('aria-labelledby="' . "' . \$groupDomId . '" . '-label"', $helper);
        self::assertStringNotContainsString('jem-custom-field-group__title', $helper);
        self::assertStringContainsString("\$value === ''", $helper);
        self::assertStringContainsString("\$group['rows'] === ''", $helper);
        self::assertStringContainsString('getJoomlaEventFieldGroupsById()', $helper);
        self::assertStringContainsString('getJoomlaVenueFieldGroupsById()', $helper);
        self::assertStringContainsString('name="event_custom_fields_order"', $settings);
        self::assertStringContainsString('name="venue_custom_fields_order"', $settings);
        self::assertSame(2, substr_count($settings, 'default="jem_joomla_groups"'));
        self::assertStringContainsString(
            'COM_JEM_CUSTOM_FIELDS_ORDER_JEM_JOOMLA_GROUPS="JEM fields, Joomla fields, Field Groups"',
            $language
        );
        self::assertStringContainsString('.jem-custom-field-group__list', $style);
        self::assertStringContainsString('.jem-custom-fields-start', $style);
        self::assertStringContainsString('width: fit-content', $style);
        self::assertStringContainsString('font: inherit', $style);
        self::assertStringContainsString('.jem-custom-field-group__field-label', $style);
        self::assertStringContainsString('.jem-custom-field-group__field-value', $style);
        self::assertStringContainsString('.jem-custom-field-group__list', $responsiveStyle);
        self::assertStringContainsString('width: fit-content', $responsiveStyle);
        self::assertStringContainsString('.jem-custom-field-group__field-label', $responsiveStyle);
        self::assertStringContainsString('.jem-custom-field-group__field-value', $responsiveStyle);
        self::assertStringContainsString('dl.jem-dl {', $responsiveStyle);

        foreach (array(
            '/site/views/event/tmpl/default.php',
            '/site/views/event/tmpl/responsive/default.php',
            '/site/views/venue/tmpl/default.php',
            '/site/views/venue/tmpl/responsive/default.php',
        ) as $template) {
            $contents = $this->read($template);
            self::assertStringContainsString('renderOrderedDetailSections(', $contents, $template);
            self::assertStringContainsString('renderOrderedDetailRows(', $contents, $template);
            self::assertStringContainsString('addDetailSeparator(', $contents, $template);
        }

        foreach (array(
            '/site/views/event/tmpl/default.php',
            '/site/views/event/tmpl/responsive/default.php',
        ) as $template) {
            $contents = $this->read($template);
            self::assertStringContainsString("(object) array('id' => (int) \$this->item->locid)", $contents, $template);
            self::assertStringContainsString("get('venue_custom_fields_order', 'jem_joomla_groups')", $contents, $template);
        }
    }

    public function testBackendItemEditorsKeepSimpleSelectsNearTheirContent(): void
    {
        $style = $this->read('/media/css/backend.css');

        self::assertStringContainsString('jem-edit-category-form', $this->read('/admin/views/category/tmpl/edit.php'));
        self::assertStringContainsString('jem-edit-type-form', $this->read('/admin/views/type/tmpl/edit.php'));
        self::assertStringContainsString('#event-form select:not([multiple])', $style);
        self::assertStringContainsString('#venue-form select:not([multiple])', $style);
        self::assertStringContainsString('.jem-edit-category-form select:not([multiple])', $style);
        self::assertStringContainsString('.jem-edit-type-form select:not([multiple])', $style);
        self::assertStringContainsString('padding-inline-end: calc(2.25rem + 5px)', $style);
        self::assertStringContainsString('width: fit-content !important', $style);
    }

    public function testJoomlaContextIsRegisteredWithoutReplacingTheLegacyDispatcher(): void
    {
        $helper = $this->read('/site/helpers/helper.php');
        $categoryHelper = $this->read('/site/helpers/category.php');
        $shim = $this->read('/admin/helpers/jem.php');
        $access = $this->read('/admin/access.xml');

        self::assertStringContainsString("'com_jem.event' => Text::_('COM_JEM_EVENTS')", $helper);
        self::assertStringContainsString("'com_jem.venue' => Text::_('COM_JEM_VENUES')", $helper);
        self::assertStringContainsString("array('event', 'venue')", $helper);
        self::assertStringContainsString('class JemEventCategories extends JemFieldsCategories', $categoryHelper);
        self::assertStringContainsString('class JemVenueCategories extends JemFieldsCategories', $categoryHelper);
        self::assertStringContainsString('implements CategoryInterface', $categoryHelper);
        self::assertStringContainsString("if (\$id === 'root')", $categoryHelper);
        self::assertStringContainsString('new CategoryNode(array(', $categoryHelper);
        self::assertStringContainsString('return $this->root;', $categoryHelper);
        self::assertStringContainsString('return null;', $categoryHelper);
        self::assertStringContainsString("require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';", $shim);
        self::assertStringContainsString('<section name="fieldgroup">', $access);
        self::assertStringContainsString('<section name="field">', $access);
        self::assertStringContainsString('<action name="core.edit.value"', $access);
        self::assertFileDoesNotExist(JEM_TEST_ROOT . '/site/services/provider.php');
    }

    public function testManifestExposesFieldsAndGroupsBetweenSeparators(): void
    {
        $manifest = $this->read('/jem.xml');
        $adminLanguage = $this->read('/admin/language/en-GB/com_jem.ini');
        $systemLanguage = $this->read('/admin/language/en-GB/com_jem.sys.ini');
        $before = strpos($manifest, 'alias="jem-fields-separator-before"');
        $fields = strpos($manifest, 'option=com_fields&amp;view=fields&amp;context=com_jem.event');
        $groups = strpos($manifest, 'option=com_fields&amp;view=groups&amp;context=com_jem.event');
        $after = strpos($manifest, 'alias="jem-fields-separator-after"');

        self::assertNotFalse($before);
        self::assertNotFalse($fields);
        self::assertNotFalse($groups);
        self::assertNotFalse($after);
        self::assertStringContainsString(
            '<menu type="separator" alias="jem-fields-separator-before">-</menu>',
            $manifest
        );
        self::assertStringContainsString(
            '<menu type="separator" alias="jem-fields-separator-after">-</menu>',
            $manifest
        );
        self::assertStringContainsString('COM_JEM="JEM"', $adminLanguage);
        self::assertStringContainsString('COM_JEM="JEM"', $systemLanguage);
        self::assertLessThan($fields, $before);
        self::assertLessThan($groups, $fields);
        self::assertLessThan($after, $groups);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(JEM_TEST_ROOT . $relativePath);

        self::assertNotFalse($contents, $relativePath);

        return $contents;
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventPricingCapacityViewTest extends TestCase
{
    public function testVenueEditorExposesResponsiveMultiSpaceProfilesTab(): void
    {
        $edit = $this->read('/admin/views/venue/tmpl/edit.php');
        $capacity = $this->read('/admin/views/venue/tmpl/edit_capacity.php');
        $form = $this->read('/admin/models/forms/venue.xml');
        $view = $this->read('/admin/views/venue/view.html.php');
        $css = $this->read('/media/css/backend.css');

        self::assertStringContainsString("loadTemplate('capacity')", $edit);
        self::assertStringContainsString('COM_JEM_VENUE_PROFILES_TAB', $edit);
        self::assertStringContainsString('COM_JEM_VENUE_PROFILES_TAB="Profiles"', $this->read('/admin/language/en-GB/com_jem.ini'));
        self::assertStringContainsString('capacity_configuration_json', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_ADD_SPACE', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_ADD_AREA', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_NO_PROFILES', $capacity);
        self::assertStringContainsString('data-action="create-profile"', $capacity);
        self::assertStringContainsString('data-role="profile-empty-state"', $capacity);
        self::assertStringContainsString('data-role="profile-configuration"', $capacity);
        self::assertStringContainsString("profileSubmittedInput.value = profileConfigured ? '1' : '0'", $capacity);
        self::assertStringContainsString("profileCapacityInput.value = String(integer(venueCapacityInput ? venueCapacityInput.value : 0))", $capacity);
        self::assertStringContainsString('ToolbarHelper::inlinehelp()', $view);
        self::assertStringContainsString(
            'https://www.joomlaeventmanager.net/documentation/backend/venues/add-venue',
            $view
        );
        self::assertStringContainsString('jem-venue-address-card', $edit);
        self::assertStringContainsString("Text::_('COM_JEM_ADDRESS')", $edit);
        self::assertLessThan(
            strpos($edit, 'jem-venue-address-card'),
            strpos($edit, "renderfield('level')")
        );
        self::assertLessThan(
            strpos($edit, "renderfield('capacity')"),
            strpos($edit, "renderfield('level')")
        );
        self::assertLessThan(
            strpos($edit, "renderfield('timezone')"),
            strpos($edit, "renderfield('capacity')")
        );
        self::assertLessThan(
            strpos($edit, "renderfield('level')"),
            strpos($edit, "renderfield('alias')")
        );
        self::assertMatchesRegularExpression(
            '/jem-venue-address-card[\s\S]+renderfield\(\'street\'\)[\s\S]+renderfield\(\'country\'\)/',
            $edit
        );
        self::assertStringContainsString('jem-capacity-section-space', $capacity);
        self::assertStringContainsString('jem-capacity-section-layout', $capacity);
        self::assertStringContainsString('jem-capacity-section-areas', $capacity);
        self::assertMatchesRegularExpression(
            '/jem-capacity-section-layout[\s\S]+jem-capacity-section-areas[\s\S]+<\/div>\s*<\/div>\s*<\/div>\s*<\/article>/',
            $capacity
        );
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_PROFILE_DEFAULT', $capacity);
        self::assertStringContainsString('jem-capacity-profile-selector', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_ADD_PROFILE', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_SET_DEFAULT', $capacity);
        self::assertMatchesRegularExpression(
            '/jem-capacity-venue-toolbar.*jem-capacity-profile-actions.*jem-capacity-profile-card/s',
            $capacity
        );
        self::assertStringContainsString('data-action="add-profile"', $capacity);
        self::assertStringContainsString('data-action="set-default"', $capacity);
        self::assertStringContainsString('data-action="duplicate-profile"', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_PROFILE_OPTION_SUMMARY', $capacity);
        self::assertStringContainsString('data-action="archive-profile"', $capacity);
        self::assertStringContainsString('data-role="profile-title"', $capacity);
        self::assertStringContainsString('data-role="profile-option"', $capacity);
        self::assertStringContainsString('jem-capacity-profile-revision', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_PROFILE_REVISION_COMPACT', $capacity);
        self::assertStringContainsString("getLabel('capacity_profile_name')", $capacity);
        self::assertStringContainsString("getInput('capacity_profile_name')", $capacity);
        self::assertStringNotContainsString("renderField('capacity')", $capacity);
        self::assertStringContainsString("getLabel('capacity_profile_capacity')", $capacity);
        self::assertStringContainsString("getInput('capacity_profile_capacity')", $capacity);
        self::assertStringContainsString('class="jem-capacity-profile-capacity"', $capacity);
        self::assertStringContainsString("renderfield('capacity')", $edit);
        self::assertStringNotContainsString("renderField('capacity_profile_revision')", $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_LAYOUT_CAPACITY', $capacity);
        self::assertStringContainsString("summary.classList.toggle('is-over-capacity', layoutsExceedProfile)", $capacity);
        self::assertStringContainsString('profileCapacityInput.setCustomValidity(validationMessage)', $capacity);
        self::assertStringContainsString('field.reportValidity()', $edit);
        self::assertStringContainsString('revealInvalidVenueCapacityField', $edit);
        self::assertStringContainsString("'.jem-venue-capacity-editor input:invalid, '", $edit);
        self::assertStringContainsString("bootstrap.Tab.getOrCreateInstance(tabTrigger).show()", $edit);
        self::assertStringContainsString('class="col-md-8"', $edit);
        self::assertStringContainsString('class="col-md-4"', $edit);
        self::assertStringContainsString('data-role="configuration-overview"', $edit);
        self::assertStringContainsString('class="jem-venue-image-alt"', $edit);
        self::assertStringContainsString('#image-event .jem-venue-image-control .input-group', $edit);
        self::assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $edit);
        self::assertStringContainsString('img.venue-image[src$="blank.webp"]', $edit);
        self::assertStringContainsString('display: none;', $edit);
        self::assertStringNotContainsString('<aside class="card mt-3 jem-capacity-overview"', $capacity);
        self::assertStringContainsString(
            'document.querySelector(\'[data-role="configuration-overview"]\')',
            $capacity
        );
        self::assertLessThan(
            strpos($edit, 'data-role="configuration-overview"'),
            strpos($edit, 'id="venue-geodata"')
        );
        foreach (array('space_color', 'layout_color', 'data-area-field="color"') as $colourControl) {
            self::assertStringContainsString($colourControl, $capacity);
        }
        self::assertMatchesRegularExpression(
            '/data-field="space_name"[^>]+required[^>]+aria-required="true"/',
            $capacity
        );
        self::assertMatchesRegularExpression(
            '/data-field="layout_name"[^>]+required[^>]+aria-required="true"/',
            $capacity
        );
        self::assertStringContainsString("node.style.setProperty('--jem-capacity-node-color'", $capacity);
        self::assertStringContainsString("document.getElementById('jform_venue')", $capacity);
        self::assertStringContainsString("venueNameInput.addEventListener('input', sync)", $capacity);
        self::assertMatchesRegularExpression(
            '/data-role="profile-capacity">0<\/strong>\s*<span>\/<\/span>\s*<strong data-role="venue-capacity">0/',
            $capacity
        );
        self::assertDoesNotMatchRegularExpression('/<select[^>]+jem-capacity-profile-selector[^>]+disabled>/', $capacity);
        self::assertStringContainsString('profileSelector.addEventListener', $capacity);
        self::assertMatchesRegularExpression('/<input type="hidden" data-field="space_code">/', $capacity);
        self::assertStringNotContainsString('COM_JEM_VENUE_CAPACITY_SPACE_CODE_DESC', $capacity);
        self::assertStringContainsString('data-action="remove-space-image"', $capacity);
        self::assertStringContainsString('data-action="remove-layout-image"', $capacity);
        self::assertMatchesRegularExpression('/data-field="layout_code"[^>]+readonly/', $capacity);
        self::assertStringContainsString("\$space['layout_code'] = (string) \$ownedSpace['layout_code'];", $this->read('/admin/classes/venuecapacity.class.php'));
        self::assertStringNotContainsString('type="checkbox" class="form-check-input" value="1" data-field="space_image_remove"', $capacity);
        self::assertStringContainsString("spaces[spaceIndex].space_image_alt = '';", $capacity);
        self::assertStringContainsString("spaces[spaceIndex].layout_image_alt = '';", $capacity);
        self::assertStringContainsString('hide-aware-inline-help d-none', $capacity);
        self::assertStringContainsString('connectInlineHelp', $capacity);
        self::assertStringContainsString('applyInlineHelpState', $capacity);
        self::assertStringContainsString('event.target.matches(\'[data-field="space_name"]\')', $capacity);
        self::assertMatchesRegularExpression(
            '/name="capacity_profile_name"[\s\S]+required="true"[\s\S]+label="COM_JEM_VENUE_CAPACITY_PROFILE_NAME"/',
            $form
        );
        self::assertStringNotContainsString('name="capacity_profile_name" type="text"\n            readonly="true"', $form);
        self::assertStringContainsString('name="capacity_configuration_submitted" type="hidden" default="0"', $form);
        self::assertStringNotContainsString('JemVenueCapacityService::ensureDefaultProfile((int) $pk);', $this->read('/admin/models/venue.php'));
        self::assertStringContainsString('saveProfileConfiguration', $this->read('/admin/classes/venuecapacity.class.php'));
        self::assertStringContainsString('.jem-venue-capacity-editor', $css);
        self::assertStringContainsString('.jem-venue-location-card', $css);
        self::assertStringContainsString('.jem-capacity-space-card > .jem-capacity-card-header', $css);
        self::assertStringContainsString('.jem-capacity-section-areas', $css);
        self::assertStringContainsString('.jem-capacity-profile-heading', $css);
        self::assertStringContainsString('.jem-capacity-profile-actions', $css);
        self::assertStringContainsString('.jem-capacity-profile-details', $css);
        self::assertStringContainsString('.jem-capacity-profile-capacity > label', $css);
        self::assertStringContainsString('max-width: var(--jem-capacity-number-control-width)', $css);
        self::assertStringContainsString('.jem-capacity-intro', $css);
        self::assertStringContainsString('.jem-capacity-empty-state', $css);
        self::assertStringContainsString('[data-role="profile-configuration"][hidden]', $css);
        self::assertStringContainsString('.jem-capacity-profile-revision', $css);
        self::assertStringContainsString('.jem-capacity-profile-summary.is-over-capacity', $css);
        self::assertStringContainsString('.jem-capacity-overview-node', $css);
        self::assertStringContainsString('.jem-capacity-color-field', $css);
        self::assertStringContainsString('.jem-capacity-profile-name', $css);
        self::assertStringContainsString('--jem-capacity-text-control-width: 23rem', $css);
        self::assertStringContainsString('--jem-capacity-number-control-width: 8rem', $css);
        self::assertStringContainsString('justify-content: center', $css);
        self::assertStringContainsString('.jem-venue-capacity-editor .hide-aware-inline-help', $css);
        self::assertStringContainsString('[data-bs-theme="dark"] .jem-capacity-space-card', $css);
        self::assertStringContainsString('@media (max-width: 767.98px)', $css);
    }

    public function testEventEditorExposesNonCommercialVenueCapacityWorkflow(): void
    {
        $edit = $this->read('/admin/views/event/tmpl/edit.php');
        $capacity = $this->read('/admin/views/event/tmpl/edit_capacity.php');
        $form = $this->read('/admin/models/forms/event.xml');

        self::assertStringContainsString("loadTemplate('capacity')", $edit);
        self::assertStringContainsString('COM_JEM_EVENT_VENUE_CAPACITY_TAB', $edit);
        self::assertStringNotContainsString("loadTemplate('pricing')", $edit);
        self::assertStringContainsString('jem-event-pricing-readiness', $capacity);
        self::assertStringContainsString('jem-event-venue-configuration-select', $capacity);
        self::assertStringContainsString('configuration_custom_required', $capacity);
        self::assertStringContainsString('event.venueConfigurations', $capacity);
        self::assertStringContainsString('loadVenueConfigurations', $capacity);
        self::assertStringContainsString('jform_locid_id', $capacity);
        self::assertStringContainsString('jform_venue_allocation_mode', $capacity);
        self::assertStringContainsString('jform_capacity_mode', $capacity);
        self::assertStringNotContainsString('filterTaxRates', $capacity);
        self::assertStringNotContainsString('data-country-code', $capacity);
        self::assertStringContainsString('jemRevealInvalidEventFields', $edit);
        self::assertStringContainsString('[aria-invalid="true"]', $edit);
        self::assertStringContainsString('bootstrap.Tab.getOrCreateInstance(tabTrigger).show()', $edit);
        self::assertStringContainsString('bootstrap.Collapse.getOrCreateInstance(collapse, {toggle: false}).show()', $edit);
        self::assertStringContainsString('COM_JEM_EVENT_REQUIRED_FIELDS', $edit);
        self::assertStringContainsString(
            'COM_JEM_EVENT_REQUIRED_FIELDS="Please complete the following required field(s): %s."',
            $this->read('/admin/language/en-GB/com_jem.ini')
        );

        foreach (array('venue_allocation_mode', 'capacity_mode', 'pricing_mode', 'currency', 'capacity_pools', 'event_prices', 'venue_snapshot', 'venue_configuration_key', 'venue_assignment_ids') as $field) {
            self::assertStringContainsString('name="' . $field . '"', $form);
        }
        self::assertStringContainsString('<option value="single">', $form);
        self::assertStringContainsString('<option value="multiple">', $form);
        self::assertStringContainsString('name="access_level_id"', $form);
        self::assertStringContainsString('name="user_group_id"', $form);
        self::assertStringContainsString('default="1"', $form);
        self::assertStringContainsString('required="true"', $form);
        self::assertDoesNotMatchRegularExpression(
            '/<field\s+name="tax_rate_id"[^>]*\srequired="true"/',
            $form
        );
    }

    private function read(string $path): string
    {
        $file = JEM_TEST_ROOT . $path;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }
}

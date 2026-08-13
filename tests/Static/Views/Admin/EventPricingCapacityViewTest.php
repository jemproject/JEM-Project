<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventPricingCapacityViewTest extends TestCase
{
    public function testVenueEditorExposesResponsiveMultiSpaceCapacityTab(): void
    {
        $edit = $this->read('/admin/views/venue/tmpl/edit.php');
        $capacity = $this->read('/admin/views/venue/tmpl/edit_capacity.php');
        $form = $this->read('/admin/models/forms/venue.xml');
        $view = $this->read('/admin/views/venue/view.html.php');
        $css = $this->read('/media/css/backend.css');
        $responsiveCss = $this->read('/media/css/backend-responsive.css');

        self::assertStringContainsString("loadTemplate('capacity')", $edit);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_TAB', $edit);
        self::assertStringContainsString('capacity_configuration_json', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_ADD_SPACE', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_ADD_AREA', $capacity);
        self::assertStringContainsString('ToolbarHelper::inlinehelp()', $view);
        self::assertStringContainsString(
            'https://www.joomlaeventmanager.net/documentation/backend/venues/add-venue',
            $view
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
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_PROFILE_FUTURE_DESC', $capacity);
        self::assertStringContainsString('data-role="profile-title"', $capacity);
        self::assertStringContainsString('data-role="profile-option"', $capacity);
        self::assertStringContainsString('jem-capacity-profile-revision', $capacity);
        self::assertStringContainsString("renderField('capacity_profile_name')", $capacity);
        self::assertStringContainsString("renderField('capacity')", $capacity);
        self::assertStringContainsString("renderField('capacity_profile_capacity')", $capacity);
        self::assertStringContainsString('class="jem-capacity-profile-capacity"', $capacity);
        self::assertLessThan(
            strpos($capacity, "renderField('capacity_profile_capacity')"),
            strpos($capacity, "renderField('capacity')")
        );
        self::assertStringNotContainsString("renderfield('capacity')", $edit);
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
        self::assertMatchesRegularExpression('/<select[^>]+jem-capacity-profile-selector[^>]+disabled>/', $capacity);
        self::assertMatchesRegularExpression('/<button[^>]+btn btn-outline-primary[^>]+disabled>/', $capacity);
        self::assertStringContainsString('hide-aware-inline-help d-none', $capacity);
        self::assertStringContainsString('connectInlineHelp', $capacity);
        self::assertStringContainsString('applyInlineHelpState', $capacity);
        self::assertStringContainsString('event.target.matches(\'[data-field="space_name"]\')', $capacity);
        self::assertMatchesRegularExpression(
            '/name="capacity_profile_name"[\s\S]+required="true"[\s\S]+label="COM_JEM_VENUE_CAPACITY_PROFILE_NAME"/',
            $form
        );
        self::assertStringNotContainsString('name="capacity_profile_name" type="text"\n            readonly="true"', $form);
        foreach (array($css, $responsiveCss) as $backendCss) {
            self::assertStringContainsString('.jem-venue-capacity-editor', $backendCss);
            self::assertStringContainsString('.jem-capacity-space-card > .jem-capacity-card-header', $backendCss);
            self::assertStringContainsString('.jem-capacity-section-areas', $backendCss);
            self::assertStringContainsString('.jem-capacity-profile-heading', $backendCss);
            self::assertStringContainsString('.jem-capacity-profile-actions', $backendCss);
            self::assertStringContainsString('.jem-capacity-profile-details', $backendCss);
            self::assertStringContainsString('.jem-capacity-profile-capacity > .control-group', $backendCss);
            self::assertStringContainsString('max-width: 8rem', $backendCss);
            self::assertStringContainsString('.jem-capacity-intro', $backendCss);
            self::assertStringContainsString('.jem-capacity-profile-revision', $backendCss);
            self::assertStringContainsString('.jem-capacity-profile-summary.is-over-capacity', $backendCss);
            self::assertStringContainsString('.jem-capacity-overview-node', $backendCss);
            self::assertStringContainsString('.jem-capacity-color-field', $backendCss);
            self::assertStringContainsString('grid-template-columns: minmax(14rem, 1fr) auto', $backendCss);
            self::assertStringContainsString('.jem-venue-capacity-editor .hide-aware-inline-help', $backendCss);
            self::assertStringContainsString('[data-bs-theme="dark"] .jem-capacity-space-card', $backendCss);
            self::assertStringContainsString('@media (max-width: 767.98px)', $backendCss);
        }
    }

    public function testEventEditorExposesPricingCapacityWorkflowAndCountryTaxFilter(): void
    {
        $edit = $this->read('/admin/views/event/tmpl/edit.php');
        $pricing = $this->read('/admin/views/event/tmpl/edit_pricing.php');
        $form = $this->read('/admin/models/forms/event.xml');

        self::assertStringContainsString("loadTemplate('pricing')", $edit);
        self::assertStringContainsString('COM_JEM_EVENT_PRICING_CAPACITY_TAB', $edit);
        self::assertStringContainsString('jem-event-pricing-readiness', $pricing);
        self::assertStringContainsString('filterTaxRates', $pricing);
        self::assertStringContainsString('data-country-code', $pricing);
        self::assertStringContainsString('copyRow', $pricing);
        self::assertStringContainsString('updatePreviews', $pricing);
        self::assertStringContainsString('populateCapacityOptions', $pricing);
        self::assertStringContainsString("'source:' + pool.code", $pricing);
        self::assertStringContainsString('BigInt(', $pricing);
        self::assertStringContainsString("baseCode + '-copy-' + suffix", $pricing);
        self::assertStringContainsString('jem-price-advanced-toggle', $pricing);

        foreach (array('pricing_mode', 'currency', 'capacity_pools', 'event_prices', 'venue_snapshot') as $field) {
            self::assertStringContainsString('name="' . $field . '"', $form);
        }
        self::assertStringContainsString('<option value="single">', $form);
        self::assertStringContainsString('<option value="multiple">', $form);
        self::assertStringContainsString('name="access_level_id"', $form);
        self::assertStringContainsString('name="user_group_id"', $form);
        self::assertStringContainsString('default="1"', $form);
        self::assertStringContainsString('required="true"', $form);
    }

    private function read(string $path): string
    {
        $file = JEM_TEST_ROOT . $path;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }
}

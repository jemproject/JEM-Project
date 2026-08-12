<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventPricingCapacityViewTest extends TestCase
{
    public function testVenueEditorExposesResponsiveMultiSpaceCapacityTab(): void
    {
        $edit = $this->read('/admin/views/venue/tmpl/edit.php');
        $capacity = $this->read('/admin/views/venue/tmpl/edit_capacity.php');
        $css = $this->read('/media/css/backend.css');
        $responsiveCss = $this->read('/media/css/backend-responsive.css');

        self::assertStringContainsString("loadTemplate('capacity')", $edit);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_TAB', $edit);
        self::assertStringContainsString('capacity_configuration_json', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_ADD_SPACE', $capacity);
        self::assertStringContainsString('COM_JEM_VENUE_CAPACITY_ADD_AREA', $capacity);
        self::assertStringContainsString('jem-capacity-section-space', $capacity);
        self::assertStringContainsString('jem-capacity-section-layout', $capacity);
        self::assertStringContainsString('jem-capacity-section-areas', $capacity);
        foreach (array($css, $responsiveCss) as $backendCss) {
            self::assertStringContainsString('.jem-venue-capacity-editor', $backendCss);
            self::assertStringContainsString('.jem-capacity-space-card > .jem-capacity-card-header', $backendCss);
            self::assertStringContainsString('.jem-capacity-section-areas', $backendCss);
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

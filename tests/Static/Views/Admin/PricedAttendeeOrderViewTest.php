<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PricedAttendeeOrderViewTest extends TestCase
{
    public function testManualAttendeeEditorUsesAdmissionOrdersForPricedEvents(): void
    {
        $template = $this->read('/admin/views/attendee/tmpl/default.php');
        $model = $this->read('/admin/models/attendee.php');
        $controller = $this->read('/admin/controllers/attendee.php');
        $view = $this->read('/admin/views/attendee/view.html.php');

        self::assertStringContainsString('attendee.pricingOptions', $template);
        self::assertStringContainsString("name = 'admissions['", str_replace('input.name', 'name', $template));
        self::assertStringContainsString('jem-admission-options', $template);
        self::assertStringContainsString('COM_JEM_PRICED_REGISTRATION_SELECTION_REQUIRED', $template);
        self::assertStringContainsString('JemRegistrationTransition::logicalStatus($this->row)', $template);
        self::assertStringContainsString("if (!\$isPriced)", $template);
        self::assertStringContainsString('new JemPricedRegistrationService($db)', $model);
        self::assertStringContainsString("'requestedStatus' => (int) \$status", $model);
        self::assertStringContainsString("\$data['places'] = 0", $model);
        self::assertStringContainsString("Session::checkToken('get')", $controller);
        self::assertStringContainsString('getPricingData($eventId, $userId, $registrationId)', $controller);
        self::assertStringContainsString('empty($this->pricing->is_priced)', $view);
        self::assertStringContainsString('commerceReadOnly', $view);
        self::assertStringContainsString('FEATURE_PRICING', $view);
        self::assertStringContainsString('COM_JEM_PRICED_REGISTRATION_COMMERCE_READ_ONLY', $model);
        self::assertStringContainsString('FEATURE_PRICING', $controller);
    }

    public function testRegisteredUsersListShowsOrdersAndPreventsLegacyPricedTransitions(): void
    {
        $template = $this->read('/admin/views/attendees/tmpl/default.php');
        $print = $this->read('/admin/views/attendees/tmpl/print.php');
        $view = $this->read('/admin/views/attendees/view.html.php');
        $model = $this->read('/admin/models/attendees.php');
        $itemModel = $this->read('/admin/models/attendee.php');

        foreach (array($template, $print) as $source) {
            self::assertStringContainsString('COM_JEM_PRICED_REGISTRATION_ORDER', $source);
            self::assertStringContainsString('commercialBreakdowns', $source);
        }
        self::assertStringContainsString('line_gross', $template);
        self::assertStringContainsString('poolAvailability', $template);
        self::assertStringContainsString("if (!\$this->isPriced)", $view);
        self::assertStringContainsString("\$this->canForcePromotion = !\$this->isPriced", $view);
        self::assertStringContainsString('getCommercialBreakdowns', $model);
        self::assertStringContainsString('getPoolAvailability', $model);
        self::assertStringContainsString("Text::_('COM_JEM_PRICED_REGISTRATION_SELECTION_REQUIRED')", $itemModel);
        self::assertStringContainsString('commerceReadOnly', $view);
        self::assertStringContainsString('assertCommerceMutationAllowedForEvent', $this->read('/admin/controllers/attendees.php'));
    }

    public function testDeferredPricedRegistrationsAreReadOnlyOnTheFrontend(): void
    {
        $view = $this->read('/site/views/event/view.html.php');
        $model = $this->read('/site/models/event.php');
        $classic = $this->read('/site/views/event/tmpl/default_attendees.php');
        $responsive = $this->read('/site/views/event/tmpl/responsive/default_attendees.php');

        self::assertStringContainsString('FEATURE_PRICING', $view);
        self::assertStringContainsString('$this->showRegistrationAction = false', $view);
        self::assertStringContainsString('COM_JEM_PRICED_REGISTRATION_COMMERCE_READ_ONLY', $model);
        self::assertStringContainsString('COM_JEM_PRICED_REGISTRATION_COMMERCE_READ_ONLY', $classic);
        self::assertStringContainsString('COM_JEM_PRICED_REGISTRATION_COMMERCE_READ_ONLY', $responsive);
    }

    private function read(string $path): string
    {
        $file = JEM_TEST_ROOT . $path;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }
}

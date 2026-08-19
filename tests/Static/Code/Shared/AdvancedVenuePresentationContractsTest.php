<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdvancedVenuePresentationContractsTest extends TestCase
{
    public function testWebProgrammePdfAndCalendarExposeTheSavedVenueAllocation(): void
    {
        $factory = $this->read('/site/factory.php');
        $view = $this->read('/site/views/event/view.html.php');
        $classic = $this->read('/site/views/event/tmpl/default.php');
        $responsive = $this->read('/site/views/event/tmpl/responsive/default.php');
        $programme = $this->read('/site/common/hierarchy/event_programme.php');
        $pdf = $this->read('/site/views/event/view.raw.php');
        $ics = $this->read('/site/helpers/helper.php');

        self::assertStringContainsString('classes/venuesnapshot.class.php', $factory);
        self::assertStringContainsString('JemVenueSnapshot::lines($item)', $view);
        self::assertStringContainsString("loadTemplate('venueconfiguration')", $classic);
        self::assertStringContainsString("loadTemplate('venueconfiguration')", $responsive);
        self::assertStringContainsString('JemVenueSnapshot::summary($programmeItem)', $programme);
        self::assertStringContainsString('JemVenueSnapshot::summary($row)', $pdf);
        self::assertStringContainsString('JemVenueSnapshot::summary($item)', $pdf);
        self::assertStringContainsString('JemVenueSnapshot::summary($event)', $ics);
        self::assertStringContainsString("setLocation(\$location)", $ics);
    }

    public function testNotificationsAndRemindersIncludeVenueConfigurationWithoutChangingLegacyArguments(): void
    {
        $catalogue = $this->read('/site/classes/notificationtemplatecatalog.class.php');
        $mailer = $this->read('/plugins/plg_jem_mailer/mailer.php');
        $reminders = $this->read('/site/classes/reminderservice.class.php');

        self::assertMatchesRegularExpression('/private static function allowedTokens\(\)[\s\S]+?venue_configuration/', $catalogue);
        self::assertDoesNotMatchRegularExpression('/bodyLegacyTokens[\s\S]+?array_merge\(\$tokens, array\([\s\S]+?venue_configuration/', $catalogue);
        self::assertStringContainsString("'a.online_meeting_url', 'a.online_meeting_label', 'a.venue_snapshot'", $mailer);
        self::assertStringContainsString("\$values['venue_configuration'] = JemVenueSnapshot::summary(\$event)", $mailer);
        self::assertStringContainsString('_appendVenueConfiguration(', $mailer);
        self::assertStringContainsString("'venue_configuration' => JemVenueSnapshot::summary(\$registration)", $reminders);
        self::assertStringContainsString("'e.venue_snapshot'", $reminders);
    }

    public function testVenueConfigurationHasGlobalAndEventMenuDisplayControls(): void
    {
        $settings = $this->read('/admin/models/forms/settings.xml');
        $menu = $this->read('/site/views/event/tmpl/default.xml');
        $view = $this->read('/site/views/event/view.html.php');
        $programme = $this->read('/site/common/hierarchy/event_programme.php');
        $pdf = $this->read('/site/views/event/view.raw.php');
        $ics = $this->read('/site/helpers/helper.php');

        self::assertStringContainsString('name="event_show_venue_configuration"', $settings);
        self::assertStringContainsString('name="event_show_venue_configuration"', $menu);
        self::assertStringContainsString('<option value="-1">JGLOBAL_USE_GLOBAL</option>', $menu);
        self::assertStringContainsString("get('event_show_venue_configuration', -1)", $view);
        self::assertStringContainsString('$this->showVenueConfiguration ?? true', $programme);
        self::assertStringContainsString("get('event_show_venue_configuration', -1)", $pdf);
        self::assertStringContainsString("get('event_show_venue_configuration', -1)", $ics);
    }

    private function read(string $path): string
    {
        $file = JEM_TEST_ROOT . $path;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }
}

<?php

declare(strict_types=1);

use Joomla\CMS\Form\Form;
use Joomla\Registry\Registry;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

/** Read-only Joomla form and rendered-card checks for the selectable profiles. */
final class OperatingProfileSelectionJoomlaIntegrationTest extends JoomlaTestCase
{
    /** Load Joomla form validation and the candidate capability policy, without saving settings. */
    public static function setUpBeforeClass(): void
    {
        self::bootJoomlaSite();
        require_once JEM_TEST_ROOT . '/site/classes/featurepolicy.class.php';
    }

    /** Validate the actual XML field with Joomla's options rule, including forbidden values. */
    public function testProfileFieldAcceptsTicketingAndRejectsUnavailableOptions(): void
    {
        $settings = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        $field = $settings->xpath('//field[@name="operating_profile"]')[0];
        foreach (['essential' => true, 'advanced' => true, 'ticketing' => true, 'commerce' => false, 'invalid' => false] as $profile => $allowed) {
            $form = new Form('jem-profile-' . $profile);
            self::assertTrue($form->load('<form><fieldset name="profile">' . $field->asXML() . '</fieldset></form>'));
            self::assertSame($allowed, $form->validate(new Registry(['operating_profile' => $profile])), $profile);
        }
    }

    /** A stored Ticketing choice renders as a checked, enabled radio while Commerce stays disabled. */
    public function testTicketingCardRendersSelectedAndCommerceRemainsDisabled(): void
    {
        $view = new class {
            public object $form;

            /** Return only the stored values consumed by the real profile layout. */
            public function __construct()
            {
                $this->form = new class {
                    /** @return mixed Stored fixture value or the field default. */
                    public function getValue($name, $group = null, $default = null)
                    {
                        return ['operating_profile' => 'ticketing', 'operating_profile_configured' => 1][$name] ?? $default;
                    }
                };
            }

            /** @return string Actual administrator profile-card markup. */
            public function render(): string
            {
                ob_start();
                try {
                    require JEM_TEST_ROOT . '/admin/views/settings/tmpl/default_profile.php';

                    return ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            }
        };
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML($view->render());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($dom);
        self::assertSame(1, $xpath->query('//input[@value="ticketing" and @checked and not(@disabled)]')->length);
        self::assertSame(3, $xpath->query('//input[@type="radio" and @name="jform[operating_profile]" and not(@disabled)]')->length);
        self::assertSame(1, $xpath->query('//input[@aria-describedby="jem-operating-profile-commerce-desc" and @disabled]')->length);
        self::assertSame(1, $xpath->query('//input[@type="radio" and @checked]')->length);
    }
}

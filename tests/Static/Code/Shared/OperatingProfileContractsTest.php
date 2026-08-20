<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OperatingProfileContractsTest extends TestCase
{
    public function testCentralPolicyIsLoadedByTheSharedFactory(): void
    {
        $factory = (string) file_get_contents(JEM_TEST_ROOT . '/site/factory.php');
        $policy = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/featurepolicy.class.php');

        self::assertStringContainsString("classes/featurepolicy.class.php", $factory);
        self::assertStringContainsString("PROFILE_ESSENTIAL = 'essential'", $policy);
        self::assertStringContainsString("PROFILE_ADVANCED = 'advanced'", $policy);
        self::assertStringContainsString("PROFILE_COMMERCE = 'commerce'", $policy);
        self::assertStringContainsString('SELECTABLE_PROFILES', $policy);
    }

    public function testSettingsExposeTwoEnabledProfilesAndOneDisabledPreview(): void
    {
        $form = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default_profile.php');
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/settings.php');

        self::assertStringContainsString('name="operating_profile"', $form);
        self::assertStringContainsString('<option value="essential">', $form);
        self::assertStringContainsString('<option value="advanced">', $form);
        self::assertStringNotContainsString('<option value="commerce">', $form);
        self::assertStringContainsString('COM_JEM_OPERATING_PROFILE_COMMERCE', $layout);
        self::assertStringContainsString('aria-disabled="true"', $layout);
        self::assertStringContainsString('<fieldset class="options-form jem-operating-profile', $layout);
        self::assertStringContainsString('COM_JEM_OPERATING_PROFILE_DESC', $layout);
        self::assertStringNotContainsString('COM_JEM_OPERATING_PROFILE_QUESTION', $layout);
        self::assertGreaterThan(
            strpos($layout, 'jem-operating-profile-grid'),
            strpos($layout, 'id="jem-operating-profile-desc"')
        );
        self::assertSame(1, substr_count($layout, 'hide-aware-inline-help d-none'));
        self::assertStringNotContainsString('COM_JEM_OPERATING_PROFILE_INTRO', $layout);
        self::assertStringNotContainsString('COM_JEM_OPERATING_PROFILE_SAFE_CHANGE', $layout);
        self::assertStringNotContainsString('COM_JEM_OPERATING_PROFILE_COMING_SOON', $layout);
        self::assertStringNotContainsString('COM_JEM_OPERATING_PROFILE_ESSENTIAL_VERSION', $layout);
        self::assertStringNotContainsString('COM_JEM_OPERATING_PROFILE_ADVANCED_VERSION', $layout);
        self::assertStringNotContainsString('COM_JEM_OPERATING_PROFILE_COMMERCE_VERSION', $layout);
        self::assertStringContainsString('normaliseSelectableProfile', $model);
    }

    public function testFreshAndUpgradeSqlUseEssentialWithoutEnablingCommerce(): void
    {
        $install = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');

        self::assertStringContainsString("('operating_profile', 'essential')", $install);
        self::assertStringContainsString("('operating_profile_configured', '0')", $install);
        self::assertStringContainsString("VALUES ('operating_profile', 'essential')", $update);
        self::assertStringNotContainsString("VALUES ('operating_profile', 'commerce')", $update);
    }

    public function testControlPanelUsesPolicyForDeferredCommerceSurfaces(): void
    {
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/main/view.html.php');
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/main/tmpl/default.php');
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/admin/helpers/helper.php');
        $manifest = (string) file_get_contents(JEM_TEST_ROOT . '/jem.xml');

        self::assertStringContainsString('JemFeaturePolicy::current()', $view);
        self::assertStringContainsString('FEATURE_NOTIFICATION_AUTOMATION', $layout);
        self::assertStringContainsString('FEATURE_PRICING', $layout);
        self::assertStringContainsString('FEATURE_PRICING', $helper);
        self::assertStringContainsString('FEATURE_NOTIFICATION_AUTOMATION', $helper);
        self::assertStringNotContainsString('view=taxrates', $manifest);
        self::assertStringNotContainsString('view=notifications', $manifest);
    }

    public function testFrontendEditorsUseTheSameOperatingPolicyAsTheBackend(): void
    {
        $eventView = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/editevent/view.html.php');
        $eventClassic = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/editevent/tmpl/edit.php');
        $eventResponsive = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/editevent/tmpl/responsive/edit.php');
        $venueView = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/editvenue/view.html.php');
        $venueClassic = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/editvenue/tmpl/edit.php');
        $venueResponsive = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/editvenue/tmpl/responsive/edit.php');
        $venueModel = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/venue.php');

        self::assertStringContainsString('JemFeaturePolicy::current()', $eventView);
        self::assertStringContainsString('FEATURE_PROGRAMMES', $eventClassic);
        self::assertStringContainsString('FEATURE_PROGRAMMES', $eventResponsive);
        self::assertStringContainsString('JemFeaturePolicy::current()', $venueView);
        foreach (array($venueClassic, $venueResponsive) as $template) {
            self::assertStringContainsString('FEATURE_VENUE_HIERARCHY', $template);
            self::assertStringContainsString('FEATURE_VENUE_CAPACITY', $template);
        }
        self::assertStringContainsString('if (!$policy->allows(JemFeaturePolicy::FEATURE_VENUE_HIERARCHY))', $venueModel);
        self::assertStringContainsString('if (!$policy->allows(JemFeaturePolicy::FEATURE_VENUE_CAPACITY))', $venueModel);
    }

    public function testNotificationAutomationIsHiddenStoppedAndPreservedOutsideAdvanced(): void
    {
        $entrypoint = (string) file_get_contents(JEM_TEST_ROOT . '/admin/jem.php');
        $settingsLayout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default.php');
        $settingsModel = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/settings.php');
        $adminEvent = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/event/tmpl/edit.php');
        $classicEvent = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/editevent/tmpl/edit_extended.php');
        $responsiveEvent = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/editevent/tmpl/responsive/edit_extended.php');
        $eventModel = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $reminderService = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/reminderservice.class.php');

        foreach (array($entrypoint, $settingsLayout, $adminEvent, $classicEvent, $responsiveEvent, $eventModel) as $source) {
            self::assertStringContainsString('FEATURE_NOTIFICATION_AUTOMATION', $source);
        }
        self::assertStringContainsString('$storedConfiguration->get($key, $value)', $settingsModel);
        self::assertStringContainsString('elseif ($policy->allows(JemFeaturePolicy::FEATURE_NOTIFICATION_AUTOMATION))', $settingsModel);
        self::assertGreaterThanOrEqual(3, substr_count($reminderService, 'FEATURE_NOTIFICATION_AUTOMATION'));
    }

    public function testAdminEntrypointHandlesViewsWithoutATask(): void
    {
        $entrypoint = (string) file_get_contents(JEM_TEST_ROOT . '/admin/jem.php');

        self::assertStringContainsString("getCmd('task', '')", $entrypoint);
        self::assertStringContainsString("strtok(\$task, '.') ?: ''", $entrypoint);
        self::assertStringNotContainsString("strtolower((string) strtok(\$task, '.'))", $entrypoint);
    }

    public function testOperatingProfileStartsTheDefaultGlobalParametersTab(): void
    {
        $settingsLayout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default.php');
        $parametersLayout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default_parameters.php');

        self::assertStringContainsString("'active' => 'parameters'", $settingsLayout);
        self::assertStringContainsString("'recall' => false", $settingsLayout);
        self::assertLessThan(
            strpos($settingsLayout, "'settings-basic'"),
            strpos($settingsLayout, "'parameters'")
        );
        self::assertStringNotContainsString("loadTemplate('profile')", $settingsLayout);
        self::assertStringContainsString("loadTemplate('profile')", $parametersLayout);
        self::assertStringContainsString('jem-global-parameters-profile', $parametersLayout);
    }

    public function testGlobalParametersSeparateDisplayFromSystemSettings(): void
    {
        $form = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default_parameters.php');
        $language = (string) file_get_contents(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini');

        self::assertStringContainsString('fieldset name="globalparam_display"', $form);
        self::assertStringContainsString('fieldset name="globalparam_system"', $form);
        self::assertStringNotContainsString('fieldset name="globalparam"', $form);
        self::assertStringContainsString("getFieldset('globalparam_display')", $layout);
        self::assertStringContainsString("getFieldset('globalparam_system')", $layout);
        self::assertLessThan(
            strpos($form, 'name="globalparam_system"'),
            strpos($form, 'name="backend_show_order_numbers"')
        );
        self::assertLessThan(
            strpos($form, 'name="loglevel"'),
            strpos($form, 'name="globalparam_system"')
        );
        self::assertStringContainsString('COM_JEM_SETTINGS_DISPLAY_NAVIGATION="Display & Navigation"', $language);
        self::assertStringContainsString('COM_JEM_SETTINGS_SYSTEM_INTEGRATIONS="System & Integrations"', $language);
        self::assertLessThan(
            strpos($layout, "globalparam_recurrence"),
            strpos($layout, 'COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAYS')
        );
        self::assertLessThan(
            strpos($layout, '<div class="width-50 fltrt">'),
            strpos($layout, "globalparam_csv")
        );
        self::assertLessThan(
            strpos($layout, "globalparam_csv"),
            strpos($layout, "globalparam_recurrence")
        );
    }

    public function testAdvancedParametersAreSplitIntoFocusedFieldsets(): void
    {
        $form = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        $layout = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default_parameters.php');

        foreach (array(
            'globalparam_access',
            'globalparam_lists_calendar',
            'globalparam_time_regional',
            'globalparam_attachments',
            'globalparam_defaults',
            'globalparam_csv',
        ) as $fieldset) {
            self::assertStringContainsString('fieldset name="' . $fieldset . '"', $form);
            self::assertStringContainsString("'" . $fieldset . "'", $layout);
        }

        self::assertStringNotContainsString('fieldset name="globalparam2"', $form);
        self::assertSame(1, substr_count($form, '<field name="categories_order"'));
        self::assertStringContainsString('label="COM_JEM_SETTINGS_DISPLAY_LOCKED_EVENTS"', $form);
    }
}

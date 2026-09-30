<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AgeAccessContractsTest extends TestCase
{
    public function testInstallAndUpgradeSchemasContainAgeClassifications(): void
    {
        $install = $this->read('admin/sql/install.mysql.utf8.sql');
        $upgrade = $this->read('admin/sql/updates/mysql/5.1.0.sql');

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_age_levels`', $install);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_age_levels`', $upgrade);
        self::assertStringContainsString('repair510AgeAccessSchema', $this->read('script.php'));
        self::assertSame(2, substr_count($install, '`age_level_id` int(11) unsigned NULL DEFAULT NULL'));
        self::assertSame(2, substr_count($upgrade, 'ADD COLUMN `age_level_id` INT(11) UNSIGNED NULL DEFAULT NULL'));

        foreach (array(0, 6, 12, 16, 18, 65) as $minimumAge) {
            self::assertMatchesRegularExpression('/\(\d+,\s*\'[^\']+\',\s*' . $minimumAge . ',\s*\d+,\s*\'[^\']+\',\s*\'#[0-9A-F]{6}\'/i', $install);
            self::assertMatchesRegularExpression('/\(\d+,\s*\'[^\']+\',\s*' . $minimumAge . ',\s*\d+,\s*\'[^\']+\',\s*\'#[0-9A-F]{6}\'/i', $upgrade);
        }

        self::assertStringContainsString("(6, 'Seniors', 65, 99, '65+'", $install);
    }

    public function testEventAndVenueFormsExposeOneOptionalAgeLevel(): void
    {
        foreach (array(
            'admin/models/forms/event.xml',
            'admin/models/forms/venue.xml',
            'site/models/forms/event.xml',
            'site/models/forms/venue.xml',
        ) as $form) {
            $contents = $this->read($form);

            self::assertSame(1, substr_count($contents, 'name="age_level_id"'), $form);
            self::assertStringContainsString('type="agelevel"', $contents, $form);
        }

        self::assertStringContainsString(
            "JemAgeAccess::normaliseLevelId(\$array['age_level_id'])",
            $this->read('admin/tables/event.php')
        );
        self::assertStringContainsString(
            "JemAgeAccess::normaliseLevelId(\$array['age_level_id'])",
            $this->read('admin/tables/venue.php')
        );

        foreach (array(
            'admin/views/event/tmpl/edit.php',
            'admin/views/venue/tmpl/edit.php',
            'site/views/editevent/tmpl/edit.php',
            'site/views/editevent/tmpl/responsive/edit.php',
            'site/views/editvenue/tmpl/edit.php',
            'site/views/editvenue/tmpl/responsive/edit.php',
        ) as $template) {
            self::assertStringContainsString("'age_level_id'", $this->read($template), $template);
        }
    }

    public function testAgeServiceUsesEventDateAndTheEventVenueRangeIntersection(): void
    {
        $service = $this->read('site/classes/ageaccess.class.php');
        $factory = $this->read('site/factory.php');

        self::assertStringContainsString('/classes/ageaccess.class.php', $factory);
        self::assertStringContainsString('return $ages ? max($ages) : null;', $service);
        self::assertStringContainsString('return $ages ? min($ages) : null;', $service);
        self::assertStringContainsString('isAgeWithinRange', $service);
        self::assertStringContainsString('$birth->diff($event)->y', $service);
        self::assertStringContainsString("'profile.dob'", $service);
        self::assertStringContainsString('sqlVisibilityCondition', $service);
        self::assertStringContainsString('TIMESTAMPDIFF(YEAR, ', $service);
        self::assertStringContainsString(' BETWEEN ', $service);
    }

    public function testListsAndDirectEventAccessApplyTheSameAgePolicy(): void
    {
        foreach (array(
            'site/models/event.php',
            'site/models/eventslist.php',
            'site/models/search.php',
            'modules/mod_jem_types/helper.php',
        ) as $path) {
            $contents = $this->read($path);

            self::assertStringContainsString('JemAgeAccess::decorateEvent', $contents, $path);
            self::assertStringContainsString('JemAgeAccess::canViewEvent', $contents, $path);
        }

        self::assertStringContainsString(
            'JemAgeAccess::sqlVisibilityCondition',
            $this->read('site/models/eventslist.php')
        );
        self::assertStringContainsString(
            'JemAgeAccess::sqlVisibilityCondition',
            $this->read('site/models/search.php')
        );
    }

    public function testRegistrationRejectsUnknownAndUnderageUsersButKeepsCancellationAvailable(): void
    {
        $policy = $this->read('site/classes/registrationaccesspolicy.class.php');
        $view = $this->read('site/views/event/view.html.php');

        self::assertStringContainsString("public const AGE_UNKNOWN = 'age_unknown';", $policy);
        self::assertStringContainsString("public const AGE_RESTRICTED = 'age_restricted';", $policy);
        self::assertLessThan(
            strpos($policy, 'if ($ageState === \'restricted\')'),
            strpos($policy, 'if ($status < 0)')
        );
        self::assertStringContainsString('$this->ageRegistrationMessage', $view);

        foreach (array(
            'site/views/event/tmpl/default_attendees.php',
            'site/views/event/tmpl/responsive/default_attendees.php',
        ) as $template) {
            self::assertStringContainsString('ageRegistrationMessage', $this->read($template), $template);
        }
    }

    public function testSettingsAndPublicLayoutsExposeAgeLevelsAndAccessibleBadges(): void
    {
        $settingsModel = $this->read('admin/models/settings.php');
        $settingsView = $this->read('admin/views/settings/tmpl/default.php');
        $settingsTemplate = $this->read('admin/views/settings/tmpl/default_ageaccess.php');
        $output = $this->read('site/classes/output.class.php');

        self::assertStringContainsString('storeAgeLevels', $settingsModel);
        self::assertStringContainsString("loadTemplate('ageaccess')", $settingsView);
        self::assertStringContainsString('name="jem_age_levels"', $settingsTemplate);
        self::assertStringContainsString('function escapeAttribute(value)', $settingsTemplate);
        self::assertStringContainsString('jem-age-maximum', $settingsTemplate);
        self::assertStringContainsString('jem-age-preview', $settingsTemplate);
        self::assertStringContainsString('max="99"', $settingsTemplate);
        self::assertStringContainsString('jem-age-color-control', $settingsTemplate);
        self::assertStringContainsString('.jem-age-color-control input[type="text"]', $settingsTemplate);
        self::assertStringContainsString('width: 100%;', $settingsTemplate);
        self::assertStringContainsString('form-control-color::-webkit-color-swatch-wrapper', $settingsTemplate);
        self::assertStringContainsString('jem-age-cell--published', $settingsTemplate);
        self::assertStringContainsString('@media (max-width: 767.98px)', $settingsTemplate);
        self::assertStringContainsString(".replace(/\"/g, '&quot;')", $settingsTemplate);
        self::assertStringContainsString('function ageBadge', $output);
        self::assertStringContainsString('aria-label=', $output);
        self::assertStringContainsString("(\$item->age_minimum ?? null) === null", $output);

        foreach (array(
            'site/views/event/tmpl/default.php',
            'site/views/event/tmpl/responsive/default.php',
            'site/views/venue/tmpl/default.php',
            'site/views/venue/tmpl/responsive/default.php',
            'site/views/eventsblog/tmpl/default.php',
        ) as $template) {
            self::assertStringContainsString('ageBadge(', $this->read($template), $template);
        }

        foreach (array(
            'site/views/event/tmpl/default.php',
            'site/views/event/tmpl/responsive/default.php',
        ) as $template) {
            $contents = $this->read($template);
            self::assertStringContainsString("(\$this->item->age_minimum ?? null) !== null", $contents, $template);
            self::assertStringContainsString("Text::_('COM_JEM_AGE_LEVEL')", $contents, $template);
        }
    }

    public function testRecurrencesAndMapDiscoveryPreserveAgeAccess(): void
    {
        $eventModel = $this->read('admin/models/event.php');
        $mapHelper = $this->read('site/helpers/map.php');

        self::assertGreaterThanOrEqual(2, substr_count($eventModel, "'age_level_id'"));
        self::assertGreaterThanOrEqual(3, substr_count($mapHelper, 'JemAgeAccess::sqlVisibilityCondition'));
        self::assertStringContainsString('JemAgeAccess::decorateEvent', $mapHelper);
        self::assertStringContainsString('JemAgeAccess::canViewEvent', $mapHelper);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

        self::assertNotFalse($contents, $relativePath);

        return $contents;
    }
}

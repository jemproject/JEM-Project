<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/modules/mod_jem/script.php';

final class ModJemUpgradeTest extends TestCase
{
    public function testLegacyTitleSelectionIsMigratedWithoutChangingItsOutput(): void
    {
        self::assertSame(
            [
                'count' => '5',
                'showtitle' => '1',
                'showvenue' => '0',
            ],
            $this->migrate([
                'count' => '5',
                'showtitloc' => '1',
            ])
        );
    }

    public function testLegacyVenueSelectionIsMigratedWithoutChangingItsOutput(): void
    {
        self::assertSame(
            [
                'count' => '5',
                'showtitle' => '0',
                'showvenue' => '1',
            ],
            $this->migrate([
                'count' => '5',
                'showtitloc' => '0',
            ])
        );
    }

    public function testExistingJem5ChoicesArePreservedAndLegacyKeyIsRemoved(): void
    {
        self::assertSame(
            [
                'showtitle' => '0',
                'showvenue' => '0',
            ],
            $this->migrate([
                'showtitloc' => '1',
                'showtitle' => '0',
                'showvenue' => '0',
            ])
        );
    }

    public function testParamsWithoutLegacyKeyAreNotRewritten(): void
    {
        $params = [
            'count' => '5',
            'showtitle' => '0',
        ];

        self::assertSame($params, $this->migrate($params));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migrated = $this->migrate([
            'showtitloc' => '1',
            'linkdet' => '2',
        ]);

        self::assertSame($migrated, $this->migrate($migrated));
    }

    public function testInstallerAndRuntimeContainTheUpgradeGuards(): void
    {
        $installer = (string) file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem/script.php');
        $entryPoint = (string) file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem/mod_jem.php');
        $advancedLayout = (string) file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem/tmpl/table-advanced.php');

        self::assertStringContainsString('return $this->migrateLegacyModuleParams();', $installer);
        self::assertStringContainsString("->from(\$db->quoteName('#__modules'))", $installer);
        self::assertStringContainsString("unset(\$params['showtitloc']);", $installer);
        self::assertStringContainsString("\$params->exists('showtitle')", $entryPoint);
        self::assertStringContainsString("\$params->exists('showvenue')", $entryPoint);
        self::assertStringNotContainsString('$showtitloc', $advancedLayout);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function migrate(array $params): array
    {
        $method = new ReflectionMethod(mod_jemInstallerScript::class, 'migrateLegacyParams');

        return $method->invoke(null, $params);
    }
}

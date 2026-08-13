<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VenueMediaPathContractsTest extends TestCase
{
    public function testVenueMediaUsesStableIdsAndMirroredSmallTree(): void
    {
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/classes/venueimagepath.class.php');

        self::assertIsString($helper);
        self::assertStringContainsString("public const BASE = 'images/jem/venues'", $helper);
        self::assertStringContainsString("public const THUMB = 'small'", $helper);
        self::assertStringContainsString("\$venueId . '/venue'", $helper);
        self::assertStringContainsString("self::idFolder(\$venueId, 'spaces', \$spaceId)", $helper);
        self::assertStringContainsString("'/layouts/'", $helper);
        self::assertStringContainsString("self::BASE . '/' . self::THUMB", $helper);
    }

    public function testSchemaKeepsLegacyRowsFlatAndAddsMutableMediaMetadata(): void
    {
        $install = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');
        $installer = file_get_contents(JEM_TEST_ROOT . '/script.php');

        self::assertIsString($install);
        self::assertIsString($update);
        self::assertIsString($installer);
        self::assertMatchesRegularExpression('/CREATE TABLE IF NOT EXISTS `#__jem_venues`[\s\S]+?`image_path` varchar\(255\) NOT NULL DEFAULT \'\'/i', $install);
        self::assertStringContainsString('no existing files are moved here', $update);
        self::assertStringContainsString('repair510MediaSchema()', $installer);

        foreach (array(
            '#__jem_venue_capacity_profiles',
            '#__jem_venue_spaces',
            '#__jem_venue_layouts',
            '#__jem_venue_capacity_areas',
        ) as $table) {
            self::assertMatchesRegularExpression(
                '/CREATE TABLE IF NOT EXISTS `' . preg_quote($table, '/') . '`[\s\S]+?`image` varchar\(100\)[\s\S]+?`image_alt` varchar\(255\)/i',
                $install
            );
        }
    }

    public function testHousekeepingIsRecursiveAndExcludesTheSmallTree(): void
    {
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/housekeeping.php');
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');

        self::assertIsString($model);
        self::assertIsString($helper);
        self::assertStringContainsString("Folder::files(\$basePath, '.', true, true)", $model);
        self::assertStringContainsString('$thumbBase . DIRECTORY_SEPARATOR', $model);
        self::assertStringContainsString("Folder::files(\$basePath, '.', true, true)", $helper);
        self::assertStringContainsString("NULLIF(image_path", $model);
    }

    public function testSpaceAndLayoutMediaDoNotParticipateInPhysicalFingerprints(): void
    {
        $service = file_get_contents(JEM_TEST_ROOT . '/admin/classes/venuecapacity.class.php');

        self::assertIsString($service);
        self::assertStringContainsString('saveConfigurationMedia(', $service);
        self::assertStringNotContainsString("'layout_image'", $this->methodBody($service, 'layoutFingerprint'));
        self::assertStringNotContainsString("'space_image'", $this->methodBody($service, 'configurationFingerprint'));
    }

    private function methodBody(string $source, string $method): string
    {
        $start = strpos($source, 'function ' . $method);
        self::assertNotFalse($start);
        $next = strpos($source, "\n    private static function ", $start + 10);

        return substr($source, $start, $next === false ? null : $next - $start);
    }
}

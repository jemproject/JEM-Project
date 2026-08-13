<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!class_exists('Joomla\\String\\StringHelper')) {
    final class JemVenueCapacityTestStringHelper
    {
        public static function substr(string $value, int $start, ?int $length = null): string
        {
            return $length === null ? substr($value, $start) : substr($value, $start, $length);
        }

        public static function strlen(string $value): int
        {
            return strlen($value);
        }
    }

    class_alias(JemVenueCapacityTestStringHelper::class, 'Joomla\\String\\StringHelper');
}
if (!class_exists('Joomla\\CMS\\Language\\Text')) {
    final class JemVenueCapacityTestText
    {
        public static function _(string $key): string
        {
            return $key;
        }
    }

    class_alias(JemVenueCapacityTestText::class, 'Joomla\\CMS\\Language\\Text');
}

require_once JEM_TEST_ROOT . '/admin/classes/venuecapacity.class.php';

final class VenueCapacityNormalisationTest extends TestCase
{
    public function testProfileNameDefaultsToMainAndRemainsEditable(): void
    {
        self::assertSame('Main', JemVenueCapacityService::normaliseProfileName(''));
        self::assertSame('Main profile', JemVenueCapacityService::normaliseProfileName('  Main profile  '));
        self::assertSame(255, strlen(JemVenueCapacityService::normaliseProfileName(str_repeat('x', 300))));
    }

    public function testCapacityAreasCanDefineTheLayoutCapacityExactly(): void
    {
        $configuration = JemVenueCapacityService::normaliseFormData(array(
            'spaces' => array(array(
                'space_name' => 'Main hall',
                'space_code' => '',
                'layout_name' => 'Standing',
                'layout_code' => '',
                'layout_capacity' => 0,
                'areas' => array(
                    array('name' => 'Floor', 'code' => '', 'capacity' => 180, 'published' => 1),
                    array('name' => 'Balcony', 'code' => 'balcony', 'capacity' => 70, 'published' => 1),
                ),
            )),
        ), 300);

        self::assertCount(1, $configuration['spaces']);
        self::assertSame('main-hall', $configuration['spaces'][0]['space_code']);
        self::assertSame('#2F6F9F', $configuration['spaces'][0]['space_color']);
        self::assertSame('standing', $configuration['spaces'][0]['layout_code']);
        self::assertSame('#B78324', $configuration['spaces'][0]['layout_color']);
        self::assertSame(250, $configuration['spaces'][0]['layout_capacity']);
        self::assertSame(300, $configuration['profile_capacity']);
        self::assertSame('floor', $configuration['spaces'][0]['areas'][0]['code']);
        self::assertSame('#8A6D3B', $configuration['spaces'][0]['areas'][0]['color']);
        self::assertSame('quantity', $configuration['spaces'][0]['areas'][0]['allocation_mode']);
    }

    public function testBlankCapacityEditorRowIsIgnored(): void
    {
        $configuration = JemVenueCapacityService::normaliseFormData(array(
            'spaces' => array(array(
                'space_name' => '',
                'layout_name' => '',
                'layout_capacity' => 0,
                'areas' => array(),
            )),
        ), 0);

        self::assertSame(array('profile_capacity' => 0, 'spaces' => array()), $configuration);
    }

    public function testPublishedAreaRequiresExactPositiveCapacity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('COM_JEM_VENUE_CAPACITY_ERROR_AREA_CAPACITY');

        JemVenueCapacityService::normaliseFormData(array(
            'spaces' => array(array(
                'space_name' => 'Room',
                'layout_name' => 'Default',
                'layout_capacity' => 50,
                'areas' => array(array('name' => 'Floor', 'capacity' => 0, 'published' => 1)),
            )),
        ), 50);
    }

    public function testProfileCannotExceedVenuePhysicalCapacity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('COM_JEM_VENUE_CAPACITY_ERROR_PROFILE_PHYSICAL_LIMIT');

        JemVenueCapacityService::normaliseFormData(array(
            'spaces' => array(
                array('space_name' => 'Room A', 'layout_name' => 'Default', 'layout_capacity' => 60),
            ),
        ), 110, 100);
    }

    public function testCombinedLayoutsCannotExceedProfileCapacity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('COM_JEM_VENUE_CAPACITY_ERROR_PROFILE_LIMIT');

        JemVenueCapacityService::normaliseFormData(array(
            'spaces' => array(
                array('space_name' => 'Room A', 'layout_name' => 'Default', 'layout_capacity' => 60),
                array('space_name' => 'Room B', 'layout_name' => 'Default', 'layout_capacity' => 50),
            ),
        ), 100, 120);
    }

    public function testCapacityColoursAreNormalisedAndValidated(): void
    {
        $configuration = JemVenueCapacityService::normaliseFormData(array(
            'spaces' => array(array(
                'space_name' => 'Room',
                'space_color' => '#aabbcc',
                'layout_name' => 'Default',
                'layout_color' => '#112233',
                'layout_capacity' => 10,
                'areas' => array(array(
                    'name' => 'General',
                    'color' => '#abcdef',
                    'capacity' => 10,
                    'published' => 1,
                )),
            )),
        ), 10, 10);

        self::assertSame('#AABBCC', $configuration['spaces'][0]['space_color']);
        self::assertSame('#112233', $configuration['spaces'][0]['layout_color']);
        self::assertSame('#ABCDEF', $configuration['spaces'][0]['areas'][0]['color']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('COM_JEM_VENUE_CAPACITY_ERROR_COLOR');
        JemVenueCapacityService::normaliseFormData(array(
            'spaces' => array(array(
                'space_name' => 'Invalid colour',
                'space_color' => 'red',
                'layout_name' => 'Default',
                'layout_capacity' => 1,
            )),
        ), 1, 1);
    }
}

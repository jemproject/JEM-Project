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
        self::assertSame('standing', $configuration['spaces'][0]['layout_code']);
        self::assertSame(250, $configuration['spaces'][0]['layout_capacity']);
        self::assertSame('floor', $configuration['spaces'][0]['areas'][0]['code']);
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

        self::assertSame(array('spaces' => array()), $configuration);
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
        $this->expectExceptionMessage('COM_JEM_VENUE_CAPACITY_ERROR_PROFILE_LIMIT');

        JemVenueCapacityService::normaliseFormData(array(
            'spaces' => array(
                array('space_name' => 'Room A', 'layout_name' => 'Default', 'layout_capacity' => 60),
                array('space_name' => 'Room B', 'layout_name' => 'Default', 'layout_capacity' => 50),
            ),
        ), 100);
    }
}

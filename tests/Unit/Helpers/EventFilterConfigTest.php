<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/eventfilterconfig.class.php';

final class EventFilterConfigTest extends TestCase
{
    public function testEmptyConfigurationProvidesTheOrderedPilotRows(): void
    {
        $configuration = JemEventFilterConfig::normalise('');

        self::assertSame(2, $configuration['version']);
        self::assertSame(
            array('contact_category', 'contact'),
            array_column($configuration['rows'], 'key')
        );
        self::assertFalse($configuration['rows'][0]['active']);
        self::assertFalse($configuration['rows'][0]['visible']);
        self::assertFalse($configuration['rows'][0]['editable']);
    }

    public function testStoredOrderAndValidValuesArePreserved(): void
    {
        $configuration = JemEventFilterConfig::normalise(array(
            'version' => 1,
            'rows' => array(
                array(
                    'key' => 'contact',
                    'condition' => 'in',
                    'value' => array('9', 4, 9, 0, -2),
                    'active' => 1,
                    'visible' => 1,
                    'editable' => 0,
                ),
                array(
                    'key' => 'contact_category',
                    'condition' => 'exact',
                    'value' => '12',
                    'active' => true,
                    'visible' => true,
                    'editable' => true,
                ),
            ),
        ));

        self::assertSame(array('contact', 'contact_category'), array_column($configuration['rows'], 'key'));
        self::assertSame(array(9, 4), $configuration['rows'][0]['value']);
        self::assertTrue($configuration['rows'][0]['active']);
        self::assertTrue($configuration['rows'][0]['visible']);
        self::assertFalse($configuration['rows'][0]['editable']);
        self::assertSame('exact', $configuration['rows'][1]['condition']);
        self::assertSame(12, $configuration['rows'][1]['value']);
    }

    public function testInvalidRowsAndStatusCombinationsAreNormalised(): void
    {
        $configuration = JemEventFilterConfig::normalise(array(
            'rows' => array(
                array(
                    'key' => 'unknown',
                    'value' => 99,
                    'active' => true,
                ),
                array(
                    'key' => 'contact_category',
                    'condition' => 'invalid',
                    'value' => 0,
                    'active' => true,
                    'visible' => true,
                    'editable' => false,
                ),
                array(
                    'key' => 'contact_category',
                    'value' => 3,
                    'active' => true,
                ),
            ),
        ));

        self::assertCount(2, $configuration['rows']);
        self::assertSame('descendants', $configuration['rows'][0]['condition']);
        self::assertFalse($configuration['rows'][0]['active']);
        self::assertTrue($configuration['rows'][0]['visible']);
        self::assertTrue($configuration['rows'][0]['editable']);
    }

    public function testEditableAlwaysImpliesVisible(): void
    {
        $row = JemEventFilterConfig::row(array(
            'rows' => array(
                array(
                    'key' => 'contact',
                    'value' => array(),
                    'active' => false,
                    'visible' => false,
                    'editable' => true,
                ),
            ),
        ), JemEventFilterConfig::CONTACT);

        self::assertTrue($row['visible']);
        self::assertTrue($row['editable']);
    }

    public function testFingerprintChangesWithFilterPolicy(): void
    {
        $hidden = JemEventFilterConfig::normalise('');
        $visible = $hidden;
        $visible['rows'][0]['visible'] = true;
        $visible['rows'][0]['editable'] = true;

        self::assertNotSame(
            JemEventFilterConfig::fingerprint($hidden),
            JemEventFilterConfig::fingerprint($visible)
        );
    }

    public function testOptionalCustomFieldsPreserveOrderAndAreNotAddedByDefault(): void
    {
        $configuration = JemEventFilterConfig::normalise(array(
            'rows' => array(
                array(
                    'key' => 'custom4',
                    'condition' => 'exact',
                    'value' => '  Advanced  ',
                    'active' => true,
                    'visible' => true,
                    'editable' => false,
                ),
                array(
                    'key' => 'contact',
                    'condition' => 'in',
                    'value' => array(7),
                    'active' => true,
                ),
                array(
                    'key' => 'custom2',
                    'condition' => 'contains',
                    'value' => '<b>Music</b>',
                    'active' => true,
                    'visible' => true,
                    'editable' => true,
                ),
            ),
        ));

        self::assertSame(
            array('custom4', 'contact', 'custom2', 'contact_category'),
            array_column($configuration['rows'], 'key')
        );
        self::assertSame('Advanced', $configuration['rows'][0]['value']);
        self::assertSame('Music', $configuration['rows'][2]['value']);
        self::assertSame(2, count(array_filter(
            $configuration['rows'],
            static fn(array $row): bool => JemEventFilterConfig::isCustomKey($row['key'])
        )));
    }

    public function testOnlyPhysicalCustomColumnsAreAcceptedAndValuesAreBounded(): void
    {
        $longValue = str_repeat('x', 250);
        $configuration = JemEventFilterConfig::normalise(array(
            'rows' => array(
                array('key' => 'custom1', 'value' => $longValue, 'active' => true),
                array('key' => 'custom1', 'value' => 'duplicate', 'active' => true),
                array('key' => 'custom0', 'value' => 'invalid', 'active' => true),
                array('key' => 'custom11', 'value' => 'invalid', 'active' => true),
                array('key' => 'a.custom1', 'value' => 'invalid', 'active' => true),
            ),
        ));

        self::assertSame(array('custom1', 'contact_category', 'contact'), array_column($configuration['rows'], 'key'));
        self::assertSame(200, strlen($configuration['rows'][0]['value']));
        self::assertTrue($configuration['rows'][0]['active']);
    }
}

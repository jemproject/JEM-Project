<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/categorycustomfields.class.php';

final class CategoryCustomFieldsConfigurationTest extends TestCase
{
    public function testMissingConfigurationKeepsTheBackwardCompatibleGlobalMode(): void
    {
        self::assertSame(
            array(
                'mode' => 'global',
                'jem_field_ids' => array(),
                'joomla_field_ids' => array(),
                'joomla_group_ids' => array(),
            ),
            JemCategoryCustomFields::normaliseConfiguration('')
        );
    }

    public function testLegacySelectionIsUniqueSortedAndLimitedToTenSlots(): void
    {
        self::assertSame(
            array(
                'mode' => 'selection',
                'jem_field_ids' => array(1, 8, 10),
                'joomla_field_ids' => array(),
                'joomla_group_ids' => array(),
            ),
            JemCategoryCustomFields::normaliseConfiguration(array(
                'mode' => 'custom',
                'field_ids' => array('8', 1, 11, -2, 8, 10),
            ))
        );
    }

    public function testJoomlaSelectionKeepsOnlyPositiveUniqueIds(): void
    {
        self::assertSame(
            array(
                'mode' => 'selection',
                'jem_field_ids' => array(),
                'joomla_field_ids' => array(12, 25, 31),
                'joomla_group_ids' => array(),
            ),
            JemCategoryCustomFields::normaliseConfiguration(array(
                'mode' => 'joomla',
                'field_ids' => array(31, '12', 0, 25, 31),
            ))
        );
    }

    public function testCombinedSelectionNormalisesAllThreeProviders(): void
    {
        self::assertSame(
            array(
                'mode' => 'selection',
                'jem_field_ids' => array(1, 4),
                'joomla_field_ids' => array(3, 8),
                'joomla_group_ids' => array(2, 9),
            ),
            JemCategoryCustomFields::normaliseConfiguration(array(
                'mode' => 'selection',
                'jem_field_ids' => array(4, 1, 4),
                'joomla_field_ids' => array(8, 3, 0),
                'joomla_group_ids' => array(9, 2, -1),
            ))
        );
    }

    public function testNoneAndInvalidModesCannotRetainSubmittedIds(): void
    {
        self::assertSame(
            array(
                'mode' => 'none',
                'jem_field_ids' => array(),
                'joomla_field_ids' => array(),
                'joomla_group_ids' => array(),
            ),
            JemCategoryCustomFields::normaliseConfiguration(array('mode' => 'none', 'field_ids' => array(1, 2)))
        );
        self::assertSame(
            array(
                'mode' => 'global',
                'jem_field_ids' => array(),
                'joomla_field_ids' => array(),
                'joomla_group_ids' => array(),
            ),
            JemCategoryCustomFields::normaliseConfiguration(array('mode' => 'unexpected', 'field_ids' => array(1, 2)))
        );
    }

    public function testPresentationOrderDefaultsToJemThenJoomlaThenGroups(): void
    {
        self::assertSame(
            array('jem', 'joomla', 'groups'),
            JemCategoryCustomFields::normalisePresentationOrder('unexpected')
        );
        self::assertSame(
            array('groups', 'joomla', 'jem'),
            JemCategoryCustomFields::normalisePresentationOrder('groups_joomla_jem')
        );
    }

    public function testDetailRowsKeepTheConfiguredOrderAndShareOneSeparator(): void
    {
        $rows = JemCategoryCustomFields::renderOrderedDetailRows(
            array(
                'jem' => '<dt class="jem">JEM</dt><dd class="jem">1</dd>',
                'joomla' => '<dt class="joomla">Joomla</dt><dd class="joomla">2</dd>',
                'groups' => '<dt class="group">Group</dt><dd class="group">3</dd>',
            ),
            'joomla_groups_jem'
        );

        self::assertSame(
            '<dt class="joomla">Joomla</dt><dd class="joomla">2</dd>'
            . '<dt class="group">Group</dt><dd class="group">3</dd>'
            . '<dt class="jem">JEM</dt><dd class="jem">1</dd>',
            $rows
        );
        self::assertSame(
            '<dt class="jem-custom-fields-start joomla">Joomla</dt>'
            . '<dd class="jem-custom-fields-start joomla">2</dd>'
            . '<dt class="group">Group</dt><dd class="group">3</dd>'
            . '<dt class="jem">JEM</dt><dd class="jem">1</dd>',
            JemCategoryCustomFields::addDetailSeparator($rows)
        );
    }

    public function testFieldGroupIsNotRenderedWhenAllItsValuesAreEmpty(): void
    {
        $method = new ReflectionMethod(JemCategoryCustomFields::class, 'buildJoomlaDetailPresentation');
        $method->setAccessible(true);

        $presentation = $method->invoke(
            null,
            array((object) array('id' => 7, 'value' => '   ', 'group_id' => 3)),
            null,
            'joomla-custom-',
            false,
            'event'
        );

        self::assertSame(array('rows' => '', 'cards' => ''), $presentation);
    }
}

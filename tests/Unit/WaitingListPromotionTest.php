<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/waitinglistpromotion.class.php';

final class WaitingListPromotionTest extends TestCase
{
    public function testPromotionLoadsCompleteRowsForTheStableIdentityWriter(): void
    {
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/waitinglistpromotion.class.php');

        self::assertMatchesRegularExpression(
            '/->select\([\'\"]\*[\'\"]\)\s*->from\(\$db->quoteName\([\'\"]#__jem_register[\'\"]\)\)/',
            $source,
            'saveLocked() requires the complete registration row during promotion.'
        );
    }

    public function testAutomaticSelectionUsesQueueOrderAndOnlyFitsAvailablePlaces(): void
    {
        $queue = array(
            (object) array('id' => 1, 'places' => 2),
            (object) array('id' => 2, 'places' => 1),
            (object) array('id' => 3, 'places' => 1),
        );

        $selected = JemWaitingListPromotion::selectForAvailablePlaces($queue, 2);

        self::assertSame(array(1), array_column($selected, 'id'));
    }

    public function testStrictStrategyStopsWhenTheFirstRequestDoesNotFit(): void
    {
        $queue = array(
            (object) array('id' => 1, 'places' => 3),
            (object) array('id' => 2, 'places' => 1),
            (object) array('id' => 3, 'places' => 1),
        );

        $selected = JemWaitingListPromotion::selectForAvailablePlaces($queue, 2);

        self::assertSame(array(), array_column($selected, 'id'));
    }

    public function testFillStrategyCanTemporarilySkipARequestThatDoesNotFit(): void
    {
        $queue = array(
            (object) array('id' => 1, 'places' => 3),
            (object) array('id' => 2, 'places' => 1),
            (object) array('id' => 3, 'places' => 1),
        );

        $selected = JemWaitingListPromotion::selectForAvailablePlaces(
            $queue,
            2,
            JemWaitingListPromotion::STRATEGY_FILL
        );

        self::assertSame(array(2, 3), array_column($selected, 'id'));
    }

    public function testNoCandidateIsSelectedWithoutCapacity(): void
    {
        $queue = array((object) array('id' => 1, 'places' => 1));

        self::assertSame(array(), JemWaitingListPromotion::selectForAvailablePlaces($queue, 0));
    }
}

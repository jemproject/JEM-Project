<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Immutable unit and commercial-line tax result.
 */
final class JemTaxCalculation
{
    public function __construct(
        public readonly JemMoney $unitNet,
        public readonly JemMoney $unitTax,
        public readonly JemMoney $unitGross,
        public readonly JemMoney $lineNet,
        public readonly JemMoney $lineTax,
        public readonly JemMoney $lineGross,
        public readonly int $quantity,
        public readonly JemTaxPolicy $policy
    ) {
    }
}

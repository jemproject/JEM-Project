<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;

require_once __DIR__ . '/money.class.php';

/** Format an immutable relocation review without reading mutable booking state. */
final class JemRelocationNotice
{
    /**
     * Produce the holder's plain-text explanation for the Mailer template.
     * @param array $change Persisted relocation snapshot.
     * @param callable $translate Recipient-language translation of a full language key.
     * @return string Previous/new areas, actual money, decision and reason.
     */
    public static function details(array $change, callable $translate): string
    {
        $lines = [];
        foreach ($change['before'] as $index => $area) {
            $next = $change['after'][$index]['quantity'];
            if ($area['quantity'] || $next) {
                $lines[] = $area['label'] . ': ' . $area['quantity'] . ' -> ' . $next;
            }
        }
        if ($change['currency'] !== '') {
            foreach (['old', 'quoted', 'difference', 'adjustment', 'total', 'paid', 'due', 'refund'] as $key) {
                $lines[] = $translate('COM_JEM_RELOCATION_' . strtoupper($key)) . ': '
                    . JemMoney::fromMinorUnits($change['financial'][$key], $change['currency'])->decimal()
                    . ' ' . $change['currency'];
            }
        }
        $lines[] = $translate('COM_JEM_RELOCATION_FINANCIAL') . ': '
            . $translate('COM_JEM_RELOCATION_' . strtoupper($change['financial']['choice']));
        $lines[] = $translate('COM_JEM_RELOCATION_REASON') . ': ' . $change['reason'];
        return implode("\n", $lines);
    }
}

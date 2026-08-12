<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Immutable semantic tax policy with a two-decimal percentage.
 */
final class JemTaxPolicy
{
    public const STANDARD = 'standard';
    public const REDUCED = 'reduced';
    public const ZERO = 'zero';
    public const EXEMPT = 'exempt';
    public const OUTSIDE_SCOPE = 'outside_scope';

    private string $type;
    private int $rateBasisPoints;
    private bool $priceIncludesTax;

    public function __construct(string $type, $rate, bool $priceIncludesTax)
    {
        $type = strtolower(trim($type));
        if (!in_array($type, self::types(), true)) {
            throw new InvalidArgumentException('Unsupported tax semantic type.');
        }

        $basisPoints = self::parseRate($rate);
        if (in_array($type, array(self::ZERO, self::EXEMPT, self::OUTSIDE_SCOPE), true) && $basisPoints !== 0) {
            throw new InvalidArgumentException('Zero, exempt and outside-scope tax policies must use 0.00 percent.');
        }

        $this->type = $type;
        $this->rateBasisPoints = $basisPoints;
        $this->priceIncludesTax = $priceIncludesTax;
    }

    public static function types(): array
    {
        return array(self::STANDARD, self::REDUCED, self::ZERO, self::EXEMPT, self::OUTSIDE_SCOPE);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function rateBasisPoints(): int
    {
        return $this->rateBasisPoints;
    }

    public function rateDecimal(): string
    {
        return intdiv($this->rateBasisPoints, 100) . '.' . str_pad((string) ($this->rateBasisPoints % 100), 2, '0', STR_PAD_LEFT);
    }

    public function priceIncludesTax(): bool
    {
        return $this->priceIncludesTax;
    }

    private static function parseRate($rate): int
    {
        if (is_float($rate) || is_bool($rate) || $rate === null) {
            throw new InvalidArgumentException('Tax percentage must be an integer or decimal string, never a float.');
        }

        $rate = trim((string) $rate);
        if (preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/D', $rate, $matches) !== 1) {
            throw new InvalidArgumentException('Tax percentage requires at most two decimal places.');
        }

        $basisPoints = ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
        if ($basisPoints > 10000) {
            throw new InvalidArgumentException('Tax percentage cannot exceed 100.00.');
        }

        return $basisPoints;
    }
}

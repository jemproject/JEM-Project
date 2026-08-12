<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Immutable two-decimal monetary value.
 *
 * Amounts are stored as integer minor units. Binary floating-point input is
 * deliberately rejected so database, preview and reservation calculations use
 * the same exact representation.
 */
final class JemMoney
{
    public const SCALE = 2;
    public const FACTOR = 100;

    private int $minorUnits;
    private string $currency;

    private function __construct(int $minorUnits, string $currency)
    {
        if ($minorUnits === PHP_INT_MIN) {
            throw new OverflowException('Money amount exceeds the supported symmetric integer range.');
        }
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO 4217 code.');
        }

        $this->minorUnits = $minorUnits;
        $this->currency = $currency;
    }

    public static function fromDecimal($amount, string $currency): self
    {
        if (is_float($amount) || is_bool($amount) || $amount === null) {
            throw new InvalidArgumentException('Money must be provided as an integer or decimal string, never as float.');
        }

        $amount = trim((string) $amount);
        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/D', $amount, $matches) !== 1) {
            throw new InvalidArgumentException('Money requires at most two decimal places.');
        }

        $whole = ltrim($matches[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($matches[3] ?? '', self::SCALE, '0');
        $maximumWholeValue = intdiv(PHP_INT_MAX, self::FACTOR);
        $maximumWhole = (string) $maximumWholeValue;
        if (strlen($whole) > strlen($maximumWhole)
            || (strlen($whole) === strlen($maximumWhole) && strcmp($whole, $maximumWhole) > 0)
            || ((int) $whole === $maximumWholeValue && (int) $fraction > PHP_INT_MAX % self::FACTOR)) {
            throw new OverflowException('Money amount exceeds the supported integer range.');
        }

        $minorUnits = ((int) $whole * self::FACTOR) + (int) $fraction;
        if (($matches[1] ?? '') === '-') {
            $minorUnits *= -1;
        }

        return new self($minorUnits, $currency);
    }

    public static function fromMinorUnits(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, $currency);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function decimal(): string
    {
        $absolute = abs($this->minorUnits);
        $value = intdiv($absolute, self::FACTOR) . '.' . str_pad((string) ($absolute % self::FACTOR), self::SCALE, '0', STR_PAD_LEFT);

        return $this->minorUnits < 0 ? '-' . $value : $value;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);
        if (($other->minorUnits > 0 && $this->minorUnits > PHP_INT_MAX - $other->minorUnits)
            || ($other->minorUnits < 0 && $this->minorUnits < PHP_INT_MIN - $other->minorUnits)) {
            throw new OverflowException('Money addition exceeds the supported integer range.');
        }

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function minus(self $other): self
    {
        return $this->plus(new self(-$other->minorUnits, $other->currency));
    }

    public function multipliedBy(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Money quantity cannot be negative.');
        }
        if ($quantity !== 0 && abs($this->minorUnits) > intdiv(PHP_INT_MAX, $quantity)) {
            throw new OverflowException('Money multiplication exceeds the supported integer range.');
        }

        return new self($this->minorUnits * $quantity, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minorUnits === $other->minorUnits;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Money values must use the same currency.');
        }
    }
}

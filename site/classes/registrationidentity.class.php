<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Generates opaque registration and operation references.
 */
final class JemRegistrationIdentity
{
    public const REFERENCE_LENGTH = 28;
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generateRegistrationReference($bytes = null)
    {
        return self::generate('R-', $bytes);
    }

    public static function generateOperationReference($bytes = null)
    {
        return self::generate('O-', $bytes);
    }

    public static function isRegistrationReference($reference)
    {
        return is_string($reference)
            && preg_match('/^R-[0-9A-HJKMNP-TV-Z]{26}$/D', $reference) === 1;
    }

    public static function isOperationReference($reference)
    {
        return is_string($reference)
            && preg_match('/^O-[0-9A-HJKMNP-TV-Z]{26}$/D', $reference) === 1;
    }

    private static function generate($prefix, $bytes = null)
    {
        if ($bytes === null) {
            $bytes = random_bytes(16);
        }

        if (!is_string($bytes) || strlen($bytes) !== 16) {
            throw new InvalidArgumentException('JEM references require exactly 16 bytes.');
        }

        return $prefix . self::encodeBase32($bytes);
    }

    private static function encodeBase32($bytes)
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';

        foreach (unpack('C*', $bytes) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 31];
                $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
            }
        }

        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $encoded;
    }
}

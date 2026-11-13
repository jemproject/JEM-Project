<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Plain-text validation for explicitly submitted registration comments. */
final class JemRegistrationComment
{
    /**
     * Preserve comment text without interpreting HTML, entities or plugin syntax.
     *
     * @param mixed $value Submitted comment; arrays and non-string values are invalid.
     * @return string Valid UTF-8 text with normalised line endings, at most 255 characters.
     * @throws InvalidArgumentException A language key describing invalid or oversized input.
     */
    public static function normalise($value): string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('COM_JEM_REGISTRATION_COMMENT_INVALID');
        }

        $value = str_replace(array("\r\n", "\r"), "\n", $value);
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('COM_JEM_REGISTRATION_COMMENT_INVALID');
        }
        if (mb_strlen($value, 'UTF-8') > 255) {
            throw new InvalidArgumentException('COM_JEM_REGISTRATION_COMMENT_TOO_LONG');
        }

        return $value;
    }
}

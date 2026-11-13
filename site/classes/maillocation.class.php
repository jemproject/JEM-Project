<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Optional physical-location rows in standard notification templates. */
final class JemMailLocation
{
    /**
     * Omit an empty location row, or its unused separator, before inserting content.
     *
     * @param string $template Unrendered plain-text template, including translated labels.
     * @param string|null $venue Physical venue name.
     * @param string|null $city Venue city.
     * @param string $venueMarker Placeholder representing the venue.
     * @param string $cityMarker Placeholder representing the city.
     * @return string Template with nonempty location values only; unrelated rows are unchanged.
     */
    public static function prepare(
        string $template,
        $venue,
        $city,
        string $venueMarker = '{venue}',
        string $cityMarker = '{city}'
    ): string {
        $hasVenue = trim((string) $venue) !== '';
        $hasCity = trim((string) $city) !== '';
        if ($hasVenue && $hasCity) {
            return $template;
        }

        $markers = preg_quote($venueMarker, '~') . '|' . preg_quote($cityMarker, '~');
        // Only a dedicated text row with these two placeholders, never HTML or event content.
        $pattern = '~^([^\r\n{}<>%]*?)(' . $markers . ')[\t ]*(?:/|,|[-–—]|\|)[\t ]*('
            . $markers . ')([\t ]*)(\r\n|\n|\r|$)~mu';

        return preg_replace_callback(
            $pattern,
            static function (array $match) use ($hasVenue, $hasCity, $venueMarker, $cityMarker): string {
                if ($match[2] === $match[3]) {
                    return $match[0];
                }
                if (!$hasVenue && !$hasCity) {
                    return '';
                }

                return $match[1] . ($hasVenue ? $venueMarker : $cityMarker) . $match[4] . $match[5];
            },
            $template
        ) ?? $template;
    }

    /**
     * Render a positional translation without shifting arguments when a location row is omitted.
     *
     * @param string $template Translated printf template; Joomla numbered aliases are supported.
     * @param array $arguments Original arguments in their original order.
     * @param int $venueIndex Zero-based venue argument index; city immediately follows it.
     * @return string Rendered body, preserving all other placeholders and literal percentages.
     */
    public static function renderLegacy(string $template, array $arguments, int $venueIndex): string
    {
        $template = preg_replace('/\[\[%([0-9]+):[^\]]*\]\]/', '%\1$s', $template);
        if (trim((string) ($arguments[$venueIndex] ?? '')) !== ''
            && trim((string) ($arguments[$venueIndex + 1] ?? '')) !== '') {
            return vsprintf($template, $arguments);
        }

        // Keep unusual custom printf formats intact instead of renumbering them incompletely.
        $printfPattern = '/%(?:%|(?:([1-9][0-9]*)\$)?([sd]))/';
        if (str_contains(preg_replace($printfPattern, '', $template), '%')) {
            return vsprintf($template, $arguments);
        }
        $nextIndex = 0;
        $indexed = preg_replace_callback(
            $printfPattern,
            static function (array $match) use (&$nextIndex): string {
                if ($match[0] === '%%') {
                    return $match[0];
                }

                $index = !empty($match[1]) ? (int) $match[1] : ++$nextIndex;

                return '%' . $index . '$' . $match[2];
            },
            $template
        );
        $prepared = self::prepare(
            $indexed,
            $arguments[$venueIndex] ?? '',
            $arguments[$venueIndex + 1] ?? '',
            '%' . ($venueIndex + 1) . '$s',
            '%' . ($venueIndex + 2) . '$s'
        );

        return vsprintf($prepared === $indexed ? $template : $prepared, $arguments);
    }
}

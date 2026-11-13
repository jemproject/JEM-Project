<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** A finite calendar interval shared by request validation and navigation. */
final class JemCalendarNavigation
{
    /** @var array|null Current calendar's filtered month bounds; never shared between requests. */
    private static ?array $range = null;

    /**
     * Use whole event months, or a centred window when no dated events are visible.
     *
     * @param string|null $first First visible event start date.
     * @param string|null $last Last visible event end date (start date in start-only mode).
     * @param string $today Joomla-local civil date.
     * @param int $months Empty-calendar window; even windows put the extra month in the future.
     * @return array{start: string, end: string} Inclusive civil-date bounds.
     */
    public static function bounds(?string $first, ?string $last, string $today, int $months = 3): array
    {
        if (self::validDate($first) && self::validDate($last) && $last >= $first) {
            $start = new DateTimeImmutable($first);
            $end = new DateTimeImmutable($last);
        } else {
            $months = max(1, min(120, $months));
            $before = intdiv($months - 1, 2);
            $current = (new DateTimeImmutable($today))->modify('first day of this month');
            $start = $current->modify('-' . $before . ' months');
            $end = $current->modify('+' . ($months - $before - 1) . ' months');
        }

        return ['start' => $start->format('Y-m-01'), 'end' => $end->format('Y-m-t')];
    }

    /**
     * Reject invalid dates before lexical date comparison.
     *
     * @param string|null $date Date to validate.
     * @return bool Whether this is a complete, real four-digit-year date.
     */
    private static function validDate(?string $date): bool
    {
        return $date !== null && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    /**
     * Determine whether a displayed period intersects the allowed calendar months.
     *
     * @param array $range Inclusive start/end bounds.
     * @param string $start First displayed date.
     * @param string|null $end Last displayed date, or the same day.
     * @return bool Whether the period can be displayed.
     */
    public static function overlaps(array $range, string $start, ?string $end = null): bool
    {
        $end = $end ?? $start;

        return self::validDate($start) && self::validDate($end) && $start <= $end
            && $start <= $range['end'] && $end >= $range['start'];
    }

    /**
     * Set the current calendar interval after querying its actual visibility scope.
     *
     * @param array $range Inclusive month bounds.
     * @return void
     */
    public static function setRange(array $range): void
    {
        self::$range = $range;
    }

    /**
     * Test a navigation destination, accepting legacy callers without a prepared calendar.
     *
     * @param string|DateTimeInterface $start First displayed date.
     * @param string|DateTimeInterface|null $end Last displayed date.
     * @return bool Whether to keep the link enabled.
     */
    public static function allows($start, $end = null): bool
    {
        $start = $start instanceof DateTimeInterface ? $start->format('Y-m-d') : (string) $start;
        $end = $end instanceof DateTimeInterface ? $end->format('Y-m-d') : ($end === null ? null : (string) $end);

        return self::$range === null || self::overlaps(self::$range, $start, $end);
    }

    /**
     * Render navigation attributes without an href when the destination is outside the range.
     *
     * @param string $href HTML-escaped local URL.
     * @param string|DateTimeInterface $start First destination date.
     * @param string|DateTimeInterface|null $end Last destination date.
     * @param string $rel Optional previous/next relationship.
     * @return string Safe attributes, including an accessible explanation when disabled.
     */
    public static function attributes(string $href, $start, $end = null, string $rel = ''): string
    {
        if (!self::allows($start, $end)) {
            return self::disabledAttributes();
        }

        $rel = in_array($rel, ['prev', 'next'], true) ? $rel . ' nofollow' : 'nofollow';

        return ' href="' . $href . '" rel="' . $rel . '"';
    }

    /**
     * Explain a disabled boundary link to pointer and keyboard users.
     *
     * @return string Attributes for a non-navigable, focusable control.
     */
    public static function disabledAttributes(): string
    {
        return ' aria-disabled="true" data-jem-navigation-limit="1" tabindex="0" title="'
            . htmlspecialchars(Text::_('COM_JEM_CALENDAR_NO_MORE_EVENTS'), ENT_QUOTES, 'UTF-8') . '"';
    }

    /**
     * Reject explicit out-of-range requests; bring an undated menu entry to its nearest event period.
     *
     * @param array $range Filtered calendar bounds.
     * @param string $start Requested period start.
     * @param string $end Requested period end.
     * @param string $model Calendar model name (calendar, venuecal, categorycal, day, weekcal, annualcalendar).
     * @return void Throws a 404 before loading events, or redirects an undated landing page.
     */
    public static function checkRequest(array $range, string $start, string $end, string $model): void
    {
        self::setRange($range);
        $app = Factory::getApplication();
        $input = $app->input;
        $explicit = $model === 'day'
            ? !in_array($input->getString('id', ''), ['', '0'], true)
            : $input->get('yearID', null) !== null || $input->get('monthID', null) !== null
                || $input->get('weekID', null) !== null;
        // Do not let a model's legacy invalid-date fallback turn arbitrary URLs into HTTP 200.
        $validRequest = true;
        if ($explicit && in_array($model, ['calendar', 'categorycal', 'venuecal'], true)) {
            $requested = sprintf('%04d-%02d-01', $input->getInt('yearID', (int) substr($start, 0, 4)),
                $input->getInt('monthID', (int) substr($start, 5, 2)));
            $validRequest = $requested === $start && self::validDate($requested) && self::overlaps($range, $requested,
                (new DateTimeImmutable($requested))->format('Y-m-t'));
        } elseif ($explicit && $model === 'day') {
            $raw = $input->getString('id', '');
            $validRequest = (bool) preg_match('/^\d{8}$/D', $raw)
                && self::validDate(substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2));
        }
        if ($explicit && in_array($model, ['weekcal', 'annualcalendar'], true)) {
            $year = $input->getInt('yearID', (int) substr($start, 0, 4));
            $week = $input->getInt('weekID', 1);
            $validRequest = $year >= 1 && $year <= 9999 && $week >= -520000 && $week <= 520000;
            if ($validRequest && $model === 'annualcalendar') {
                $validRequest = $year === (int) substr($start, 0, 4);
            } elseif ($validRequest) {
                $iso = (new DateTimeImmutable('2000-01-01'))->setISODate($year, $week, 1);
                $validRequest = (int) $iso->format('Y') >= 1 && (int) $iso->format('Y') <= 9999;
            }
        }
        if ($validRequest && self::overlaps($range, $start, $end)) {
            return;
        }
        if ($explicit) {
            $app->getDocument()->setMetaData('robots', 'noindex, nofollow');
            throw new RuntimeException(Text::_('COM_JEM_CALENDAR_NO_MORE_EVENTS'), 404);
        }

        // Preserve the active menu, archive, category/venue scope and layout.
        $target = new DateTimeImmutable($end < $range['start'] ? $range['start'] : $range['end']);
        $uri = clone Uri::getInstance();
        if ($model === 'day') {
            $uri->setVar('id', $target->format('Ymd'));
        } elseif ($model === 'weekcal') {
            // Sunday belongs to the following displayed week when Sunday is its first day.
            if ((int) $app->getParams()->get('firstweekday', 1) === 0) {
                $target = $target->modify('+1 day');
            }
            $uri->setVar('yearID', $target->format('o'));
            $uri->setVar('weekID', $target->format('W'));
        } elseif ($model === 'annualcalendar') {
            $month = (int) substr($start, 5, 2);
            $uri->setVar('yearID', (int) $target->format('Y') - ((int) $target->format('n') < $month ? 1 : 0));
        } else {
            $uri->setVar('yearID', $target->format('Y'));
            $uri->setVar('monthID', $target->format('n'));
        }
        $app->redirect(Route::_($uri->toString(['path', 'query']), false));
        $app->close();
    }
}

<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

require_once __DIR__ . '/../helpers/calendarhourgrid.php';

/**
 * Native PDF drawing for the weekly hourly grid, followed by lossless session details.
 */
final class JemPdfWeekTimetable
{
    /**
     * Draw one overview per week and automatically paginated details for every projected session.
     *
     * @param object $pdf Configured TCPDF document, with no page added yet.
     * @param string $title Document title.
     * @param array $rows Access-filtered, unsplit event records.
     * @param array $period Normalized week model period.
     * @param string $font Configured PDF body font.
     * @return void
     */
    public static function render($pdf, string $title, array $rows, array $period, string $font = 'dejavusans'): void
    {
        $start = $period['start'];
        $days = (int) $start->diff($period['end'])->format('%r%a') + 1;
        // Preserve the PDF helpers' additional category-access guard.
        $rows = array_values(array_filter($rows, static function ($row): bool {
            return !isset($row->user_has_access_category) || (bool) $row->user_has_access_category;
        }));
        $grid = JemCalendarHourGridHelper::prepare($rows, $start->format('Y-m-d'), $days);
        if (!$grid) {
            return;
        }

        foreach (array_chunk($grid['columns'], 7) as $week) {
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->AddPage();
            $margins = $pdf->getMargins();
            $left = max(8.0, (float) $margins['left']);
            $right = max(8.0, (float) $margins['right']);
            $top = max(8.0, (float) $margins['top']);
            $width = $pdf->getPageWidth() - $left - $right;
            $bottom = $pdf->getPageHeight() - max(20.0, (float) $margins['bottom']);
            $pdf->SetFont($font, '', 8);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->SetDrawColor(148, 163, 184);
            $pdf->SetLineWidth(0.15);
            $pdf->setCellPaddings(0.7, 0.5, 0.7, 0.5);

            self::text($pdf, $left, $top, $width, 9, $title, 'C');
            $range = JemOutput::formatdate($week[0]['date']) . ' - '
                . JemOutput::formatdate($week[count($week) - 1]['date']);
            $caption = Text::_('COM_JEM_CALENDAR_MODE_TIMETABLE') . ' | ' . $range;
            self::text($pdf, $left, $top + 9, $width, 7, $caption, 'C');

            $axisWidth = 13.0;
            $dayWidth = ($width - $axisWidth) / count($week);
            $headingY = $top + 18;
            $allDayY = $headingY + 9;
            $plotY = $allDayY + 12;
            $plotHeight = max(20.0, $bottom - $plotY);
            $minutes = max(60, ($grid['last_hour'] - $grid['first_hour']) * 60);
            $details = [];
            $number = 0;
            self::text($pdf, $left, $allDayY, $axisWidth, 12, Text::_('COM_JEM_TIMETABLE_ALL_DAY'));

            for ($hour = $grid['first_hour']; $hour <= $grid['last_hour']; $hour++) {
                $y = $plotY + ($hour - $grid['first_hour']) * 60 / $minutes * $plotHeight;
                $pdf->SetDrawColor(203, 213, 225);
                $pdf->Line($left + $axisWidth, $y, $left + $width, $y);
                self::text($pdf, $left, $y - 2, $axisWidth - 1, 4, sprintf('%02d:00', $hour), 'R');
            }

            foreach ($week as $dayIndex => $column) {
                $x = $left + $axisWidth + $dayIndex * $dayWidth;
                $pdf->SetDrawColor(148, 163, 184);
                $pdf->Rect($x, $headingY, $dayWidth, $bottom - $headingY);
                $dateLabel = JemOutput::formatdate($column['date'], 'D d/m');
                self::text($pdf, $x, $headingY, $dayWidth, 9, $dateLabel, 'C');
                $allDayReferences = [];
                foreach ($column['all_day'] as $row) {
                    $details[] = self::detail(++$number, $column['date'], $row);
                    $allDayReferences[] = '#' . $number;
                }
                self::text($pdf, $x, $allDayY, $dayWidth, 12, implode(', ', $allDayReferences), 'C');

                foreach ($column['timed'] as $item) {
                    $detail = self::detail(++$number, $column['date'], $item['row']);
                    $details[] = $detail;
                    $laneWidth = $dayWidth / $column['lanes'];
                    $gap = min(0.2, $laneWidth / 10);
                    $eventX = $x + $item['lane'] * $laneWidth + $gap;
                    $eventY = $plotY + ($item['start'] - $grid['first_hour'] * 60) / $minutes * $plotHeight;
                    $eventHeight = min($bottom - $eventY, max(0.25, ($item['end'] - $item['start']) / $minutes * $plotHeight));
                    $eventWidth = $laneWidth - 2 * $gap;
                    $pdf->SetFillColor(224, 242, 254);
                    $pdf->SetDrawColor(56, 107, 135);
                    $pdf->Rect($eventX, $eventY, $eventWidth, $eventHeight, 'DF');
                    // Small rectangles retain their reference; full text is always available below.
                    $label = '#' . $number;
                    if ($eventWidth >= 14 && $eventHeight >= 5) {
                        $label .= ' ' . $detail['label'] . "\n" . $detail['title'];
                    }
                    self::text($pdf, $eventX, $eventY, $eventWidth, $eventHeight, $label);
                    if ($detail['url'] !== '') {
                        $pdf->Link($eventX, $eventY, $eventWidth, $eventHeight, $detail['url']);
                    }
                }
            }

            if (!$details) {
                self::text($pdf, $left + $axisWidth, $plotY + $plotHeight / 2, $width - $axisWidth, 8, Text::_('COM_JEM_NO_EVENTS'), 'C');
                continue;
            }

            // Never truncate titles or silently drop dense/short/all-day sessions to fit the overview.
            $pdf->SetAutoPageBreak(true, max(20.0, (float) $margins['bottom']));
            $pdf->AddPage();
            $pdf->SetFont($font, '', 9);
            $pdf->SetXY($left, $top);
            $pdf->writeHTML('<h2>' . self::escape($title) . '</h2><p>' . self::escape($caption) . '</p>', true, false, true, false, '');
            foreach ($details as $detail) {
                $titleHtml = self::escape($detail['title']);
                if ($detail['url'] !== '') {
                    $titleHtml = '<a href="' . self::escape($detail['url']) . '">' . $titleHtml . '</a>';
                }
                $html = '<p><b>#' . $detail['number'] . ' ' . $titleHtml . '</b><br>'
                    . self::escape(JemOutput::formatdate($detail['date']) . ' | ' . $detail['label']);
                if ($detail['venue'] !== '') {
                    $html .= '<br>' . self::escape($detail['venue']);
                }
                if ($detail['actual'] !== '') {
                    $html .= '<br>' . self::escape($detail['actual']);
                }
                $pdf->writeHTML($html . '</p>', true, false, true, false, '');
            }
        }
    }

    /**
     * Write clipped, fitted plain text inside its rectangle using native TCPDF sizing.
     *
     * @param object $pdf PDF document.
     * @param float $x Left position.
     * @param float $y Top position.
     * @param float $width Available width.
     * @param float $height Available height.
     * @param string $text Plain text.
     * @param string $align Horizontal alignment.
     * @return void
     */
    private static function text($pdf, float $x, float $y, float $width, float $height, string $text, string $align = 'L'): void
    {
        if ($text === '') {
            return;
        }
        $pdf->MultiCell($width, $height, $text, 0, $align, false, 0, $x, $y, true, 0, false, false, $height, 'M', true);
    }

    /**
     * Build a reference without substituting the visual fallback for a missing actual end time.
     *
     * @param int $number Reference within the week.
     * @param string $date Projected local date.
     * @param object $row Projected event/session.
     * @return array Plain details and optional routed event URL.
     */
    private static function detail(int $number, string $date, $row): array
    {
        $url = '';
        if (!empty($row->slug)) {
            $url = Route::_(JemHelperRoute::getEventRoute($row->slug), false);
            if (!preg_match('#^https?://#i', $url)) {
                $url = rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
            }
        }
        return [
            'number' => $number,
            'date' => $date,
            'title' => trim((string) ($row->title ?? '') . ' ' . JemOutput::eventStatusLabel($row)),
            'venue' => trim((string) ($row->venue ?? '')),
            'label' => !empty($row->day_schedule['all_day'])
                ? Text::_('COM_JEM_TIMETABLE_ALL_DAY') : (string) ($row->day_schedule['label'] ?? ''),
            'actual' => (string) ($row->day_schedule['actual'] ?? ''),
            'url' => $url,
        ];
    }

    /**
     * Escape text for the details-page HTML renderer.
     *
     * @param string $value Plain text.
     * @return string Escaped text.
     */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

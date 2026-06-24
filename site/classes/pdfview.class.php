<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;

/**
 * Shared PDF view helpers.
 */
class JemPdfView
{
    /**
     * Renders a simple event list PDF.
     */
    public static function renderEventList(string $title, array $rows, string $filename, string $profile = 'list'): void
    {
        self::renderTable(
            $title,
            array(
                Text::_('COM_JEM_DATE'),
                Text::_('COM_JEM_TITLE'),
                Text::_('COM_JEM_VENUE'),
                Text::_('COM_JEM_CITY'),
                Text::_('COM_JEM_CATEGORY'),
            ),
            self::buildEventListRows($rows),
            $filename,
            $profile
        );
    }

    /**
     * Renders the Events List PDF with links and event type badges.
     */
    public static function renderLinkedEventList(string $title, array $rows, string $filename, string $profile = 'list'): void
    {
        if (!class_exists('JemPdf', false) || !JemPdf::isAvailable()) {
            Factory::getApplication()->close();

            return;
        }

        $settings = JemHelper::config();
        $paper = self::getProfilePaperSettings($settings, $profile);
        $paperSize = $paper['size'];
        $orientation = $paper['orientation'];
        $singlePageTarget = JemPdf::prefersSinglePage($paperSize);
        $pdf = JemPdf::createDocument($title, $orientation, $paperSize);

        if (!$pdf) {
            Factory::getApplication()->close();

            return;
        }

        $margins = JemPdf::fitSinglePageMargins(self::getMargins($settings), $paperSize);
        $pdf->SetMargins($margins['left'], $margins['top'], $margins['right']);
        $pdf->SetAutoPageBreak(!$singlePageTarget, $singlePageTarget ? 0 : $margins['bottom']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();
        $pdf->writeHTML(self::buildLinkedEventListHtml($title, $rows, $paperSize), true, false, true, false, '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output($filename, 'D');
        Factory::getApplication()->close();
    }

    /**
     * Renders a venue list PDF.
     */
    public static function renderVenueList(string $title, array $rows, string $filename, string $profile = 'list'): void
    {
        self::renderTable(
            $title,
            array(
                Text::_('COM_JEM_VENUE'),
                Text::_('COM_JEM_CITY'),
                Text::_('COM_JEM_STATE'),
                Text::_('COM_JEM_COUNTRY'),
                Text::_('COM_JEM_WEBSITE'),
            ),
            self::buildVenueListRows($rows),
            $filename,
            $profile
        );
    }

    /**
     * Renders an event-first Events Map PDF.
     */
    public static function renderEventsMapList(string $title, array $rows, string $filename, string $mapProvider = 'osm'): void
    {
        self::renderTable(
            $title,
            array(
                Text::_('COM_JEM_DATE'),
                Text::_('COM_JEM_EVENT'),
                Text::_('COM_JEM_VENUE'),
                Text::_('COM_JEM_CITY'),
                Text::_('COM_JEM_STATE'),
                Text::_('COM_JEM_COUNTRY'),
                Text::_('COM_JEM_MAP'),
            ),
            self::buildEventsMapRows($rows, $mapProvider),
            $filename,
            'map'
        );
    }

    /**
     * Renders a monthly calendar PDF using a calendar grid.
     */
    public static function renderMonthlyCalendar(string $title, array $rows, string $filename, int $year, int $month, $params = null): void
    {
        if (!class_exists('JemPdf', false) || !JemPdf::isAvailable()) {
            Factory::getApplication()->close();

            return;
        }

        $settings = JemHelper::config();
        $paper = self::getProfilePaperSettings($settings, 'calendar');
        $paperSize = $paper['size'];
        $orientation = $paper['orientation'];
        $singlePageTarget = JemPdf::prefersSinglePage($paperSize);
        $pdf = JemPdf::createDocument($title, $orientation, $paperSize);

        if (!$pdf) {
            Factory::getApplication()->close();

            return;
        }

        $margins = JemPdf::fitSinglePageMargins(self::getMargins($settings), $paperSize);
        $pdf->SetMargins($margins['left'], $margins['top'], $margins['right']);
        $pdf->SetAutoPageBreak(!$singlePageTarget, $singlePageTarget ? 0 : $margins['bottom']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();
        $pdf->writeHTML(self::buildMonthlyCalendarHtml($title, $rows, $year, $month, $params, $paperSize), true, false, true, false, '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output($filename, 'D');
        Factory::getApplication()->close();
    }

    /**
     * Renders a weekly calendar PDF using a calendar grid.
     */
    public static function renderWeeklyCalendar(string $title, array $rows, string $filename, int $year, int $week, $params = null): void
    {
        if (!class_exists('JemPdf', false) || !JemPdf::isAvailable()) {
            Factory::getApplication()->close();

            return;
        }

        $settings = JemHelper::config();
        $paper = self::getProfilePaperSettings($settings, 'calendar');
        $paperSize = $paper['size'];
        $orientation = $paper['orientation'];
        $singlePageTarget = JemPdf::prefersSinglePage($paperSize);
        $pdf = JemPdf::createDocument($title, $orientation, $paperSize);

        if (!$pdf) {
            Factory::getApplication()->close();

            return;
        }

        $margins = JemPdf::fitSinglePageMargins(self::getMargins($settings), $paperSize);
        $pdf->SetMargins($margins['left'], $margins['top'], $margins['right']);
        $pdf->SetAutoPageBreak(!$singlePageTarget, $singlePageTarget ? 0 : $margins['bottom']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();
        $pdf->writeHTML(self::buildWeeklyCalendarHtml($title, $rows, $year, $week, $params, $paperSize), true, false, true, false, '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output($filename, 'D');
        Factory::getApplication()->close();
    }

    /**
     * Renders a special days list PDF.
     */
    public static function renderSpecialDays(string $title, array $rows, string $filename, string $profile = 'list'): void
    {
        self::renderTable(
            $title,
            array(
                Text::_('COM_JEM_TITLE'),
                Text::_('COM_JEM_SPECIAL_DAY_FIELD_TYPE'),
                Text::_('COM_JEM_DATE'),
                Text::_('COM_JEM_LOCATION'),
                Text::_('JSTATUS'),
            ),
            self::buildSpecialDayRows($rows),
            $filename,
            $profile
        );
    }

    /**
     * Renders a simple table PDF using the global PDF settings.
     */
    public static function renderTable(string $title, array $headers, array $rows, string $filename, string $profile = 'list', string $filterSummary = ''): void
    {
        if (!class_exists('JemPdf', false) || !JemPdf::isAvailable()) {
            Factory::getApplication()->close();

            return;
        }

        $settings = JemHelper::config();
        $paper = self::getProfilePaperSettings($settings, $profile);
        $paperSize = $paper['size'];
        $orientation = $paper['orientation'];
        $singlePageTarget = JemPdf::prefersSinglePage($paperSize);
        $pdf = JemPdf::createDocument($title, $orientation, $paperSize);

        if (!$pdf) {
            Factory::getApplication()->close();

            return;
        }

        $margins = JemPdf::fitSinglePageMargins(self::getMargins($settings), $paperSize);
        $pdf->SetMargins($margins['left'], $margins['top'], $margins['right']);
        $pdf->SetAutoPageBreak(!$singlePageTarget, $singlePageTarget ? 0 : $margins['bottom']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();
        $pdf->writeHTML(self::buildTableHtml($title, $headers, $rows, $paperSize, $filterSummary), true, false, true, false, '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output($filename, 'D');
        Factory::getApplication()->close();
    }

    private static function buildEventListRows(array $rows): array
    {
        $tableRows = array();

        foreach ($rows as $row) {
            $tableRows[] = array(
                self::htmlToPlainText(JemOutput::formatShortDateTime($row->dates ?? '', $row->times ?? '', $row->enddates ?? '', $row->endtimes ?? '')),
                self::buildPdfEventLink($row),
                self::buildPdfVenueLink($row),
                (string) ($row->city ?? ''),
                array('html' => self::buildPdfCategoryLinks((array) ($row->categories ?? array()))),
            );
        }

        return $tableRows;
    }

    private static function buildVenueListRows(array $rows): array
    {
        $tableRows = array();

        foreach ($rows as $row) {
            $tableRows[] = array(
                self::buildPdfVenueListLink($row),
                (string) ($row->city ?? ''),
                (string) ($row->state ?? ''),
                (string) ($row->country ?? ''),
                self::buildPdfExternalLink((string) ($row->url ?? '')),
            );
        }

        return $tableRows;
    }

    private static function buildEventsMapRows(array $rows, string $mapProvider): array
    {
        $tableRows = array();

        foreach ($rows as $row) {
            $tableRows[] = array(
                self::htmlToPlainText(JemOutput::formatShortDateTime($row->dates ?? '', $row->times ?? '', $row->enddates ?? '', $row->endtimes ?? '')),
                self::buildPdfEventLink($row),
                self::buildPdfVenueLink($row),
                (string) ($row->city ?? ''),
                (string) ($row->state ?? ''),
                (string) ($row->country ?? ''),
                self::buildPdfMapLink($row, $mapProvider),
            );
        }

        return $tableRows;
    }

    private static function buildSpecialDayRows(array $rows): array
    {
        $tableRows = array();

        foreach ($rows as $row) {
            $start = (string) ($row->start_date ?? '');
            $end = (string) ($row->end_date ?? '');
            $range = $start;

            if ($end !== '' && $end !== '0000-00-00' && $end !== $start) {
                $range .= ' - ' . $end;
            }

            $location = implode(', ', array_filter(array(
                trim((string) ($row->country ?? '')),
                trim((string) ($row->region ?? '')),
                trim((string) ($row->city ?? '')),
            )));

            $tableRows[] = array(
                (string) ($row->title ?? ''),
                (string) ($row->day_type ?? ''),
                $range,
                $location,
                ((int) ($row->published ?? 0) === 1) ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'),
            );
        }

        return $tableRows;
    }

    private static function buildTableHtml(string $title, array $headers, array $rows, string $paperSize, string $filterSummary = ''): string
    {
        $scale = JemPdf::getPosterScale($paperSize);
        $settings = JemHelper::config();
        $titleFontFamily = self::getPdfFontFamily($settings, 'pdf_title_font_family');
        $headerFontFamily = self::getPdfFontFamily($settings, 'pdf_header_font_family');
        $bodyFontFamily = self::getPdfFontFamily($settings, 'pdf_body_font_family');
        $baseFontSize = max(7, min(14, (int) round(8 * $scale)));
        $titleFontSize = max(12, min(28, (int) round(15 * $scale)));
        $cellPadding = JemPdf::prefersSinglePage($paperSize) ? '1.2mm' : '1.6mm';
        $columnWidths = self::calculateTableColumnWidths($headers, $rows);
        $html = array();

        $html[] = '<style>
            body { font-family: ' . $bodyFontFamily . '; }
            h1 { font-family: ' . $titleFontFamily . '; font-size: ' . $titleFontSize . 'pt; margin: 0 0 4mm 0; }
            table { border-collapse: collapse; width: 100%; }
            th { font-family: ' . $headerFontFamily . '; background-color: #e5e7eb; border: 0.2mm solid #9ca3af; font-size: ' . $baseFontSize . 'pt; font-weight: bold; padding: ' . $cellPadding . '; }
            td { font-family: ' . $bodyFontFamily . '; border: 0.2mm solid #cbd5e1; font-size: ' . $baseFontSize . 'pt; padding: ' . $cellPadding . '; vertical-align: top; }
            a { color: #1f5b99; text-decoration: underline; }
            .jem-pdf-view-intro, .jem-pdf-view-footer-text { font-size: ' . $baseFontSize . 'pt; line-height: ' . ($baseFontSize + 3) . 'pt; }
            .jem-pdf-view-intro { margin-bottom: 4mm; }
            .jem-pdf-view-footer-text { margin-top: 4mm; border-top: 0.2mm solid #d1d5db; padding-top: 2mm; }
            .jem-pdf-filter-summary { color: #374151; font-size: ' . max(6, $baseFontSize - 1) . 'pt; border-top: 0.2mm solid #d1d5db; margin-top: 0; padding-top: 2mm; padding-bottom: 2mm; }
            .jem-pdf-empty { color: #6b7280; text-align: center; }
        </style>';
        $html[] = '<h1>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h1>';
        $intro = self::buildViewTextBlock('intro');

        if ($intro !== '') {
            $html[] = $intro;
        }

        $html[] = '<table cellpadding="2" cellspacing="0"><thead><tr>';

        foreach ($headers as $index => $header) {
            $width = isset($columnWidths[$index]) ? ' width="' . $columnWidths[$index] . '%"' : '';
            $html[] = '<th' . $width . '>' . htmlspecialchars((string) $header, ENT_COMPAT, 'UTF-8') . '</th>';
        }

        $html[] = '</tr></thead><tbody>';

        if (!$rows) {
            $html[] = '<tr><td class="jem-pdf-empty" colspan="' . count($headers) . '">' . Text::_('COM_JEM_NO_EVENTS') . '</td></tr>';
        }

        foreach ($rows as $row) {
            $html[] = '<tr>';

            foreach ($row as $index => $cell) {
                $width = isset($columnWidths[$index]) ? ' width="' . $columnWidths[$index] . '%"' : '';

                if (is_array($cell) && isset($cell['html'])) {
                    $html[] = '<td' . $width . '>' . (string) $cell['html'] . '</td>';
                } else {
                    $html[] = '<td' . $width . '>' . nl2br(htmlspecialchars(self::htmlToPlainText((string) $cell), ENT_COMPAT, 'UTF-8')) . '</td>';
                }
            }

            $html[] = '</tr>';
        }

        $html[] = '</tbody></table>';
        $footer = self::buildViewTextBlock('footer');

        if ($footer !== '') {
            $html[] = $footer;
        }

        if ($filterSummary !== '') {
            $html[] = '<div style="height:4mm; line-height:4mm;">&nbsp;</div>';
            $html[] = '<div class="jem-pdf-filter-summary">' . nl2br(htmlspecialchars($filterSummary, ENT_COMPAT, 'UTF-8')) . '</div>';
        }

        return implode("\n", $html);
    }

    private static function calculateTableColumnWidths(array $headers, array $rows): array
    {
        $count = count($headers);

        if ($count < 1) {
            return array();
        }

        $scores = array_fill(0, $count, 0.0);

        foreach ($headers as $index => $header) {
            $scores[$index] = max(4.0, min(24.0, (float) StringHelper::strlen(self::htmlToPlainText((string) $header)) * 0.9));
        }

        foreach ($rows as $row) {
            for ($index = 0; $index < $count; ++$index) {
                $cell = $row[$index] ?? '';
                $text = is_array($cell) && isset($cell['html']) ? self::htmlToPlainText((string) $cell['html']) : self::htmlToPlainText((string) $cell);
                $lines = preg_split('/\R+/', trim($text)) ?: array('');
                $longest = 0;

                foreach ($lines as $line) {
                    $longest = max($longest, StringHelper::strlen(trim($line)));
                }

                $scores[$index] = max($scores[$index], min(32.0, (float) $longest));
            }
        }

        $min = 5.0;
        $max = $count > 6 ? 24.0 : 34.0;

        foreach ($scores as $index => $score) {
            $scores[$index] = max($min, min($max, $score));
        }

        $total = array_sum($scores) ?: 1.0;
        $widths = array();
        $used = 0.0;

        foreach ($scores as $index => $score) {
            if ($index === $count - 1) {
                $widths[$index] = max($min, 100.0 - $used);
            } else {
                $widths[$index] = round(($score / $total) * 100, 2);
                $used += $widths[$index];
            }
        }

        return $widths;
    }

    private static function buildMonthlyCalendarHtml(string $title, array $rows, int $year, int $month, $params, string $paperSize): string
    {
        $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = $monthStart->modify('last day of this month');
        $firstWeekDay = self::getCalendarFirstWeekday($params);
        $weekday = (int) $monthStart->format('w');
        $offset = $firstWeekDay === 0 ? $weekday : ($weekday === 0 ? 6 : $weekday - 1);
        $gridStart = $monthStart->modify('-' . $offset . ' days');
        $cellCount = (int) (ceil(($offset + (int) $monthStart->format('t')) / 7) * 7);
        $eventsByDate = self::buildCalendarEventsByDate($rows, $gridStart, $gridStart->modify('+' . ($cellCount - 1) . ' days'));
        $legend = self::buildCalendarCategoryLegend($rows);
        $useCategoryBackground = $params && method_exists($params, 'get') && (int) $params->get('eventbg_usecatcolor', 0) === 1;
        $weekdays = $firstWeekDay === 0
            ? array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT')
            : array('MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN');

        if (self::getCalendarPdfLayout($params) === 'agenda') {
            return self::buildCalendarAgendaHtml($title, $monthStart, $monthEnd, $eventsByDate, $legend, $paperSize, $monthStart->format('F Y'));
        }

        return self::buildCalendarGridHtml($title, $monthStart, $monthEnd, $gridStart, $cellCount, $weekdays, $eventsByDate, $legend, $paperSize, true, $monthStart->format('F Y'), $useCategoryBackground);
    }

    private static function buildWeeklyCalendarHtml(string $title, array $rows, int $year, int $week, $params, string $paperSize): string
    {
        $firstWeekDay = self::getCalendarFirstWeekday($params);
        $weekStart = (new DateTimeImmutable())->setISODate($year, max(1, $week));

        if ($firstWeekDay === 0) {
            $weekStart = $weekStart->modify('-1 day');
        }

        $weekEnd = $weekStart->modify('+6 days');
        $eventsByDate = self::buildCalendarEventsByDate($rows, $weekStart, $weekEnd);
        $legend = self::buildCalendarCategoryLegend($rows);
        $useCategoryBackground = $params && method_exists($params, 'get') && (int) $params->get('eventbg_usecatcolor', 0) === 1;
        $weekdays = $firstWeekDay === 0
            ? array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT')
            : array('MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN');

        if (self::getCalendarPdfLayout($params) === 'agenda') {
            return self::buildCalendarAgendaHtml($title, $weekStart, $weekEnd, $eventsByDate, $legend, $paperSize, Text::sprintf('COM_JEM_WEEKCAL_WEEK_NUMBER', (int) $weekStart->format('W'), (int) $weekStart->format('o')));
        }

        return self::buildCalendarGridHtml($title, $weekStart, $weekEnd, $weekStart, 7, $weekdays, $eventsByDate, $legend, $paperSize, false, Text::sprintf('COM_JEM_WEEKCAL_WEEK_NUMBER', (int) $weekStart->format('W'), (int) $weekStart->format('o')), $useCategoryBackground);
    }

    private static function buildCalendarAgendaHtml(
        string $title,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        array $eventsByDate,
        array $legend,
        string $paperSize,
        string $subtitle = ''
    ): string {
        $settings = JemHelper::config();
        $scale = JemPdf::getPosterScale($paperSize);
        $titleFontFamily = self::getPdfFontFamily($settings, 'pdf_title_font_family');
        $headerFontFamily = self::getPdfFontFamily($settings, 'pdf_header_font_family');
        $bodyFontFamily = self::getPdfFontFamily($settings, 'pdf_body_font_family');
        $titleFontSize = max(13, min(30, (int) round(18 * $scale)));
        $baseFontSize = max(7, min(13, (int) round(8 * $scale)));
        $smallFontSize = max(6, min(11, (int) round(7 * $scale)));
        $html = array();

        $html[] = '<style>
            body { font-family: ' . $bodyFontFamily . '; color: #111827; }
            h1 { font-family: ' . $titleFontFamily . '; font-size: ' . $titleFontSize . 'pt; margin: 0 0 3mm 0; }
            .jem-pdf-agenda-subtitle { font-family: ' . $headerFontFamily . '; font-size: ' . ($baseFontSize + 2) . 'pt; font-weight: bold; text-align: center; margin: 0 0 3mm 0; }
            a { color: #1f5b99; text-decoration: underline; }
            .jem-pdf-agenda { border-collapse: collapse; width: 100%; }
            .jem-pdf-agenda th { font-family: ' . $headerFontFamily . '; background-color: #f3f4f6; border: 0.2mm solid #cbd5e1; font-size: ' . $smallFontSize . 'pt; font-weight: bold; padding: 1mm; }
            .jem-pdf-agenda td { border-bottom: 0.2mm solid #d1d5db; font-size: ' . $baseFontSize . 'pt; padding: 1.2mm 1mm; vertical-align: top; }
            .jem-pdf-agenda-date { font-family: ' . $headerFontFamily . '; font-weight: bold; color: #111827; }
            .jem-pdf-agenda-weekday { color: #6b7280; font-size: ' . $smallFontSize . 'pt; }
            .jem-pdf-agenda-time { color: #374151; white-space: nowrap; }
            .jem-pdf-agenda-event-title { font-family: ' . $headerFontFamily . '; font-weight: bold; }
            .jem-pdf-agenda-muted { color: #6b7280; }
            .jem-pdf-category-mark { font-size: ' . ($smallFontSize + 2) . 'pt; font-weight: bold; }
            .jem-pdf-legend { margin-top: 3mm; font-size: ' . $smallFontSize . 'pt; }
        </style>';
        $html[] = '<h1>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h1>';

        if ($subtitle !== '') {
            $html[] = '<div class="jem-pdf-agenda-subtitle">' . htmlspecialchars($subtitle, ENT_COMPAT, 'UTF-8') . '</div>';
        }

        $intro = self::buildViewTextBlock('intro');

        if ($intro !== '') {
            $html[] = $intro;
        }

        $html[] = '<table class="jem-pdf-agenda" cellpadding="2" cellspacing="0">';
        $html[] = '<tr>'
            . '<th width="13%">' . Text::_('COM_JEM_DATE') . '</th>'
            . '<th width="11%">' . Text::_('COM_JEM_TIME') . '</th>'
            . '<th width="28%">' . Text::_('COM_JEM_EVENT') . '</th>'
            . '<th width="18%">' . Text::_('COM_JEM_VENUE') . '</th>'
            . '<th width="13%">' . Text::_('COM_JEM_TYPES') . '</th>'
            . '<th width="17%">' . Text::_('COM_JEM_CATEGORY') . '</th>'
            . '</tr>';
        $hasEvents = false;

        for ($date = $periodStart; $date <= $periodEnd; $date = $date->modify('+1 day')) {
            $dateKey = $date->format('Y-m-d');
            $dayEvents = $eventsByDate[$dateKey] ?? array();

            if (!$dayEvents) {
                continue;
            }

            foreach ($dayEvents as $index => $event) {
                $hasEvents = true;
                $html[] = '<tr>';

                if ($index === 0) {
                    $html[] = '<td width="13%" rowspan="' . count($dayEvents) . '"><span class="jem-pdf-agenda-date">' . (int) $date->format('j') . '</span><br /><span class="jem-pdf-agenda-weekday">' . htmlspecialchars($date->format('D'), ENT_COMPAT, 'UTF-8') . '</span></td>';
                }

                $html[] = '<td width="11%" class="jem-pdf-agenda-time">' . htmlspecialchars(self::getCalendarEventTime($event), ENT_COMPAT, 'UTF-8') . '</td>';
                $html[] = '<td width="28%">' . self::buildCalendarAgendaEventTitle($event) . '</td>';
                $html[] = '<td width="18%">' . self::buildCalendarAgendaVenue($event) . '</td>';
                $html[] = '<td width="13%">' . htmlspecialchars((string) ($event->type_name ?? ''), ENT_COMPAT, 'UTF-8') . '</td>';
                $html[] = '<td width="17%">' . self::buildPdfCategoryLinks((array) ($event->categories ?? array())) . '</td>';
                $html[] = '</tr>';
            }
        }

        if (!$hasEvents) {
            $html[] = '<tr><td colspan="6" class="jem-pdf-agenda-muted">' . Text::_('COM_JEM_NO_EVENTS') . '</td></tr>';
        }

        $html[] = '</table>';

        if ($legend) {
            $html[] = '<div class="jem-pdf-legend"><strong>' . Text::_('COM_JEM_CATEGORIES') . '</strong> &nbsp; ' . self::buildCalendarPdfLegendItems($legend) . '</div>';
        }

        $footer = self::buildViewTextBlock('footer');

        if ($footer !== '') {
            $html[] = $footer;
        }

        return implode("\n", $html);
    }

    private static function buildCalendarGridHtml(
        string $title,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        DateTimeImmutable $gridStart,
        int $cellCount,
        array $weekdays,
        array $eventsByDate,
        array $legend,
        string $paperSize,
        bool $shadeOutsideMonth,
        string $gridTitle = '',
        bool $useCategoryBackground = false
    ): string {
        $settings = JemHelper::config();
        $scale = JemPdf::getPosterScale($paperSize);
        $titleFontFamily = self::getPdfFontFamily($settings, 'pdf_title_font_family');
        $headerFontFamily = self::getPdfFontFamily($settings, 'pdf_header_font_family');
        $bodyFontFamily = self::getPdfFontFamily($settings, 'pdf_body_font_family');
        $titleFontSize = max(13, min(30, (int) round(18 * $scale)));
        $headerFontSize = max(7, min(14, (int) round(8 * $scale)));
        $dayFontSize = max(6, min(12, (int) round(7 * $scale)));
        $eventFontSize = max(5, min(11, (int) round(6 * $scale)));
        $dayWidth = number_format(100 / 7, 4, '.', '') . '%';
        $html = array();

        $html[] = '<style>
            body { font-family: ' . $bodyFontFamily . '; color: #111827; }
            h1 { font-family: ' . $titleFontFamily . '; font-size: ' . $titleFontSize . 'pt; margin: 0 0 3mm 0; }
            a { color: #111827; text-decoration: underline; }
            .jem-pdf-calendar { border-collapse: collapse; width: 100%; }
            .jem-pdf-calendar-title { background-color: #ffffff; color: #111827; border: 0.2mm solid #9ca3af; font-family: ' . $headerFontFamily . '; font-size: ' . ($headerFontSize + 4) . 'pt; font-weight: bold; text-align: center; height: 6mm; line-height: 6mm; padding: 0.8mm 1mm; }
            .jem-pdf-calendar th { font-family: ' . $headerFontFamily . '; background-color: #3f3f46; color: #ffffff; border: 0.2mm solid #9ca3af; font-size: ' . $headerFontSize . 'pt; font-weight: bold; text-align: center; }
            .jem-pdf-calendar td { border: 0.2mm solid #cbd5e1; font-size: ' . $dayFontSize . 'pt; vertical-align: top; height: 23mm; padding: 0.8mm; }
            .jem-pdf-calendar-week td { height: 70mm; }
            .jem-pdf-calendar-week td.jem-pdf-calendar-title { height: 6mm; line-height: 6mm; }
            .jem-pdf-daybar { background-color: #e5e5e5; font-weight: bold; padding: 0.3mm 0.5mm; }
            .jem-pdf-outside { background-color: #eeeeee; color: #9ca3af; }
            .jem-pdf-event-card { background-color: #fff8df; border: 0.15mm solid #f3e7bd; margin-top: 0.8mm; padding: 0.7mm; text-align: center; font-size: ' . $eventFontSize . 'pt; line-height: ' . ($eventFontSize + 2) . 'pt; }
            .jem-pdf-event-card a { color: inherit; }
            .jem-pdf-event-time { color: #4b5563; }
            .jem-pdf-category-mark { font-size: ' . ($eventFontSize + 2) . 'pt; font-weight: bold; }
            .jem-pdf-legend { margin-top: 3mm; font-size: ' . $headerFontSize . 'pt; }
        </style>';
        $html[] = '<h1>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h1>';
        $intro = self::buildViewTextBlock('intro');

        if ($intro !== '') {
            $html[] = $intro;
        }

        $html[] = '<table class="jem-pdf-calendar' . ($cellCount === 7 ? ' jem-pdf-calendar-week' : '') . '" cellpadding="1" cellspacing="0">';

        if ($gridTitle !== '') {
            $html[] = '<tr><td class="jem-pdf-calendar-title" colspan="7">' . htmlspecialchars($gridTitle, ENT_COMPAT, 'UTF-8') . '</td></tr>';
        }

        $html[] = '<tr>';

        foreach ($weekdays as $weekday) {
            $html[] = '<th width="' . $dayWidth . '">' . Text::_($weekday) . '</th>';
        }

        $html[] = '</tr>';

        for ($cell = 0; $cell < $cellCount; $cell++) {
            if ($cell % 7 === 0) {
                $html[] = '<tr>';
            }

            $date = $gridStart->modify('+' . $cell . ' days');
            $dateKey = $date->format('Y-m-d');
            $outside = $shadeOutsideMonth && $date->format('m') !== $periodStart->format('m');
            $classes = $outside ? ' class="jem-pdf-outside"' : '';
            $html[] = '<td width="' . $dayWidth . '"' . $classes . '>';
            $html[] = '<div class="jem-pdf-daybar">' . (int) $date->format('j') . '</div>';

            foreach (($eventsByDate[$dateKey] ?? array()) as $event) {
                $html[] = self::buildCalendarEventCard($event, $useCategoryBackground);
            }

            $html[] = '</td>';

            if ($cell % 7 === 6) {
                $html[] = '</tr>';
            }
        }

        $html[] = '</table>';

        if ($legend) {
            $html[] = '<div class="jem-pdf-legend"><strong>' . Text::_('COM_JEM_CATEGORIES') . '</strong> &nbsp; ' . self::buildCalendarPdfLegendItems($legend) . '</div>';
        }

        $footer = self::buildViewTextBlock('footer');

        if ($footer !== '') {
            $html[] = $footer;
        }

        return implode("\n", $html);
    }

    private static function buildCalendarPdfLegendItems(array $legend): string
    {
        $items = array();

        foreach ($legend as $item) {
            $color = trim((string) ($item['color'] ?? ''));

            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#6b7280';
            }

            $items[] = '<span class="jem-pdf-category-mark" style="color:' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8') . ';">&#9632;</span>&nbsp;'
                . htmlspecialchars((string) ($item['title'] ?? ''), ENT_COMPAT, 'UTF-8')
                . ' (' . (int) ($item['count'] ?? 0) . ')';
        }

        return implode(' &nbsp; ', $items);
    }

    private static function getCalendarFirstWeekday($params): int
    {
        if ($params && method_exists($params, 'get')) {
            $value = (int) $params->get('firstweekday', $params->get('weekdaystart', 1));

            return $value === 0 ? 0 : 1;
        }

        $settings = JemHelper::config();

        return (int) ($settings->weekdaystart ?? 1) === 0 ? 0 : 1;
    }

    private static function getCalendarPdfLayout($params): string
    {
        $mode = strtolower(Factory::getApplication()->input->getCmd('mode', ''));

        if (in_array($mode, array('agenda', 'calendar'), true)) {
            return $mode;
        }

        if ($params && method_exists($params, 'get')) {
            $layout = strtolower((string) $params->get('calendar_display_mode', 'calendar'));

            if (in_array($layout, array('agenda', 'calendar'), true)) {
                return $layout;
            }
        }

        $settings = JemHelper::config();
        $layout = strtolower((string) ($settings->pdf_calendar_layout ?? 'calendar'));

        return $layout === 'agenda' ? 'agenda' : 'calendar';
    }

    private static function buildCalendarEventsByDate(array $rows, DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): array
    {
        $eventsByDate = array();

        foreach ($rows as $row) {
            if (isset($row->user_has_access_category) && !$row->user_has_access_category) {
                continue;
            }

            if (!JemHelper::isValidDate($row->dates ?? '')) {
                continue;
            }

            $eventStart = new DateTimeImmutable((string) $row->dates);
            $eventEnd = isset($row->multi)
                ? $eventStart
                : (JemHelper::isValidDate($row->enddates ?? '') && $row->enddates >= $row->dates
                    ? new DateTimeImmutable((string) $row->enddates)
                    : $eventStart);
            $eventStart = $eventStart < $periodStart ? $periodStart : $eventStart;
            $eventEnd = $eventEnd > $periodEnd ? $periodEnd : $eventEnd;

            for ($date = $eventStart; $date <= $eventEnd; $date = $date->modify('+1 day')) {
                $dateKey = $date->format('Y-m-d');
                $eventKey = self::getCalendarEventIdentity($row, $dateKey);

                if (!isset($eventsByDate[$dateKey]['_keys'])) {
                    $eventsByDate[$dateKey]['_keys'] = array();
                }

                if (isset($eventsByDate[$dateKey]['_keys'][$eventKey])) {
                    continue;
                }

                $eventsByDate[$dateKey]['_keys'][$eventKey] = true;
                $eventsByDate[$dateKey][] = $row;
            }
        }

        foreach ($eventsByDate as &$events) {
            unset($events['_keys']);
        }
        unset($events);

        return $eventsByDate;
    }

    private static function getCalendarEventIdentity($row, string $dateKey): string
    {
        $id = (string) ($row->id ?? $row->eventid ?? $row->slug ?? $row->title ?? '');
        $multi = (string) ($row->multi ?? '');

        return $id . '|' . $dateKey . '|' . $multi;
    }

    private static function buildCalendarCategoryLegend(array $rows): array
    {
        $legend = array();

        foreach ($rows as $row) {
            foreach ((array) ($row->categories ?? array()) as $category) {
                $title = trim((string) ($category->catname ?? ''));

                if ($title === '') {
                    continue;
                }

                $key = (string) ($category->id ?? strtolower($title));
                $color = trim((string) ($category->color ?? ''));

                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                    $color = '#6b7280';
                }

                if (!isset($legend[$key])) {
                    $legend[$key] = array(
                        'title' => $title,
                        'color' => $color,
                        'count' => 0,
                    );
                }

                $legend[$key]['count']++;
            }
        }

        return $legend;
    }

    private static function buildCalendarEventCard($row, bool $useCategoryBackground = false): string
    {
        $title = htmlspecialchars((string) ($row->title ?? ''), ENT_COMPAT, 'UTF-8');
        $url = !empty($row->slug) ? self::absoluteUrl(Route::_(JemHelperRoute::getEventRoute($row->slug), false)) : '';
        $time = self::getCalendarEventTime($row);
        $categories = (array) ($row->categories ?? array());
        $category = reset($categories);
        $color = trim((string) ($category->color ?? ''));

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6b7280';
        }

        $cardStyle = '';
        $timeStyle = '';
        $linkStyle = '';
        $markColor = htmlspecialchars($color, ENT_COMPAT, 'UTF-8');

        if ($useCategoryBackground) {
            $textColor = self::getContrastingTextColor($color);
            $cardStyle = ' style="background-color:' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8')
                . '; border-color:' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8')
                . '; color:' . htmlspecialchars($textColor, ENT_COMPAT, 'UTF-8') . ';"';
            $timeStyle = ' style="color:' . htmlspecialchars($textColor, ENT_COMPAT, 'UTF-8') . ';"';
            $linkStyle = ' style="color:' . htmlspecialchars($textColor, ENT_COMPAT, 'UTF-8') . ';"';
            $markColor = htmlspecialchars($textColor, ENT_COMPAT, 'UTF-8');
        }

        if ($url !== '') {
            $title = '<a href="' . htmlspecialchars($url, ENT_COMPAT, 'UTF-8') . '"' . $linkStyle . '>' . $title . '</a>';
        }

        return '<div class="jem-pdf-event-card"' . $cardStyle . '>'
            . (!$useCategoryBackground ? '<div class="jem-pdf-category-mark" style="color:' . $markColor . ';">&#9632;</div>' : '')
            . ($time !== '' ? '<div class="jem-pdf-event-time"' . $timeStyle . '>' . htmlspecialchars($time, ENT_COMPAT, 'UTF-8') . '</div>' : '')
            . '<div>' . $title . self::buildPdfEventIndicators($row) . '</div>'
            . '</div>';
    }

    private static function getCalendarEventTime($row): string
    {
        $start = JemOutput::formattime($row->times ?? '', '', false);
        $end = JemOutput::formattime($row->endtimes ?? '', '', false);

        return trim($start . ($start !== '' && $end !== '' ? ' - ' : '') . $end);
    }

    private static function buildCalendarAgendaEventTitle($row): string
    {
        $title = htmlspecialchars((string) ($row->title ?? ''), ENT_COMPAT, 'UTF-8');
        $url = !empty($row->slug) ? self::absoluteUrl(Route::_(JemHelperRoute::getEventRoute($row->slug), false)) : '';

        if ($url !== '') {
            $title = '<a class="jem-pdf-agenda-event-title" href="' . htmlspecialchars($url, ENT_COMPAT, 'UTF-8') . '">' . $title . '</a>';
        }

        return $title . self::buildPdfEventIndicators($row);
    }

    private static function buildCalendarAgendaVenue($row): string
    {
        $link = self::buildPdfVenueLink($row);

        return (string) ($link['html'] ?? '');
    }

    private static function buildLinkedEventListHtml(string $title, array $rows, string $paperSize): string
    {
        $scale = JemPdf::getPosterScale($paperSize);
        $settings = JemHelper::config();
        $titleFontFamily = self::getPdfFontFamily($settings, 'pdf_title_font_family');
        $headerFontFamily = self::getPdfFontFamily($settings, 'pdf_header_font_family');
        $bodyFontFamily = self::getPdfFontFamily($settings, 'pdf_body_font_family');
        $baseFontSize = max(7, min(14, (int) round(8 * $scale)));
        $titleFontSize = max(14, min(30, (int) round(18 * $scale)));
        $eventTitleFontSize = max(10, min(18, (int) round(12 * $scale)));
        $html = array();

        $html[] = '<style>
            body { font-family: ' . $bodyFontFamily . '; }
            h1 { font-family: ' . $titleFontFamily . '; font-size: ' . $titleFontSize . 'pt; margin: 0 0 4mm 0; color: #111827; }
            a { color: #1f5b99; text-decoration: underline; }
            .jem-pdf-event-list { border-top: 0.5mm solid #f59e0b; }
            .jem-pdf-event { border-bottom: 0.2mm solid #d1d5db; padding: 2mm 0; font-size: ' . $baseFontSize . 'pt; }
            .jem-pdf-event-title { font-family: ' . $headerFontFamily . '; font-size: ' . $eventTitleFontSize . 'pt; font-weight: bold; color: #1f5b99; }
            .jem-pdf-event-meta { color: #111827; font-size: ' . $baseFontSize . 'pt; line-height: ' . ($baseFontSize + 3) . 'pt; }
            .jem-pdf-badge { color: #ffffff; font-weight: bold; border-radius: 1.5mm; padding: 0.6mm 1.6mm; }
            .jem-pdf-view-intro, .jem-pdf-view-footer-text { font-size: ' . $baseFontSize . 'pt; line-height: ' . ($baseFontSize + 3) . 'pt; }
            .jem-pdf-view-intro { margin-bottom: 4mm; }
            .jem-pdf-view-footer-text { margin-top: 4mm; border-top: 0.2mm solid #d1d5db; padding-top: 2mm; }
            .jem-pdf-muted { color: #6b7280; }
        </style>';
        $html[] = '<h1>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h1>';
        $intro = self::buildViewTextBlock('intro');

        if ($intro !== '') {
            $html[] = $intro;
        }

        $html[] = '<div class="jem-pdf-event-list">';

        if (!$rows) {
            $html[] = '<div class="jem-pdf-event jem-pdf-muted">' . Text::_('COM_JEM_NO_EVENTS') . '</div>';
        }

        foreach ($rows as $row) {
            if (isset($row->user_has_access_category) && !$row->user_has_access_category) {
                continue;
            }

            $eventTitle = trim((string) ($row->title ?? ''));
            $eventUrl = !empty($row->slug) ? self::absoluteUrl(Route::_(JemHelperRoute::getEventRoute($row->slug), false)) : '';
            $date = self::htmlToPlainText(JemOutput::formatShortDateTime($row->dates ?? '', $row->times ?? '', $row->enddates ?? '', $row->endtimes ?? ''));
            $meta = array();

            if ($date !== '') {
                $meta[] = '&#9719; ' . htmlspecialchars($date, ENT_COMPAT, 'UTF-8');
            }

            if (!empty($row->venue)) {
                $venue = htmlspecialchars((string) $row->venue, ENT_COMPAT, 'UTF-8');

                if (!empty($row->venueslug)) {
                    $venue = '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_(JemHelperRoute::getVenueRoute($row->venueslug), false)), ENT_COMPAT, 'UTF-8') . '">' . $venue . '</a>';
                }

                $meta[] = '&#9679; ' . $venue;
            }

            if (!empty($row->city)) {
                $meta[] = '&#9638; ' . htmlspecialchars((string) $row->city, ENT_COMPAT, 'UTF-8');
            }

            if (!empty($row->state)) {
                $meta[] = '&#9636; ' . htmlspecialchars((string) $row->state, ENT_COMPAT, 'UTF-8');
            }

            $categories = self::buildPdfCategoryLinks((array) ($row->categories ?? array()));

            if ($categories !== '') {
                $meta[] = '&#9670; ' . $categories;
            }

            $titleHtml = htmlspecialchars($eventTitle, ENT_COMPAT, 'UTF-8');

            if ($eventUrl !== '') {
                $titleHtml = '<a class="jem-pdf-event-title" href="' . htmlspecialchars($eventUrl, ENT_COMPAT, 'UTF-8') . '">' . $titleHtml . '</a>';
            }

            $titleHtml .= self::buildPdfEventIndicators($row);

            $html[] = '<div class="jem-pdf-event">'
                . '<div>' . $titleHtml . '</div>'
                . '<div class="jem-pdf-event-meta">' . implode(' &nbsp; ', $meta) . '</div>'
                . '</div>';
        }

        $html[] = '</div>';
        $footer = self::buildViewTextBlock('footer');

        if ($footer !== '') {
            $html[] = $footer;
        }

        return implode("\n", $html);
    }

    private static function buildPdfCategoryLinks(array $categories): string
    {
        $links = array();

        foreach ($categories as $category) {
            $name = trim((string) ($category->catname ?? ''));

            if ($name === '') {
                continue;
            }

            $label = htmlspecialchars($name, ENT_COMPAT, 'UTF-8');
            $slug = $category->catslug ?? $category->slug ?? $category->id ?? '';

            if ($slug !== '') {
                $label = '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_(JemHelperRoute::getCategoryRoute($slug), false)), ENT_COMPAT, 'UTF-8') . '">' . $label . '</a>';
            }

            $links[] = $label;
        }

        return implode(', ', $links);
    }

    private static function buildPdfEventLink($row): array
    {
        $title = htmlspecialchars((string) ($row->title ?? ''), ENT_COMPAT, 'UTF-8');

        if ($title === '' || empty($row->slug)) {
            return array('html' => $title);
        }

        return array('html' => '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_(JemHelperRoute::getEventRoute($row->slug), false)), ENT_COMPAT, 'UTF-8') . '">' . $title . '</a>' . self::buildPdfEventIndicators($row));
    }

    private static function buildPdfVenueLink($row): array
    {
        $venue = htmlspecialchars((string) ($row->venue ?? ''), ENT_COMPAT, 'UTF-8');
        $slug = $row->venueslug ?? '';

        if ($slug === '' && !empty($row->venue_id)) {
            $slug = (int) $row->venue_id . (!empty($row->venue_alias) ? ':' . $row->venue_alias : '');
        }

        if ($venue === '' || $slug === '') {
            return array('html' => $venue);
        }

        return array('html' => '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_(JemHelperRoute::getVenueRoute($slug), false)), ENT_COMPAT, 'UTF-8') . '">' . $venue . '</a>');
    }

    private static function buildPdfVenueListLink($row): array
    {
        $venue = htmlspecialchars((string) ($row->venue ?? $row->title ?? ''), ENT_COMPAT, 'UTF-8');
        $slug = $row->slug ?? $row->venueslug ?? '';

        if ($venue === '' || $slug === '') {
            return array('html' => $venue);
        }

        return array('html' => '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_(JemHelperRoute::getVenueRoute($slug), false)), ENT_COMPAT, 'UTF-8') . '">' . $venue . '</a>');
    }

    private static function buildPdfExternalLink(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return array('html' => '');
        }

        $href = preg_match('#^https?://#i', $url) ? $url : 'https://' . $url;

        return array('html' => '<a href="' . htmlspecialchars($href, ENT_COMPAT, 'UTF-8') . '">' . htmlspecialchars($url, ENT_COMPAT, 'UTF-8') . '</a>');
    }

    private static function buildPdfMapLink($row, string $mapProvider): array
    {
        $lat = trim((string) ($row->latitude ?? ''));
        $lng = trim((string) ($row->longitude ?? ''));

        if ($lat === '' || $lng === '') {
            return array('html' => '');
        }

        $coords = $lat . ',' . $lng;

        if ($mapProvider === 'google') {
            $url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($coords);
        } else {
            $url = 'https://www.openstreetmap.org/?mlat=' . rawurlencode($lat)
                . '&mlon=' . rawurlencode($lng)
                . '&zoom=15#map=15/' . rawurlencode($lat) . '/' . rawurlencode($lng);
        }

        return array('html' => '<a href="' . htmlspecialchars($url, ENT_COMPAT, 'UTF-8') . '">' . Text::_('COM_JEM_MAP') . '</a>');
    }

    private static function buildPdfEventIndicators($row): string
    {
        $html = '';

        if (!empty($row->featured)) {
            $html .= ' <span class="jem-pdf-muted">!</span>';
        }

        if (!empty($row->type_name)) {
            $color = trim((string) ($row->type_color ?? ''));

            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#6b7280';
            }

            $textColor = self::getContrastingTextColor($color);
            $badge = '<span class="jem-pdf-badge" style="background-color:' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8') . '; color:' . $textColor . ';">' . htmlspecialchars((string) $row->type_name, ENT_COMPAT, 'UTF-8') . '</span>';

            if (!empty($row->type_id)) {
                $slug = (int) $row->type_id . (!empty($row->type_alias) ? ':' . $row->type_alias : '');
                $badge = '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_(JemHelperRoute::getTypeeventsRoute($slug), false)), ENT_COMPAT, 'UTF-8') . '">' . $badge . '</a>';
            }

            $html .= ' ' . $badge;
        }

        return $html;
    }

    private static function getContrastingTextColor(string $background): string
    {
        if (!preg_match('/^#?([0-9a-fA-F]{6})$/', $background, $matches)) {
            return '#ffffff';
        }

        $hex = $matches[1];
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance > 145 ? '#111827' : '#ffffff';
    }

    /**
     * Builds the optional menu/view intro or footer text for PDFs.
     */
    public static function buildViewTextBlock(string $position): string
    {
        $settings = JemHelper::config();

        if ((int) ($settings->pdf_include_view_text ?? 1) !== 1) {
            return '';
        }

        $position = strtolower($position);
        $showKey = $position === 'footer' ? 'showfootertext' : 'showintrotext';
        $textKey = $position === 'footer' ? 'footertext' : 'introtext';
        $class = $position === 'footer' ? 'jem-pdf-view-footer-text' : 'jem-pdf-view-intro';
        $params = Factory::getApplication()->getParams();

        if (!$params || !$params->get($showKey)) {
            return '';
        }

        $text = trim((string) $params->get($textKey, ''));

        if ($text === '' || trim(strip_tags($text)) === '') {
            return '';
        }

        return '<div class="' . $class . '">' . self::normaliseEditorHtmlForPdf($text) . '</div>';
    }

    /**
     * Keeps trusted editor HTML usable in downloaded PDFs.
     */
    private static function normaliseEditorHtmlForPdf(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
        $html = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html);

        return preg_replace_callback(
            '#\s(href|src)=([\'"])(.*?)\2#i',
            static function (array $matches): string {
                $attribute = strtolower($matches[1]);
                $quote = $matches[2];
                $url = html_entity_decode(trim($matches[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if ($url === '' || preg_match('#^(?:https?:|mailto:|tel:|data:|#)#i', $url)) {
                    return ' ' . $attribute . '=' . $quote . htmlspecialchars($url, ENT_COMPAT, 'UTF-8') . $quote;
                }

                return ' ' . $attribute . '=' . $quote . htmlspecialchars(self::absoluteUrl($url), ENT_COMPAT, 'UTF-8') . $quote;
            },
            $html
        );
    }

    private static function getMargins($settings): array
    {
        $profile = (string) ($settings->pdf_margin_profile ?? 'medium');
        $profiles = array(
            'none' => array('top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0),
            'small' => array('top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8),
            'medium' => array('top' => 14, 'right' => 14, 'bottom' => 14, 'left' => 14),
            'large' => array('top' => 22, 'right' => 22, 'bottom' => 22, 'left' => 22),
        );

        if ($profile !== 'custom' && isset($profiles[$profile])) {
            return $profiles[$profile];
        }

        return array(
            'top' => max(0, min(50, (int) ($settings->pdf_margin_top ?? 14))),
            'right' => max(0, min(50, (int) ($settings->pdf_margin_right ?? 14))),
            'bottom' => max(0, min(50, (int) ($settings->pdf_margin_bottom ?? 14))),
            'left' => max(0, min(50, (int) ($settings->pdf_margin_left ?? 14))),
        );
    }

    private static function getProfilePaperSettings($settings, string $profile): array
    {
        $profile = strtolower($profile);
        $sizeKey = 'pdf_' . $profile . '_paper_size';
        $orientationKey = 'pdf_' . $profile . '_orientation';

        if ($profile === 'calendar') {
            $sizeKey = 'pdf_paper_size';
            $orientationKey = 'pdf_orientation';
        }

        return array(
            'size' => self::normalisePaperSize((string) ($settings->{$sizeKey} ?? $settings->pdf_paper_size ?? 'A4')),
            'orientation' => self::normaliseOrientation((string) ($settings->{$orientationKey} ?? $settings->pdf_orientation ?? ($profile === 'calendar' ? 'L' : 'P'))),
        );
    }

    private static function getPdfFontFamily($settings, string $key): string
    {
        $font = strtolower((string) ($settings->{$key} ?? 'helvetica'));
        $fonts = array(
            'helvetica',
            'times',
            'courier',
            'dejavusans',
            'dejavuserif',
            'dejavusansmono',
            'freesans',
            'freeserif',
            'freemono',
        );

        return in_array($font, $fonts, true) ? $font : 'helvetica';
    }

    private static function htmlToPlainText(string $value): string
    {
        $value = str_replace(array('<br>', '<br/>', '<br />'), "\n", $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace("/[ \t]+/", ' ', $value);
        $value = preg_replace("/ *\n */", "\n", $value);

        return trim((string) $value);
    }

    private static function absoluteUrl(string $url): string
    {
        if ($url === '' || $url === '#') {
            return '';
        }

        if (preg_match('#^(?:https?:)?//#i', $url)) {
            return $url;
        }

        if ($url[0] === '/') {
            $uri = Uri::getInstance();
            $port = $uri->getPort() ? ':' . $uri->getPort() : '';

            return $uri->getScheme() . '://' . $uri->getHost() . $port . $url;
        }

        return rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
    }

    private static function normalisePaperSize(string $paper): string
    {
        $paper = strtoupper($paper);

        return in_array($paper, array('A4', 'A3', 'A2', 'A1', 'LETTER'), true) ? $paper : 'A4';
    }

    private static function normaliseOrientation(string $orientation): string
    {
        $orientation = strtoupper($orientation);

        return in_array($orientation, array('P', 'L'), true) ? $orientation : 'P';
    }
}

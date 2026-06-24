<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Raw: Annual Calendar
 */
class JemViewAnnualcalendar extends HtmlView
{
    /**
     * Creates the iCalendar output for the Annual Calendar view.
     */
    public function display($tpl = null)
    {
        $settings  = JemHelper::config();
        $settings2 = JemHelper::globalattribs();
        $app       = Factory::getApplication();
        $jinput    = $app->input;
        $params    = $app->getParams();
        $layout    = $jinput->getCmd('layout', '');

        $year       = (int) $jinput->getInt('yearID', date('Y'));
        $startMonth = max(1, min(12, (int) $params->get('annual_start_month', 1)));
        $periodStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));
        $periodEnd   = $periodStart->modify('+12 months -1 day');

        if ($layout === 'pdf') {
            $this->renderPdf($periodStart, $periodEnd, $params);

            return;
        }

        if ($settings2->get('global_show_ical_icon', '0') == 1) {
            $model = $this->getModel();
            $model->setState('list.start', 0);
            $model->setState('list.limit', $settings->ical_max_items);

            $rows = $model->getItems();

            $vcal = JemHelper::getCalendarTool();
            $filename = 'jem_events_year_' . $periodStart->format('Ymd') . '_' . $periodEnd->format('Ymd') . '.ics';

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    JemHelper::icalAddEvent($vcal, $row);
                }
            }

            $vcal->returnCalendar(false, false, true, $filename);
        }
    }

    /**
     * Creates the Annual Calendar PDF.
     */
    private function renderPdf(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd, $params): void
    {
        if (!class_exists('JemPdf', false) || !JemPdf::isAvailable()) {
            $this->appClose();

            return;
        }

        $model = $this->getModel();
        $model->setState('list.start', 0);
        $model->setState('list.limit', 0);

        $rows = $model->getItems();
        $categoryLegend = array();
        $eventsByDate = $this->buildEventsByDate($rows, $periodStart, $periodEnd, $categoryLegend);
        $specialDaysByDate = JemHelper::calendarSpecialDays($periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d'));
        $legend = (int) $params->get('show_types_of_days_legend', 1) === 1
            ? JemHelper::calendarSpecialDayLegend($periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d'))
            : array();

        $periodTitle = (int) $periodStart->format('n') === 1
            ? $periodStart->format('Y')
            : $periodStart->format('Y') . ' / ' . $periodEnd->format('Y');
        $title = Text::_('COM_JEM_ANNUALCALENDAR_VIEW_DEFAULT_TITLE') . ' ' . $periodTitle;

        $jemsettings = JemHelper::config();
        $paperSize = $this->normalisePdfPaperSize((string) ($jemsettings->pdf_annual_paper_size ?? 'A4'));
        $orientation = $this->normalisePdfOrientation((string) ($jemsettings->pdf_annual_orientation ?? 'L'));
        $showDayTypesLegend = (int) ($jemsettings->pdf_annual_show_day_types_legend ?? 1) === 1;
        $showCategoriesLegend = (int) ($jemsettings->pdf_annual_show_categories_legend ?? 1) === 1;
        $eventTitlesMode = (string) ($jemsettings->pdf_annual_event_titles ?? 'auto');
        $showEventTitles = $eventTitlesMode === '1' || ($eventTitlesMode === 'auto' && in_array($paperSize, array('A2', 'A1'), true));
        $eventLimit = max(1, min(12, (int) ($jemsettings->pdf_annual_event_limit ?? 6)));
        $columnGap = max(0, min(10, (float) ($jemsettings->pdf_annual_column_gap ?? 1)));
        $rowGap = max(0, min(10, (float) ($jemsettings->pdf_annual_row_gap ?? 1)));
        $monthMatrix = $this->normalisePdfMonthMatrix((string) ($jemsettings->pdf_annual_month_matrix ?? 'auto'));
        $verticalAlign = $this->normalisePdfVerticalAlign((string) ($jemsettings->pdf_annual_vertical_align ?? 'top'));
        $pdf = JemPdf::createDocument($title, $orientation, $paperSize);

        if (!$pdf) {
            $this->appClose();

            return;
        }

        $margins = JemPdf::fitSinglePageMargins(array('top' => 6, 'right' => 5, 'bottom' => 5, 'left' => 5), $paperSize);
        $posterScale = JemPdf::getPosterScale($paperSize);
        $pdf->SetMargins($margins['left'], $margins['top'], $margins['right']);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML($this->buildPdfHtml($title, $periodStart, $eventsByDate, $specialDaysByDate, $legend, $categoryLegend, $params, $orientation, $showEventTitles, $eventLimit, $paperSize, $columnGap, $rowGap, $monthMatrix, $verticalAlign), true, false, true, false, '');
        if ($showDayTypesLegend || $showCategoriesLegend) {
            $pdf->setPage(1);
            $pdf->SetY(-max(18, (int) round(18 * $posterScale)));
            $pdf->writeHTML(
                $this->buildPdfCompactLegendHtml(
                    $showDayTypesLegend ? $legend : array(),
                    $showCategoriesLegend ? $categoryLegend : array(),
                    $showDayTypesLegend,
                    $showCategoriesLegend
                ),
                true,
                false,
                true,
                false,
                ''
            );
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output('jem-annual-calendar-' . $periodStart->format('Ymd') . '-' . $periodEnd->format('Ymd') . '.pdf', 'D');
        $this->appClose();
    }

    /**
     * Builds event markers for every date covered by the annual period.
     */
    private function buildEventsByDate(array $rows, DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd, array &$categoryLegend): array
    {
        $eventsByDate = array();

        foreach ($rows as $row) {
            if (!JemHelper::isValidDate($row->dates) || empty($row->user_has_access_event)) {
                continue;
            }

            if (isset($row->multi) && $row->multi !== 'first') {
                continue;
            }

            $eventStartDate = new DateTimeImmutable($row->dates);
            $eventEndDate = JemHelper::isValidDate($row->enddates) && $row->enddates >= $row->dates
                ? new DateTimeImmutable($row->enddates)
                : $eventStartDate;
            $eventStartDate = $eventStartDate < $periodStart ? $periodStart : $eventStartDate;
            $eventEndDate = $eventEndDate > $periodEnd ? $periodEnd : $eventEndDate;
            $categoryColors = array();

            foreach ((array) $row->categories as $category) {
                $categoryColor = trim((string) ($category->color ?? ''));
                $categoryId = (int) ($category->id ?? 0);
                $categoryName = trim((string) ($category->catname ?? ''));
                $validCategoryColor = $categoryColor !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $categoryColor);

                if ($validCategoryColor) {
                    $categoryColors[$categoryColor] = $categoryColor;
                }

                if ($categoryName !== '') {
                    $legendKey = $categoryId > 0 ? (string) $categoryId : strtolower($categoryName);

                    if (!isset($categoryLegend[$legendKey])) {
                        $categoryLegend[$legendKey] = array(
                            'title' => $categoryName,
                            'color' => $validCategoryColor ? $categoryColor : '',
                        );
                    }
                }
            }

            for ($eventDate = $eventStartDate; $eventDate <= $eventEndDate; $eventDate = $eventDate->modify('+1 day')) {
                $eventsByDate[$eventDate->format('Y-m-d')][] = array(
                    'title' => $row->title,
                    'link' => !empty($row->slug) ? $this->buildAbsoluteUrl(Route::_(JemHelperRoute::getEventRoute($row->slug), false)) : '',
                    'colors' => array_values($categoryColors),
                    'is_multiday' => $eventEndDate > $eventStartDate,
                );
            }
        }

        return $eventsByDate;
    }

    /**
     * Normalises PDF paper size.
     */
    private function normalisePdfPaperSize(string $paper): string
    {
        $paper = strtoupper($paper);

        return in_array($paper, array('A4', 'A3', 'A2', 'A1', 'LETTER'), true) ? $paper : 'A4';
    }

    /**
     * Normalises PDF page orientation.
     */
    private function normalisePdfOrientation(string $orientation): string
    {
        $orientation = strtoupper($orientation);

        return in_array($orientation, array('P', 'L'), true) ? $orientation : 'L';
    }

    /**
     * Normalises the annual PDF month matrix.
     */
    private function normalisePdfMonthMatrix(string $matrix): string
    {
        $matrix = strtolower(str_replace(array(' ', ','), array('', 'x'), trim($matrix)));

        return in_array($matrix, array('auto', '1x12', '2x6', '3x4', '4x3', '6x2', '12x1'), true) ? $matrix : 'auto';
    }

    /**
     * Normalises the annual PDF month block vertical alignment.
     */
    private function normalisePdfVerticalAlign(string $align): string
    {
        $align = strtolower(trim($align));

        if ($align === 'center') {
            $align = 'middle';
        }

        return in_array($align, array('top', 'middle', 'bottom'), true) ? $align : 'top';
    }

    /**
     * Builds the TCPDF-compatible HTML for the annual calendar.
     */
    private function buildPdfHtml(
        string $title,
        DateTimeImmutable $periodStart,
        array $eventsByDate,
        array $specialDaysByDate,
        array $legend,
        array $categoryLegend,
        $params,
        string $orientation,
        bool $showEventTitles,
        int $eventLimit,
        string $paperSize,
        float $columnGap,
        float $rowGap,
        string $monthMatrix,
        string $verticalAlign
    ): string {
        $firstWeekDay = (int) $params->get('firstweekday', 1);
        $markerLimit = $showEventTitles
            ? max(1, min(12, $eventLimit))
            : max(1, (int) $params->get('annual_marker_limit', $eventLimit));
        $orientation = $this->normalisePdfOrientation($orientation);
        $monthMatrix = $this->normalisePdfMonthMatrix($monthMatrix);
        $verticalAlign = $this->normalisePdfVerticalAlign($verticalAlign);
        $monthColumns = $orientation === 'P' ? 3 : 4;
        $monthRows = (int) ceil(12 / $monthColumns);

        if ($monthMatrix !== 'auto') {
            [$monthColumns, $monthRows] = array_map('intval', explode('x', $monthMatrix, 2));
        }

        $monthCellWidth = number_format(100 / $monthColumns, 4, '.', '') . '%';
        $posterScale = JemPdf::getPosterScale($paperSize);
        $titleFontSize = round(12 * $posterScale, 1);
        $monthTitleFontSize = round(7.5 * $posterScale, 1);
        $cellFontSize = round(5.4 * $posterScale, 1);
        $headerFontSize = round(5.2 * $posterScale, 1);
        $cellHeight = round(6.2 * $posterScale, 1);
        $markerFontSize = round(5.4 * $posterScale, 1);
        $markerHeight = round(1.9 * $posterScale, 1);
        $legendFontSize = round(6.2 * $posterScale, 1);
        $monthHorizontalPadding = number_format($columnGap / 2, 2, '.', '');
        $monthVerticalPadding = number_format($rowGap / 2, 2, '.', '');
        $weekdays = $firstWeekDay === 0
            ? array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT')
            : array('MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN');

        $html = array();
        $html[] = '<style>
            h1 { font-size: ' . $titleFontSize . 'pt; margin: 0 0 2px 0; }
            h2 { font-size: ' . $monthTitleFontSize . 'pt; margin: 0 0 1px 0; }
            table { border-collapse: collapse; }
            .jem-pdf-month-cell { padding: ' . $monthVerticalPadding . 'mm ' . $monthHorizontalPadding . 'mm; vertical-align: ' . $verticalAlign . '; }
            .jem-pdf-month { width: 99%; }
            .jem-pdf-month th { background-color: #e5e7eb; border: 0.2mm solid #9ca3af; font-size: ' . $headerFontSize . 'pt; font-weight: bold; text-align: center; }
            .jem-pdf-month td { border: 0.2mm solid #9ca3af; font-size: ' . $cellFontSize . 'pt; height: ' . $cellHeight . 'mm; vertical-align: top; }
            .jem-pdf-week { background-color: #f3f4f6; color: #4b5563; text-align: center; }
            .jem-pdf-empty { background-color: #f9fafb; }
            .jem-pdf-day-number { font-weight: bold; }
            .jem-pdf-markers td { border: none; font-size: ' . $markerFontSize . 'pt; height: ' . $markerHeight . 'mm; line-height: ' . $markerHeight . 'mm; text-align: center; vertical-align: middle; }
            .jem-pdf-legend { margin-top: 4px; }
            .jem-pdf-legend th { background-color: #e5e7eb; border: 0.2mm solid #cbd5e1; font-size: ' . $legendFontSize . 'pt; font-weight: bold; }
            .jem-pdf-legend td { border: 0.2mm solid #d1d5db; font-size: ' . $legendFontSize . 'pt; }
            .jem-pdf-compact-legend td { font-size: ' . $legendFontSize . 'pt; vertical-align: middle; }
            .jem-pdf-event-marker-legend { font-size: ' . $legendFontSize . 'pt; margin-top: 1mm; text-align: center; }
            .jem-pdf-view-intro, .jem-pdf-view-footer-text { font-size: ' . $legendFontSize . 'pt; line-height: ' . ($legendFontSize + 2) . 'pt; }
            .jem-pdf-view-intro { margin-bottom: 2mm; }
            .jem-pdf-view-footer-text { margin-top: 2mm; border-top: 0.2mm solid #d1d5db; padding-top: 1mm; }
            a { color: #1f5b99; text-decoration: underline; }
        </style>';
        $html[] = '<h1>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h1>';
        $intro = JemPdfView::buildViewTextBlock('intro');

        if ($intro !== '') {
            $html[] = $intro;
        }

        $html[] = '<table width="100%" cellpadding="1" cellspacing="0">';

        for ($row = 0; $row < $monthRows; $row++) {
            $html[] = '<tr>';

            for ($column = 0; $column < $monthColumns; $column++) {
                $monthIndex = ($row * $monthColumns) + $column;

                if ($monthIndex >= 12) {
                    $html[] = '<td class="jem-pdf-month-cell" width="' . $monthCellWidth . '">&nbsp;</td>';
                    continue;
                }

                $monthDate = $periodStart->modify('+' . $monthIndex . ' months');
                $html[] = '<td class="jem-pdf-month-cell" width="' . $monthCellWidth . '">' . $this->buildPdfMonthHtml($monthDate, $firstWeekDay, $weekdays, $eventsByDate, $specialDaysByDate, $markerLimit, $showEventTitles) . '</td>';
            }

            $html[] = '</tr>';
        }

        $html[] = '</table>';
        $footer = JemPdfView::buildViewTextBlock('footer');

        if ($footer !== '') {
            $html[] = $footer;
        }

        return implode("\n", $html);
    }

    /**
     * Builds one month table.
     */
    private function buildPdfMonthHtml(
        DateTimeImmutable $monthDate,
        int $firstWeekDay,
        array $weekdays,
        array $eventsByDate,
        array $specialDaysByDate,
        int $markerLimit,
        bool $showEventTitles
    ): string {
        $monthStart = $monthDate->modify('first day of this month');
        $monthNumber = (int) $monthStart->format('n');
        $daysInMonth = (int) $monthStart->format('t');
        $weekday = (int) $monthStart->format('w');
        $offset = $firstWeekDay === 0 ? $weekday : ($weekday === 0 ? 6 : $weekday - 1);
        $gridStart = $monthStart->modify('-' . $offset . ' days');
        $cellCount = (int) (ceil(($offset + $daysInMonth) / 7) * 7);
        $html = array();

        $html[] = '<h2>' . HTMLHelper::_('date', $monthStart->format('Y-m-d'), 'F Y') . '</h2>';
        $weekWidth = '7%';
        $dayWidth = '13%';

        $html[] = '<table class="jem-pdf-month" width="99%" cellpadding="1" cellspacing="0">';
        $html[] = '<tr><th class="jem-pdf-week" width="' . $weekWidth . '">' . Text::_('COM_JEM_WKCAL_WEEK') . '</th>';

        foreach ($weekdays as $weekdayLabel) {
            $html[] = '<th width="' . $dayWidth . '">' . Text::_($weekdayLabel) . '</th>';
        }

        $html[] = '</tr>';

        for ($cell = 0; $cell < $cellCount; $cell++) {
            $cellDate = $gridStart->modify('+' . $cell . ' days');

            if ($cell % 7 === 0) {
                $weekDate = $firstWeekDay === 0 ? $cellDate->modify('+1 day') : $cellDate;
                $html[] = '<tr><td class="jem-pdf-week" width="' . $weekWidth . '">' . (int) $weekDate->format('W') . '</td>';
            }

            if ((int) $cellDate->format('n') !== $monthNumber) {
                $html[] = '<td class="jem-pdf-day jem-pdf-empty" width="' . $dayWidth . '">&nbsp;</td>';
            } else {
                $date = $cellDate->format('Y-m-d');
                $events = $eventsByDate[$date] ?? array();
                $specialDays = $specialDaysByDate[$date] ?? array();
                $specialColor = $this->getSpecialDayColor($specialDays);
                $style = $specialColor !== '' ? ' style="background-color:' . htmlspecialchars($specialColor, ENT_COMPAT, 'UTF-8') . ';"' : '';
                $markers = $this->buildPdfEventMarkers($events, $markerLimit, $showEventTitles);

                $html[] = '<td class="jem-pdf-day" width="' . $dayWidth . '"' . $style . '><span class="jem-pdf-day-number">' . (int) $cellDate->format('j') . '</span><br />' . $markers . '</td>';
            }

            if ($cell % 7 === 6) {
                $html[] = '</tr>';
            }
        }

        $html[] = '</table>';

        return implode('', $html);
    }

    /**
     * Builds compact event markers for a PDF day cell.
     */
    private function buildPdfEventMarkers(array $events, int $markerLimit, bool $showEventTitles): string
    {
        if (!$events) {
            return '&nbsp;';
        }

        $markers = array();
        $shownMarkers = 0;
        $eventCount = count($events);
        $slotLimit = min(max(1, $markerLimit), 6);
        $visibleMarkerLimit = $eventCount > $slotLimit ? max(0, $slotLimit - 1) : $slotLimit;

        foreach ($events as $event) {
            if ($shownMarkers >= $visibleMarkerLimit) {
                break;
            }

            $hasCategoryColor = !empty($event['colors']);
            $color = $hasCategoryColor ? reset($event['colors']) : '#111827';
            $marker = !empty($event['is_multiday'])
                ? ($hasCategoryColor ? '&#9632;' : '&#9633;')
                : ($hasCategoryColor ? '&#9679;' : '&#9675;');
            $markerHtml = '<span style="color:' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8') . ';">' . $marker . '</span>';

            if ($showEventTitles) {
                $title = trim((string) ($event['title'] ?? ''));
                $link = trim((string) ($event['link'] ?? ''));

                if ($title !== '') {
                    $titleHtml = htmlspecialchars($this->truncatePdfEventTitle($title, 18), ENT_COMPAT, 'UTF-8');

                    if ($link !== '') {
                        $titleHtml = '<a href="' . htmlspecialchars($link, ENT_COMPAT, 'UTF-8') . '">' . $titleHtml . '</a>';
                    }

                    $markerHtml .= ' ' . $titleHtml;
                }
            }

            $markers[] = $markerHtml;
            $shownMarkers++;
        }

        $hiddenMarkers = $eventCount - $shownMarkers;

        if ($hiddenMarkers > 0) {
            $markers[] = '+' . (int) $hiddenMarkers;
        }

        if ($showEventTitles) {
            $html = array();
            $html[] = '<table class="jem-pdf-markers" width="100%" cellpadding="0" cellspacing="0">';

            foreach ($markers as $marker) {
                $html[] = '<tr><td width="100%" align="left">' . $marker . '</td></tr>';
            }

            $html[] = '</table>';

            return implode('', $html);
        }

        $rows = array_chunk($markers, 3);
        $html = array();
        $html[] = '<table class="jem-pdf-markers" width="100%" cellpadding="0" cellspacing="0">';

        foreach ($rows as $row) {
            $html[] = '<tr>';

            foreach ($row as $marker) {
                $html[] = '<td width="33%">' . $marker . '</td>';
            }

            for ($i = count($row); $i < 3; $i++) {
                $html[] = '<td width="33%">&nbsp;</td>';
            }

            $html[] = '</tr>';
        }

        for ($i = count($rows); $i < 2; $i++) {
            $html[] = '<tr><td width="33%">&nbsp;</td><td width="33%">&nbsp;</td><td width="33%">&nbsp;</td></tr>';
        }

        $html[] = '</table>';

        return implode('', $html);
    }

    /**
     * Truncates an annual PDF event title for day cells.
     */
    private function truncatePdfEventTitle(string $title, int $limit): string
    {
        $title = trim($title);

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($title, 'UTF-8') > $limit
                ? mb_substr($title, 0, max(1, $limit - 3), 'UTF-8') . '...'
                : $title;
        }

        return strlen($title) > $limit ? substr($title, 0, max(1, $limit - 1)) . '...' : $title;
    }

    /**
     * Builds an absolute URL for links embedded in downloaded PDFs.
     */
    private function buildAbsoluteUrl(string $url): string
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

    /**
     * Returns the primary special day colour for a date.
     */
    private function getSpecialDayColor(array $specialDays): string
    {
        if (!$specialDays) {
            return '';
        }

        $primary = reset($specialDays);
        $color = (string) ($primary['color'] ?? '');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#d1d5db';
    }

    /**
     * Builds the Types of Days table included below the PDF calendar.
     */
    private function buildPdfLegendHtml(array $legend): string
    {
        $html = array();
        $html[] = '<h2 class="jem-pdf-legend-title">' . Text::_('COM_JEM_CALENDAR_TYPES_OF_DAYS_APPLIED') . '</h2>';
        $html[] = '<table class="jem-pdf-legend" width="100%" cellpadding="2" cellspacing="0">';
        $html[] = '<tr>'
            . '<th width="8%">' . Text::_('COM_JEM_CALENDAR_TYPE_OF_DAY_COLOR') . '</th>'
            . '<th width="22%">' . Text::_('COM_JEM_CALENDAR_TYPE_OF_DAY_TYPE') . '</th>'
            . '<th width="28%">' . Text::_('COM_JEM_CALENDAR_TYPE_OF_DAY_SPECIAL_DAYS') . '</th>'
            . '<th width="42%">' . Text::_('COM_JEM_CALENDAR_TYPE_OF_DAY_DESCRIPTION') . '</th>'
            . '</tr>';

        foreach ($legend as $legendItem) {
            $color = htmlspecialchars($legendItem['color'], ENT_COMPAT, 'UTF-8');
            $title = (string) ($legendItem['title'] ?? '');

            if (strcasecmp($title, (string) ($legendItem['type'] ?? '')) === 0) {
                $title = '';
            }

            $html[] = '<tr>'
                . '<td width="8%" style="background-color:' . $color . ';">&nbsp;</td>'
                . '<td width="22%">' . htmlspecialchars($legendItem['type'], ENT_COMPAT, 'UTF-8') . '</td>'
                . '<td width="28%">' . ($title !== '' ? htmlspecialchars($title, ENT_COMPAT, 'UTF-8') : '-') . '</td>'
                . '<td width="42%">' . (trim((string) $legendItem['description']) !== '' ? htmlspecialchars($legendItem['description'], ENT_COMPAT, 'UTF-8') : '-') . '</td>'
                . '</tr>';
        }

        $html[] = '</table>';

        return implode("\n", $html);
    }

    /**
     * Builds a compact colour key for day types and event categories.
     */
    private function buildPdfCompactLegendHtml(array $dayTypeLegend, array $categoryLegend, bool $showDayTypes, bool $showCategories): string
    {
        $html = array();
        $html[] = '<table class="jem-pdf-compact-legend" width="100%" cellpadding="1" cellspacing="0" style="border-top:0.2mm solid #9ca3af;border-bottom:0.2mm solid #9ca3af;">';
        $html[] = '<tr>';
        $html[] = '<td width="50%" align="left">';
        if ($showDayTypes) {
            $html[] = '<strong>' . Text::_('COM_JEM_CALENDAR_TYPES_OF_DAYS') . '</strong> '
                . $this->buildPdfLegendItems($dayTypeLegend, 3, 3);
        } else {
            $html[] = '&nbsp;';
        }
        $html[] = '</td>';
        $html[] = '<td width="50%" align="right">';
        if ($showCategories) {
            $html[] = '<strong>' . Text::_('COM_JEM_CATEGORIES') . '</strong> '
                . $this->buildPdfLegendItems($categoryLegend, 4, 3);
        } else {
            $html[] = '&nbsp;';
        }
        $html[] = '</td>';
        $html[] = '</tr>';
        $html[] = '<tr><td colspan="2" align="center">' . $this->buildEventMarkerLegendHtml() . '</td></tr>';
        $html[] = '</table>';

        return implode('', $html);
    }

    private function buildEventMarkerLegendHtml(): string
    {
        return '&#9679; ' . Text::_('COM_JEM_ANNUALCALENDAR_EVENT_MARKER_ONE_DAY')
            . '&nbsp;&nbsp; &#9632; ' . Text::_('COM_JEM_ANNUALCALENDAR_EVENT_MARKER_MULTI_DAY');
    }

    /**
     * Builds compact legend items with colour swatches.
     */
    private function buildPdfLegendItems(array $items, int $itemsPerLine, int $maxLines): string
    {
        if (!$items) {
            return '-';
        }

        $lines = array();
        $lineItems = array();
        $shownItems = 0;
        $maxItems = max(1, $itemsPerLine) * max(1, $maxLines);

        foreach ($items as $item) {
            if ($shownItems >= $maxItems) {
                break;
            }

            $color = !empty($item['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $item['color'])
                ? (string) $item['color']
                : '#ffffff';
            $label = trim((string) ($item['type'] ?? $item['title'] ?? ''));

            if ($label === '') {
                continue;
            }

            $marker = $color === '#ffffff' ? '&#9633;' : '&#9632;';
            $lineItems[] = '<span style="color:' . htmlspecialchars($color === '#ffffff' ? '#111827' : $color, ENT_COMPAT, 'UTF-8') . ';">' . $marker . '</span> '
                . htmlspecialchars($label, ENT_COMPAT, 'UTF-8');
            $shownItems++;

            if (count($lineItems) >= $itemsPerLine) {
                $lines[] = implode(' &nbsp; ', $lineItems);
                $lineItems = array();
            }
        }

        if ($lineItems) {
            $lines[] = implode(' &nbsp; ', $lineItems);
        }

        $hiddenItems = count($items) - $shownItems;

        if ($hiddenItems > 0) {
            $lastLine = array_pop($lines);
            $lastLine = trim((string) $lastLine);
            $lines[] = ($lastLine !== '' ? $lastLine . ' &nbsp; ' : '') . '+' . (int) $hiddenItems;
        }

        return $lines ? implode('<br />', $lines) : '-';
    }

    /**
     * Closes the current Joomla application.
     */
    private function appClose(): void
    {
        Factory::getApplication()->close();
    }
}
?>

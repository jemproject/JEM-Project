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

/**
 * Shared PDF view helpers.
 */
class JemPdfView
{
    /**
     * Renders a simple event list PDF.
     */
    public static function renderEventList(string $title, array $rows, string $filename): void
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
            $filename
        );
    }

    /**
     * Renders a venue list PDF.
     */
    public static function renderVenueList(string $title, array $rows, string $filename): void
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
            $filename
        );
    }

    /**
     * Renders a special days list PDF.
     */
    public static function renderSpecialDays(string $title, array $rows, string $filename): void
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
            $filename
        );
    }

    /**
     * Renders a simple table PDF using the global PDF settings.
     */
    public static function renderTable(string $title, array $headers, array $rows, string $filename): void
    {
        if (!class_exists('JemPdf', false) || !JemPdf::isAvailable()) {
            Factory::getApplication()->close();

            return;
        }

        $settings = JemHelper::config();
        $paperSize = self::normalisePaperSize((string) ($settings->pdf_paper_size ?? 'A4'));
        $orientation = self::normaliseOrientation((string) ($settings->pdf_orientation ?? 'P'));
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
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML(self::buildTableHtml($title, $headers, $rows, $paperSize), true, false, true, false, '');

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
            $categories = array();

            foreach ((array) ($row->categories ?? array()) as $category) {
                $name = trim((string) ($category->catname ?? ''));

                if ($name !== '') {
                    $categories[] = $name;
                }
            }

            $tableRows[] = array(
                JemOutput::formatShortDateTime($row->dates ?? '', $row->times ?? '', $row->enddates ?? '', $row->endtimes ?? ''),
                (string) ($row->title ?? ''),
                (string) ($row->venue ?? ''),
                (string) ($row->city ?? ''),
                implode(', ', $categories),
            );
        }

        return $tableRows;
    }

    private static function buildVenueListRows(array $rows): array
    {
        $tableRows = array();

        foreach ($rows as $row) {
            $tableRows[] = array(
                (string) ($row->venue ?? $row->title ?? ''),
                (string) ($row->city ?? ''),
                (string) ($row->state ?? ''),
                (string) ($row->country ?? ''),
                (string) ($row->url ?? ''),
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

    private static function buildTableHtml(string $title, array $headers, array $rows, string $paperSize): string
    {
        $scale = JemPdf::getPosterScale($paperSize);
        $baseFontSize = max(7, min(14, (int) round(8 * $scale)));
        $titleFontSize = max(12, min(28, (int) round(15 * $scale)));
        $cellPadding = JemPdf::prefersSinglePage($paperSize) ? '1.2mm' : '1.6mm';
        $html = array();

        $html[] = '<style>
            h1 { font-size: ' . $titleFontSize . 'pt; margin: 0 0 4mm 0; }
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #e5e7eb; border: 0.2mm solid #9ca3af; font-size: ' . $baseFontSize . 'pt; font-weight: bold; padding: ' . $cellPadding . '; }
            td { border: 0.2mm solid #cbd5e1; font-size: ' . $baseFontSize . 'pt; padding: ' . $cellPadding . '; vertical-align: top; }
            .jem-pdf-empty { color: #6b7280; text-align: center; }
            .jem-pdf-footer { color: #6b7280; font-size: ' . max(6, $baseFontSize - 1) . 'pt; border-top: 0.2mm solid #d1d5db; padding-top: 2mm; }
        </style>';
        $html[] = '<h1>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h1>';
        $html[] = '<table cellpadding="2" cellspacing="0"><thead><tr>';

        foreach ($headers as $header) {
            $html[] = '<th>' . htmlspecialchars((string) $header, ENT_COMPAT, 'UTF-8') . '</th>';
        }

        $html[] = '</tr></thead><tbody>';

        if (!$rows) {
            $html[] = '<tr><td class="jem-pdf-empty" colspan="' . count($headers) . '">' . Text::_('COM_JEM_NO_EVENTS') . '</td></tr>';
        }

        foreach ($rows as $row) {
            $html[] = '<tr>';

            foreach ($row as $cell) {
                $html[] = '<td>' . nl2br(htmlspecialchars((string) $cell, ENT_COMPAT, 'UTF-8')) . '</td>';
            }

            $html[] = '</tr>';
        }

        $html[] = '</tbody></table>';
        $html[] = '<br /><div class="jem-pdf-footer">Powered by JEM</div>';

        return implode("\n", $html);
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

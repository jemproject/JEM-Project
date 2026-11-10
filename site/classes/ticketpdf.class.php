<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;

require_once __DIR__ . '/pdf.class.php';

/** Private reservation document. The QR holds only the opaque locator. */
final class JemTicketPdf
{
    /**
     * Render a reservation snapshot; current validity is always checked at the gate.
     * @param array $ticket Authorised snapshot returned by JemTicketService.
     * @param string $languageTag Recipient language, or the current interface language.
     * @return string In-memory PDF bytes, never a public file.
     */
    public static function render(array $ticket, string $languageTag = ''): string
    {
        $languageTag = $languageTag ?: Factory::getApplication()->getLanguage()->getTag();
        $language = new Language($languageTag);
        $language->load('com_jem', JPATH_SITE, 'en-GB', true);
        if ($languageTag !== 'en-GB') {
            $language->load('com_jem', JPATH_SITE, $languageTag, true);
        }
        $translate = static fn (string $key): string => $language->_($key);
        $pdf = JemPdf::createDocument($translate('COM_JEM_TK_DOCUMENT'), 'P', 'A5');
        if (!$pdf) {
            throw new RuntimeException('COM_JEM_TK_PDF_UNAVAILABLE');
        }
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', 'B', 15);
        $pdf->MultiCell(0, 8, $translate('COM_JEM_TK_DOCUMENT'), 0, 'L');
        $pdf->SetFont('dejavusans', '', 11);
        $pdf->MultiCell(0, 7, $ticket['event'], 0, 'L');
        $pdf->MultiCell(0, 6, trim(($ticket['date'] ?? '') . ' ' . substr($ticket['time'] ?? '', 0, 5)), 0, 'L');
        $pdf->MultiCell(0, 6, $ticket['holder'], 0, 'L');
        $pdf->MultiCell(0, 6, $translate('COM_JEM_TK_PLACES') . ': ' . (int) $ticket['places'], 0, 'L');
        foreach ($ticket['pools'] as $pool) {
            $name = $pool['name'] === 'COM_JEM_TK_GENERAL' ? $translate($pool['name']) : $pool['name'];
            $pdf->MultiCell(0, 6, $name . ': ' . (int) $pool['quantity'], 0, 'L');
        }
        $pdf->MultiCell(0, 6, $translate('COM_JEM_TK_PAYMENT_' . strtoupper($ticket['payment']['state'])), 0, 'L');
        $y = $pdf->GetY() + 3;
        if ($y > 120) {
            $pdf->AddPage();
            $y = 15;
        }
        $pdf->write2DBarcode($ticket['reference'], 'QRCODE,M', 49, $y, 50, 50,
            ['border' => 0, 'padding' => 2, 'fgcolor' => [0, 0, 0], 'bgcolor' => [255, 255, 255]], 'N');
        $pdf->SetY($y + 53);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->MultiCell(0, 6, $ticket['reference'], 0, 'C');
        $pdf->MultiCell(0, 5, $translate('COM_JEM_TK_PDF_NOTICE'), 0, 'L');
        return $pdf->Output('reservation.pdf', 'S');
    }
}

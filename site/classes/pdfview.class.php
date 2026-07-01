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
use Joomla\CMS\Http\HttpFactory;
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
    public static function renderEventList(string $title, array $rows, string $filename, string $profile = 'list', string $day = ''): void
    {
        $specialDaysHtml = '';
        if (JemHelper::isValidDate($day)) {
            $days = JemHelper::calendarSpecialDays($day, $day);
            $specialDaysHtml = '<div class="jem-pdf-day-date">' . htmlspecialchars(self::formatTimelineDayLabel($day), ENT_COMPAT, 'UTF-8') . '</div>'
                . self::buildSpecialDayBadgesHtml($days[$day] ?? array());
        }

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
            $profile,
            '',
            $specialDaysHtml
        );
    }

    /**
     * Renders the Events List PDF with links and event type badges.
     */
    public static function renderLinkedEventList(string $title, array $rows, string $filename, string $profile = 'list', string $preListHtml = ''): void
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
        $pdf->writeHTML(self::buildLinkedEventListHtml($title, $rows, $paperSize, $preListHtml), true, false, true, false, '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output($filename, 'D');
        Factory::getApplication()->close();
    }

    /**
     * Renders an event type page PDF with the same type header and event list
     * concept used by the frontend view.
     */
    public static function renderTypeEventList(string $title, $type, array $rows, string $filename, string $profile = 'list'): void
    {
        self::renderLinkedEventList($title, $rows, $filename, $profile, self::buildTypeHeaderHtml($type));
    }

    /**
     * Renders the Day Timeline PDF using a compact timeline layout.
     */
    public static function renderDayTimeline(string $title, array $rows, string $filename, string $day = '', $params = null): void
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
        $pdf->writeHTML(self::buildDayTimelineHtml($title, $rows, $day, $params, $paperSize), true, false, true, false, '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output($filename, 'D');
        Factory::getApplication()->close();
    }

    /**
     * Renders a venue list PDF.
     */
    public static function renderVenueList(string $title, array $rows, string $filename, string $profile = 'list', string $mapProvider = 'osm'): void
    {
        $isMapProfile = $profile === 'map';
        $headers = array(
            Text::_('COM_JEM_VENUE'),
            Text::_('COM_JEM_CITY'),
            Text::_('COM_JEM_STATE'),
            Text::_('COM_JEM_COUNTRY'),
            Text::_('COM_JEM_WEBSITE'),
        );

        if ($isMapProfile) {
            $headers[] = Text::_('COM_JEM_LATITUDE');
            $headers[] = Text::_('COM_JEM_LONGITUDE');
            $headers[] = Text::_('COM_JEM_MAP_LINK');
        }

        self::renderTable(
            $title,
            $headers,
            self::buildVenueListRows($rows, $isMapProfile, $mapProvider),
            $filename,
            $profile,
            '',
            $isMapProfile ? self::buildVenueMapPreviewHtml($rows, $mapProvider) : ''
        );
    }

    /**
     * Renders a venue type page PDF with the type header and venue table.
     */
    public static function renderTypeVenueList(string $title, $type, array $rows, string $filename, string $profile = 'list'): void
    {
        self::renderTable(
            $title,
            array(
                Text::_('COM_JEM_VENUE'),
                Text::_('COM_JEM_CITY'),
                Text::_('COM_JEM_STATE'),
                Text::_('COM_JEM_COUNTRY'),
                Text::_('COM_JEM_WEBSITE'),
                Text::_('COM_JEM_MAP_LINK'),
            ),
            self::buildVenueListRows($rows, false, 'osm', true),
            $filename,
            $profile,
            '',
            self::buildTypeHeaderHtml($type)
        );
    }

    /**
     * Renders a single category page PDF mirroring the category overview plus
     * the event list shown below it.
     */
    public static function renderCategoryDetail(string $title, $category, array $children, array $rows, string $description, string $filename, string $profile = 'list'): void
    {
        self::renderLinkedEventList($title, $rows, $filename, $profile, self::buildCategoryDetailIntroHtml($category, $children, $description));
    }

    /**
     * Renders the categories overview, including category type grouping when
     * that is the active frontend mode.
     */
    public static function renderCategoryList(string $title, array $rows, array $typeItems, string $filename, string $profile = 'list'): void
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
        $pdf->writeHTML(self::buildCategoryListHtml($title, $rows, $typeItems, $paperSize), true, false, true, false, '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output($filename, 'D');
        Factory::getApplication()->close();
    }

    /**
     * Renders a venue detail PDF mirroring the desktop venue page structure.
     */
    public static function renderVenueDetail(string $title, $venue, array $rows, $params, string $filename): void
    {
        if (!class_exists('JemPdf', false) || !JemPdf::isAvailable()) {
            Factory::getApplication()->close();

            return;
        }

        $settings = JemHelper::config();
        $paper = self::getProfilePaperSettings($settings, 'list');
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
        $pdf->writeHTML(self::buildVenueDetailHtml($title, $venue, $rows, $params, $paperSize), true, false, true, false, '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output($filename, 'D');
        Factory::getApplication()->close();
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
    public static function renderTable(string $title, array $headers, array $rows, string $filename, string $profile = 'list', string $filterSummary = '', string $preTableHtml = ''): void
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
        $pdf->writeHTML(self::buildTableHtml($title, $headers, $rows, $paperSize, $filterSummary, $preTableHtml), true, false, true, false, '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $pdf->Output($filename, 'D');
        Factory::getApplication()->close();
    }

    private static function buildVenueDetailHtml(string $title, $venue, array $rows, $params, string $paperSize): string
    {
        $scale = JemPdf::getPosterScale($paperSize);
        $settings = JemHelper::config();
        $titleFontFamily = self::getPdfFontFamily($settings, 'pdf_title_font_family');
        $headerFontFamily = self::getPdfFontFamily($settings, 'pdf_header_font_family');
        $bodyFontFamily = self::getPdfFontFamily($settings, 'pdf_body_font_family');
        $baseFontSize = max(7, min(14, (int) round((int) ($settings->pdf_base_font_size ?? 8) * $scale)));
        $headingFontSize = max(10, min(24, (int) round((int) ($settings->pdf_heading_font_size ?? 12) * $scale)));
        $titleFontSize = max(18, min(34, $headingFontSize + 10));
        $fallbackImageWidth = max(1, min(200, (int) ($settings->pdf_imagewidth ?? 40)));
        $fallbackImageHeight = max(1, min(200, (int) ($settings->pdf_imageheight ?? 40)));
        $venueImageWidth = max(1, min(200, (int) ($settings->pdf_venue_imagewidth ?? $fallbackImageWidth)));
        $venueImageHeight = max(1, min(200, (int) ($settings->pdf_venue_imageheight ?? $fallbackImageHeight)));
        $showImage = !$params || !method_exists($params, 'get') || (int) $params->get('venue_show_image', 1) === 1;
        $showDescription = (!$params || !method_exists($params, 'get') || (int) $params->get('venue_show_description', 1) === 1)
            && trim((string) ($venue->locdescription ?? '')) !== ''
            && trim((string) ($venue->locdescription ?? '')) !== '<br>';
        $showEvents = !$params || !method_exists($params, 'get') || (int) $params->get('venue_show_events', 1) === 1;
        $venueHeadingDisplay = $params && method_exists($params, 'get') ? (string) $params->get('venue_heading_display', 'label_name') : 'label_name';
        $venueHeadingDisplay = in_array($venueHeadingDisplay, array('label', 'label_name', 'name'), true) ? $venueHeadingDisplay : 'label_name';
        $mapDisplay = $params && method_exists($params, 'get') ? (string) $params->get('venue_map_display', 'link_button') : 'link_button';
        if ($mapDisplay === 'hide') {
            $mapDisplay = 'none';
        } elseif ($mapDisplay === 'global' || $mapDisplay === 'link') {
            $mapDisplay = 'link_button';
        }
        $mapDisplay = in_array($mapDisplay, array('none', 'link_text', 'link_button', 'map'), true) ? $mapDisplay : 'link_button';
        $globalMapService = (int) ($settings->global_show_mapserv ?? 0);
        $showMapLink = $mapDisplay !== 'none' && ($mapDisplay !== 'link_button' || in_array($globalMapService, array(0, 1, 2, 3, 4, 5), true));
        $imageHtml = $showImage ? self::buildTimelinePdfImage((string) ($venue->locimage ?? ''), 'venue', (string) ($venue->venue ?? $title), $venueImageWidth, $venueImageHeight) : '';
        $description = $showDescription ? self::normaliseEditorHtmlForPdf((string) $venue->locdescription) : '';
        $html = array();

        $html[] = '<style>
            body { font-family: ' . $bodyFontFamily . '; color: #111827; font-size: ' . $baseFontSize . 'pt; }
            h1 { font-family: ' . $titleFontFamily . '; font-size: ' . $titleFontSize . 'pt; margin: 0 0 4mm 0; color: #111827; }
            h2 { font-family: ' . $headerFontFamily . '; font-size: ' . $headingFontSize . 'pt; margin: 5mm 0 2mm 0; color: #111827; }
            a { color: #1f5b99; text-decoration: underline; }
            .jem-pdf-venue-card { background-color: #f8fafc; border: 0.25mm solid #d6dde8; border-radius: 1.5mm; }
            .jem-pdf-venue-card td { font-size: ' . $baseFontSize . 'pt; vertical-align: top; padding: 1mm 1.5mm; }
            .jem-pdf-label { font-family: ' . $headerFontFamily . '; font-weight: bold; width: 24%; color: #111827; }
            .jem-pdf-image-cell { text-align: right; vertical-align: middle; }
            .jem-pdf-image { border: 0.2mm solid #d1d5db; vertical-align: middle; }
            .jem-pdf-type-badge { color: #ffffff; font-size: ' . max(6, $baseFontSize - 1) . 'pt; font-weight: bold; border-radius: 1.4mm; padding: 0.7mm 1.5mm; }
            .jem-pdf-section { line-height: ' . ($baseFontSize + 3) . 'pt; }
            .jem-pdf-separator { border-top: 0.25mm solid #d1d5db; height: 2mm; }
            .jem-pdf-event-list { border-top: 0.5mm solid #f59e0b; }
            .jem-pdf-event { border-bottom: 0.2mm solid #d1d5db; padding: 2mm 0; }
            .jem-pdf-event-title { font-family: ' . $headerFontFamily . '; font-size: ' . ($baseFontSize + 3) . 'pt; font-weight: bold; color: #1f5b99; }
            .jem-pdf-event-meta { color: #111827; font-size: ' . $baseFontSize . 'pt; line-height: ' . ($baseFontSize + 3) . 'pt; }
            .jem-pdf-badge { color: #ffffff; font-weight: bold; border-radius: 1.5mm; padding: 0.6mm 1.6mm; }
            .jem-pdf-muted { color: #6b7280; }
        </style>';
        $html[] = '<h1>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h1>';

        $intro = self::buildViewTextBlock('intro');
        if ($intro !== '') {
            $html[] = $intro;
        }

        $venueName = (string) ($venue->venue ?? '');
        $html[] = '<h2>' . self::buildPdfBlockHeading($venueHeadingDisplay, Text::_('COM_JEM_VENUE'), $venueName) . '</h2>';

        $rowsHtml = array();
        $rowsHtml[] = self::buildPdfSummaryRow(Text::_('COM_JEM_TITLE'), htmlspecialchars($venueName, ENT_COMPAT, 'UTF-8') . self::buildPdfTypedEntityBadge($venue, 'type_', 'venue'));
        $rowsHtml[] = !empty($venue->street) ? self::buildPdfSummaryRow(Text::_('COM_JEM_STREET'), htmlspecialchars((string) $venue->street, ENT_COMPAT, 'UTF-8')) : '';
        $rowsHtml[] = !empty($venue->postalCode) ? self::buildPdfSummaryRow(Text::_('COM_JEM_ZIP'), htmlspecialchars((string) $venue->postalCode, ENT_COMPAT, 'UTF-8')) : '';
        $rowsHtml[] = !empty($venue->city) ? self::buildPdfSummaryRow(Text::_('COM_JEM_CITY'), htmlspecialchars((string) $venue->city, ENT_COMPAT, 'UTF-8')) : '';
        $rowsHtml[] = !empty($venue->state) ? self::buildPdfSummaryRow(Text::_('COM_JEM_STATE'), htmlspecialchars((string) $venue->state, ENT_COMPAT, 'UTF-8')) : '';
        $rowsHtml[] = !empty($venue->country) ? self::buildPdfSummaryRow(Text::_('COM_JEM_COUNTRY'), htmlspecialchars((string) $venue->country, ENT_COMPAT, 'UTF-8')) : '';

        if ($showMapLink) {
            $map = self::buildPdfMapLink($venue, 'osm');
            if (!empty($map['html'])) {
                $rowsHtml[] = self::buildPdfSummaryRow(Text::_('COM_JEM_MAP'), $map['html']);
            }
        }

        if (!empty($venue->url)) {
            $rowsHtml[] = self::buildPdfSummaryRow(Text::_('COM_JEM_WEBSITE'), self::buildPdfExternalLink((string) $venue->url)['html']);
        }

        $detailsTable = '<table width="100%" cellpadding="2" cellspacing="0">' . implode('', array_filter($rowsHtml)) . '</table>';
        $html[] = '<table class="jem-pdf-venue-card" width="100%" cellpadding="4" cellspacing="0"><tr>'
            . '<td width="' . ($imageHtml !== '' ? '76' : '100') . '%">' . $detailsTable . '</td>'
            . ($imageHtml !== '' ? '<td class="jem-pdf-image-cell" width="24%">' . $imageHtml . '</td>' : '')
            . '</tr></table>';

        if ($description !== '') {
            $html[] = '<div class="jem-pdf-separator">&nbsp;</div>';
            $html[] = '<h2>' . Text::_('COM_JEM_VENUE_DESCRIPTION') . '</h2>';
            $html[] = '<div class="jem-pdf-section">' . $description . '</div>';
        }

        if ($showEvents) {
            $html[] = '<div class="jem-pdf-separator">&nbsp;</div>';
            $html[] = '<h2>' . Text::_('COM_JEM_EVENTS') . '</h2>';
            $html[] = '<div class="jem-pdf-event-list">';

            if (!$rows) {
                $html[] = '<div class="jem-pdf-event jem-pdf-muted">' . Text::_('COM_JEM_NO_EVENTS') . '</div>';
            }

            foreach ($rows as $row) {
                if (isset($row->user_has_access_category) && !$row->user_has_access_category) {
                    continue;
                }

                $eventUrl = !empty($row->slug) ? self::absoluteUrl(Route::_(JemHelperRoute::getEventRoute($row->slug), false)) : '';
                $eventTitle = htmlspecialchars((string) ($row->title ?? ''), ENT_COMPAT, 'UTF-8');

                if ($eventUrl !== '') {
                    $eventTitle = '<a class="jem-pdf-event-title" href="' . htmlspecialchars($eventUrl, ENT_COMPAT, 'UTF-8') . '">' . $eventTitle . '</a>';
                }

                $date = self::htmlToPlainText(JemOutput::formatShortDateTime($row->dates ?? '', $row->times ?? '', $row->enddates ?? '', $row->endtimes ?? ''));
                $meta = array();
                if ($date !== '') {
                    $meta[] = '&#9719; ' . htmlspecialchars($date, ENT_COMPAT, 'UTF-8');
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

                $html[] = '<div class="jem-pdf-event"><div>' . $eventTitle . self::buildPdfEventIndicators($row) . '</div>'
                    . '<div class="jem-pdf-event-meta">' . implode(' &nbsp; ', $meta) . '</div></div>';
            }

            $html[] = '</div>';
        }

        $footer = self::buildViewTextBlock('footer');
        if ($footer !== '') {
            $html[] = $footer;
        }

        return implode("\n", $html);
    }

    private static function buildPdfSummaryRow(string $label, string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        return '<tr><td class="jem-pdf-label" width="24%">' . htmlspecialchars($label, ENT_COMPAT, 'UTF-8') . ':</td><td width="76%">' . $value . '</td></tr>';
    }

    private static function buildPdfBlockHeading(string $display, string $label, string $name): string
    {
        $name = trim($name);

        if ($display === 'name') {
            return htmlspecialchars($name, ENT_COMPAT, 'UTF-8');
        }

        if ($display === 'label_name' && $name !== '') {
            return htmlspecialchars($label . ' - ' . $name, ENT_COMPAT, 'UTF-8');
        }

        return htmlspecialchars($label, ENT_COMPAT, 'UTF-8');
    }

    private static function buildPdfTypedEntityBadge($row, string $prefix = 'type_', string $entity = 'event'): string
    {
        JemOutput::translateType($row, $prefix);

        $name = trim((string) ($row->{$prefix . 'name'} ?? ''));
        if ($name === '') {
            return '';
        }

        $color = trim((string) ($row->{$prefix . 'color'} ?? ''));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6b7280';
        }

        $badge = '<span class="jem-pdf-type-badge" style="background-color:' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8') . '; color:' . self::getContrastingTextColor($color) . ';">' . htmlspecialchars($name, ENT_COMPAT, 'UTF-8') . '</span>';
        $typeId = (int) ($row->{$prefix . 'id'} ?? 0);

        if ($typeId > 0) {
            $slug = $typeId . (!empty($row->{$prefix . 'alias'}) ? ':' . $row->{$prefix . 'alias'} : '');
            $route = $entity === 'venue' ? JemHelperRoute::getTypevenuesRoute($slug) : JemHelperRoute::getTypeeventsRoute($slug);
            $badge = '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_($route, false)), ENT_COMPAT, 'UTF-8') . '">' . $badge . '</a>';
        }

        return ' ' . $badge;
    }

    private static function buildDayTimelineHtml(string $title, array $rows, string $day, $params, string $paperSize): string
    {
        $scale = JemPdf::getPosterScale($paperSize);
        $settings = JemHelper::config();
        $titleFontFamily = self::getPdfFontFamily($settings, 'pdf_title_font_family');
        $headerFontFamily = self::getPdfFontFamily($settings, 'pdf_header_font_family');
        $bodyFontFamily = self::getPdfFontFamily($settings, 'pdf_body_font_family');
        $baseFontSize = max(7, min(13, (int) round(8 * $scale)));
        $titleFontSize = max(14, min(30, (int) round(18 * $scale)));
        $fallbackImageWidth = max(1, min(200, (int) ($settings->pdf_imagewidth ?? 40)));
        $fallbackImageHeight = max(1, min(200, (int) ($settings->pdf_imageheight ?? 40)));
        $eventImageWidth = max(1, min(200, (int) ($settings->pdf_event_imagewidth ?? $fallbackImageWidth)));
        $eventImageHeight = max(1, min(200, (int) ($settings->pdf_event_imageheight ?? $fallbackImageHeight)));
        $venueImageWidth = max(1, min(200, (int) ($settings->pdf_venue_imagewidth ?? $fallbackImageWidth)));
        $venueImageHeight = max(1, min(200, (int) ($settings->pdf_venue_imageheight ?? $fallbackImageHeight)));
        $showEventImage = !$params || !method_exists($params, 'get') || (bool) $params->get('timeline_show_event_image', $params->get('timeline_show_images', 1));
        $eventImagePosition = $params && method_exists($params, 'get') ? (string) $params->get('timeline_event_image_position', 'left') : 'left';
        $eventImagePosition = in_array($eventImagePosition, array('left', 'right'), true) ? $eventImagePosition : 'left';
        $showVenueImage = $params && method_exists($params, 'get') ? (bool) $params->get('timeline_show_venue_image', false) : false;
        $venueImagePosition = $params && method_exists($params, 'get') ? (string) $params->get('timeline_venue_image_position', 'right') : 'right';
        $venueImagePosition = in_array($venueImagePosition, array('left', 'right'), true) ? $venueImagePosition : 'right';
        $showCategory = !$params || !method_exists($params, 'get') || (bool) $params->get('timeline_show_category', 1);
        $categoryDisplay = $params && method_exists($params, 'get') ? (string) $params->get('timeline_category_display', 'text') : 'text';
        $categoryDisplay = in_array($categoryDisplay, array('text', 'badge'), true) ? $categoryDisplay : 'text';
        $showVenue = !$params || !method_exists($params, 'get') || (bool) $params->get('timeline_show_venue', 1);
        $showType = !$params || !method_exists($params, 'get') || (bool) $params->get('timeline_show_type', 1);
        $cardLayout = $params && method_exists($params, 'get') ? (string) $params->get('timeline_card_layout', 'compact') : 'compact';
        $cardLayout = in_array($cardLayout, array('details', 'compact'), true) ? $cardLayout : 'details';
        $layoutOverride = Factory::getApplication()->input->getCmd('jem_timeline_layout', '');
        if (in_array($layoutOverride, array('details', 'compact'), true)) {
            $cardLayout = $layoutOverride;
        }
        $showIntro = !$params || !method_exists($params, 'get') || (bool) $params->get('timeline_show_event_intro', 1);
        $introLimit = $params && method_exists($params, 'get') ? max(0, (int) $params->get('timeline_event_intro_limit', 300)) : 300;
        $showReadmore = !$params || !method_exists($params, 'get') || (bool) $params->get('timeline_show_event_readmore', 1);
        $readmoreStyle = $params && method_exists($params, 'get') ? (string) $params->get('timeline_readmore_style', 'button') : 'button';
        $readmoreStyle = in_array($readmoreStyle, array('text', 'button'), true) ? $readmoreStyle : 'text';
        $eventBackgroundMode = $params && method_exists($params, 'get') ? (string) $params->get('timetable_event_background', 'category') : 'category';
        $eventBackgroundMode = in_array($eventBackgroundMode, array('category', 'type', 'venue', 'custom'), true) ? $eventBackgroundMode : 'category';
        $customEventBackground = $params && method_exists($params, 'get') ? self::normalisePdfColor($params->get('timetable_event_background_custom', '#6bbf59')) : '#6bbf59';
        $customEventBackground = $customEventBackground !== '' ? $customEventBackground : '#6bbf59';
        $legacyAlternateDayBackground = $params && method_exists($params, 'get') ? (bool) $params->get('timeline_alternate_day_background', 1) : true;
        $dayBackground = $params && method_exists($params, 'get') ? (string) $params->get('timeline_day_background', $legacyAlternateDayBackground ? 'alternate' : 'none') : 'alternate';
        $dayBackground = in_array($dayBackground, array('none', 'alternate', 'special_day', 'alternate_special_day'), true) ? $dayBackground : 'alternate';
        $alternateDayBackgroundColor = $params && method_exists($params, 'get') ? trim((string) $params->get('timeline_alternate_day_background_color', '#f3f4f6')) : '#f3f4f6';
        if ($alternateDayBackgroundColor === '' || !preg_match('/^#[0-9a-fA-F]{3,6}$/', $alternateDayBackgroundColor)) {
            $alternateDayBackgroundColor = '#f3f4f6';
        }
        $rows = self::sortTimelineRows($rows);
        $rowsByDay = self::groupTimelineRowsByDisplayDay($rows, $day, $params);
        if (!$rowsByDay && JemHelper::isValidDate($day)) {
            $rowsByDay[$day] = array();
        }

        $timelineSpecialDays = $rowsByDay
            ? JemHelper::calendarSpecialDays((string) array_key_first($rowsByDay), (string) array_key_last($rowsByDay))
            : array();
        $html = array();

        $html[] = '<style>
            body { font-family: ' . $bodyFontFamily . '; color: #111827; }
            h1 { font-family: ' . $titleFontFamily . '; font-size: ' . $titleFontSize . 'pt; margin: 0 0 4mm 0; color: #111827; }
            a { color: #1f5b99; text-decoration: underline; }
            .jem-pdf-timeline-nav { margin: 3mm auto 7mm auto; }
            .jem-pdf-timeline-nav td { border: 0.25mm solid #6b7280; background-color: #f9fafb; text-align: center; font-family: ' . $headerFontFamily . '; font-size: ' . ($baseFontSize + 1) . 'pt; font-weight: bold; color: #111827; padding: 1.4mm 5mm; }
            .jem-pdf-timeline-day-section { padding: 2mm 0 1mm 0; }
            .jem-pdf-timeline-day { text-align: center; margin: 3mm 0 1.5mm 0; font-family: ' . $headerFontFamily . '; font-weight: bold; }
            .jem-pdf-special-days { text-align: center; margin: 0 0 3mm 0; }
            .jem-pdf-special-day-badge { border: 0.2mm solid #9ca3af; border-radius: 1mm; font-family: ' . $headerFontFamily . '; font-weight: bold; padding: 0.6mm 1.6mm; }
            .jem-pdf-timeline-row td { vertical-align: top; font-size: ' . $baseFontSize . 'pt; }
            .jem-pdf-time { text-align: right; font-family: ' . $headerFontFamily . '; font-weight: bold; color: #111827; line-height: ' . ($baseFontSize + 2) . 'pt; }
            .jem-pdf-time small { color: #6b7280; font-weight: normal; }
            .jem-pdf-axis { border-left: 0.25mm solid #9ca3af; text-align: center; color: #6b7280; }
            .jem-pdf-point { color: #6b7280; font-size: ' . ($baseFontSize + 4) . 'pt; }
            .jem-pdf-card td { border: none; }
            .jem-pdf-media { vertical-align: middle; }
            .jem-pdf-media-left { text-align: left; }
            .jem-pdf-media-right { text-align: right; }
            .jem-pdf-content { vertical-align: middle; }
            .jem-pdf-image { border: 0.2mm solid #d1d5db; display: block; vertical-align: middle; }
            .jem-pdf-event-title { font-family: ' . $headerFontFamily . '; font-size: ' . ($baseFontSize + 2) . 'pt; font-weight: bold; color: #111827; }
            .jem-pdf-meta { color: #111827; font-size: ' . max(6, $baseFontSize - 1) . 'pt; }
            .jem-pdf-detail-label { font-family: ' . $headerFontFamily . '; font-weight: bold; color: #111827; }
            .jem-pdf-detail-title { font-size: ' . ($baseFontSize + 3) . 'pt; font-weight: bold; }
            .jem-pdf-detail-description { border-top: 0.2mm solid #d1d5db; padding-top: 1mm; }
            .jem-pdf-category-badge { border-radius: 1.2mm; font-weight: bold; padding: 0.5mm 1.4mm; }
            .jem-pdf-badge { color: #ffffff; font-weight: bold; border-radius: 1.4mm; padding: 0.8mm 1.8mm; }
            .jem-pdf-intro { color: #374151; font-size: ' . max(6, $baseFontSize - 1) . 'pt; line-height: ' . ($baseFontSize + 1) . 'pt; }
            .jem-pdf-readmore-button { border: 0.2mm solid #1f5b99; border-radius: 1mm; padding: 0.6mm 1.6mm; text-decoration: none; }
            .jem-pdf-empty { color: #6b7280; text-align: center; margin-top: 8mm; }
            .jem-pdf-view-intro, .jem-pdf-view-footer-text { font-size: ' . $baseFontSize . 'pt; line-height: ' . ($baseFontSize + 3) . 'pt; }
            .jem-pdf-view-intro { margin-bottom: 4mm; }
            .jem-pdf-view-footer-text { margin-top: 4mm; border-top: 0.2mm solid #d1d5db; padding-top: 2mm; }
            .jem-pdf-muted { color: #6b7280; }
        </style>';
        $html[] = '<h1>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h1>';

        $subtitle = self::formatTimelineRangeLabel($day, $params);
        if ($subtitle !== '') {
            $html[] = '<table class="jem-pdf-timeline-nav" width="42%" align="center" border="1" cellpadding="3" cellspacing="0"><tr><td>' . htmlspecialchars($subtitle, ENT_COMPAT, 'UTF-8') . '</td></tr></table>';
        }

        $intro = self::buildViewTextBlock('intro');
        if ($intro !== '') {
            $html[] = $intro;
        }

        if (!$rowsByDay) {
            $html[] = '<div class="jem-pdf-empty">' . Text::_('COM_JEM_NO_EVENTS') . '</div>';
        }

        $dayIndex = 0;
        foreach ($rowsByDay as $date => $dayRows) {
            $specialDayColor = '';
            if (!empty($timelineSpecialDays[$date][0]['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $timelineSpecialDays[$date][0]['color'])) {
                $specialDayColor = (string) $timelineSpecialDays[$date][0]['color'];
            }

            $dayBackgroundColor = '';
            if (in_array($dayBackground, array('special_day', 'alternate_special_day'), true) && $specialDayColor !== '') {
                $dayBackgroundColor = $specialDayColor;
            } elseif (in_array($dayBackground, array('alternate', 'alternate_special_day'), true) && ($dayIndex % 2) === 1) {
                $dayBackgroundColor = $alternateDayBackgroundColor;
            }

            $hasDayBackground = $dayBackgroundColor !== '';
            $dayStyle = $hasDayBackground
                ? ' style="background-color:' . htmlspecialchars($dayBackgroundColor, ENT_COMPAT, 'UTF-8') . ';"'
                : '';

            $html[] = '<div class="jem-pdf-timeline-day-section"' . $dayStyle . '>';
            $html[] = '<div class="jem-pdf-timeline-day">' . htmlspecialchars(self::formatTimelineDayLabel($date), ENT_COMPAT, 'UTF-8') . '</div>';
            $html[] = self::buildSpecialDayBadgesHtml($timelineSpecialDays[$date] ?? array());

            if (!$dayRows) {
                $html[] = '<div class="jem-pdf-empty">' . Text::_('COM_JEM_NO_EVENTS') . '</div>';
                $html[] = '</div>';
                $dayIndex++;
                continue;
            }

            $html[] = '<table width="100%" cellpadding="0" cellspacing="0">';

            foreach ($dayRows as $row) {
                if (isset($row->user_has_access_category) && !$row->user_has_access_category) {
                    continue;
                }

                $html[] = self::buildDayTimelineRow($row, $showEventImage, $eventImagePosition, $showVenueImage, $venueImagePosition, $eventImageWidth, $eventImageHeight, $venueImageWidth, $venueImageHeight, $showCategory, $categoryDisplay, $showVenue, $showType, $showIntro, $introLimit, $showReadmore, $readmoreStyle, $eventBackgroundMode, $customEventBackground, $cardLayout, $hasDayBackground);
            }

            $html[] = '</table>';
            $html[] = '</div>';
            $dayIndex++;
        }

        $footer = self::buildViewTextBlock('footer');
        if ($footer !== '') {
            $html[] = $footer;
        }

        return implode("\n", $html);
    }

    private static function buildDayTimelineRow($row, bool $showEventImage, string $eventImagePosition, bool $showVenueImage, string $venueImagePosition, int $eventImageWidth, int $eventImageHeight, int $venueImageWidth, int $venueImageHeight, bool $showCategory, string $categoryDisplay, bool $showVenue, bool $showType, bool $showIntro, int $introLimit, bool $showReadmore, string $readmoreStyle, string $eventBackgroundMode, string $customEventBackground, string $cardLayout, bool $isAlternateDay): string
    {
        $start = JemOutput::formattime($row->times ?? '', '', false);
        $end = JemOutput::formattime($row->endtimes ?? '', '', false);
        $categories = (array) ($row->categories ?? array());
        $accent = self::getTimelinePdfAccentColor($row, $eventBackgroundMode, $customEventBackground);

        $eventImage = $showEventImage ? self::buildTimelinePdfImage((string) ($row->datimage ?? ''), 'event', (string) ($row->title ?? ''), $eventImageWidth, $eventImageHeight) : '';
        $venueImage = $showVenueImage ? self::buildTimelinePdfImage((string) ($row->locimage ?? ''), 'venue', (string) (($row->venue ?? '') ?: ($row->title ?? '')), $venueImageWidth, $venueImageHeight) : '';

        $title = htmlspecialchars((string) ($row->title ?? ''), ENT_COMPAT, 'UTF-8');
        $url = !empty($row->slug) ? self::absoluteUrl(Route::_(JemHelperRoute::getEventRoute($row->slug), false)) : '';
        if ($url !== '') {
            $title = '<a class="jem-pdf-event-title" href="' . htmlspecialchars($url, ENT_COMPAT, 'UTF-8') . '">' . $title . '</a>';
        }

        $meta = array();
        $categoryDetail = '';
        if ($showCategory) {
            $categoryHtml = $categoryDisplay === 'badge'
                ? self::buildTimelinePdfCategoryBadges($categories)
                : self::buildPdfCategoryLinks($categories);
            if ($categoryHtml !== '') {
                $categoryDetail = $categoryHtml;
                $meta[] = $categoryHtml;
            }
        }
        if ($showVenue && !empty($row->venue)) {
            $meta[] = (string) (self::buildPdfVenueLink($row)['html'] ?? '');
        }
        if ($showVenue && !empty($row->city)) {
            $meta[] = htmlspecialchars((string) $row->city, ENT_COMPAT, 'UTF-8');
        }

        $badge = '';
        $typeDetail = '';
        if ($showType && !empty($row->type_name)) {
            $typeColor = trim((string) ($row->type_color ?? ''));
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $typeColor)) {
                $typeColor = $accent;
            }
            $badge = '<span class="jem-pdf-badge" style="background-color:' . htmlspecialchars($typeColor, ENT_COMPAT, 'UTF-8') . '; color:' . self::getContrastingTextColor($typeColor) . ';">'
                . htmlspecialchars((string) $row->type_name, ENT_COMPAT, 'UTF-8') . '</span>';
            $typeDetail = $badge;
        }

        $intro = '';
        $detailsDescription = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($row->introtext ?? ''))));
        if ($showIntro && $introLimit > 0) {
            $introData = self::truncatePlainText((string) ($row->introtext ?? ''), $introLimit);
            $text = (string) $introData['text'];
            if ($text !== '') {
                $intro = '<br /><span class="jem-pdf-intro">' . htmlspecialchars($text, ENT_COMPAT, 'UTF-8') . '</span>';

                if ($showReadmore && !empty($introData['truncated']) && $url !== '') {
                    $class = $readmoreStyle === 'button' ? ' class="jem-pdf-readmore-button"' : '';
                    $intro .= ' <a' . $class . ' href="' . htmlspecialchars($url, ENT_COMPAT, 'UTF-8') . '">' . Text::_('COM_JEM_TIMELINE_READ_MORE') . '</a>';
                }
            }
        }

        $venueDetail = array();
        if ($showVenue && !empty($row->venue)) {
            $venueDetail[] = (string) (self::buildPdfVenueLink($row)['html'] ?? '');
        }
        if ($showVenue && !empty($row->city)) {
            $venueDetail[] = htmlspecialchars((string) $row->city, ENT_COMPAT, 'UTF-8');
        }

        if ($cardLayout === 'details') {
            $detailRows = array(
                '<tr><td width="22%" class="jem-pdf-detail-label">' . Text::_('COM_JEM_TITLE') . '</td><td width="78%" class="jem-pdf-detail-title">' . $title . self::buildPdfEventIndicators($row, false) . '</td></tr>',
            );

            if ($showCategory && $categoryDetail !== '') {
                $detailRows[] = '<tr><td class="jem-pdf-detail-label">' . Text::_('COM_JEM_CATEGORY') . '</td><td>' . $categoryDetail . '</td></tr>';
            }

            if ($typeDetail !== '') {
                $detailRows[] = '<tr><td class="jem-pdf-detail-label">' . Text::_('COM_JEM_TYPE') . '</td><td>' . $typeDetail . '</td></tr>';
            }

            if (!empty($venueDetail)) {
                $detailRows[] = '<tr><td class="jem-pdf-detail-label">' . Text::_('COM_JEM_VENUE') . '</td><td>' . implode(' ', $venueDetail) . '</td></tr>';
            }

            if ($showIntro && $detailsDescription !== '') {
                $detailRows[] = '<tr><td class="jem-pdf-detail-label jem-pdf-detail-description">' . Text::_('COM_JEM_DESCRIPTION') . '</td><td class="jem-pdf-detail-description">' . htmlspecialchars($detailsDescription, ENT_COMPAT, 'UTF-8') . '</td></tr>';
            }

            $content = '<table width="100%" cellpadding="1" cellspacing="0">' . implode('', $detailRows) . '</table>';
        } else {
            $content = '<div>' . $title . self::buildPdfEventIndicators($row, false) . '</div>'
                . (!empty($meta) ? '<div class="jem-pdf-meta">' . implode(' &nbsp; ', array_filter($meta)) . '</div>' : '')
                . ($badge !== '' ? '<br />' . $badge : '')
                . $intro;
        }

        $leftImages = array();
        $rightImages = array();

        if ($eventImage !== '') {
            if ($eventImagePosition === 'right') {
                $rightImages[] = $eventImage;
            } else {
                $leftImages[] = $eventImage;
            }
        }

        if ($venueImage !== '') {
            if ($venueImagePosition === 'left') {
                $leftImages[] = $venueImage;
            } else {
                $rightImages[] = $venueImage;
            }
        }

        $leftMedia = implode('<br />', $leftImages);
        $rightMedia = implode('<br />', $rightImages);

        if ($leftMedia !== '' && $rightMedia !== '') {
            $cardInner = '<table width="100%" cellpadding="1" cellspacing="0"><tr>'
                . '<td width="18%" valign="middle" class="jem-pdf-media jem-pdf-media-left" style="vertical-align:middle;text-align:left;">' . $leftMedia . '</td>'
                . '<td width="64%" valign="middle" class="jem-pdf-content" style="vertical-align:middle;">' . $content . '</td>'
                . '<td width="18%" valign="middle" class="jem-pdf-media jem-pdf-media-right" style="vertical-align:middle;text-align:right;">' . $rightMedia . '</td>'
                . '</tr></table>';
        } elseif ($leftMedia !== '') {
            $cardInner = '<table width="100%" cellpadding="1" cellspacing="0"><tr>'
                . '<td width="22%" valign="middle" class="jem-pdf-media jem-pdf-media-left" style="vertical-align:middle;text-align:left;">' . $leftMedia . '</td>'
                . '<td width="78%" valign="middle" class="jem-pdf-content" style="vertical-align:middle;">' . $content . '</td>'
                . '</tr></table>';
        } elseif ($rightMedia !== '') {
            $cardInner = '<table width="100%" cellpadding="1" cellspacing="0"><tr>'
                . '<td width="78%" valign="middle" class="jem-pdf-content" style="vertical-align:middle;">' . $content . '</td>'
                . '<td width="22%" valign="middle" class="jem-pdf-media jem-pdf-media-right" style="vertical-align:middle;text-align:right;">' . $rightMedia . '</td>'
                . '</tr></table>';
        } else {
            $cardInner = '<table width="100%" cellpadding="1" cellspacing="0"><tr><td>' . $content . '</td></tr></table>';
        }

        $cardStyle = 'border:0.35mm solid ' . htmlspecialchars($accent, ENT_COMPAT, 'UTF-8')
            . ';border-left:1.2mm solid ' . htmlspecialchars($accent, ENT_COMPAT, 'UTF-8') . ';';
        $axisStyle = $isAlternateDay ? ' style="border-left:0.45mm solid #4b5563;color:#4b5563;"' : '';
        $pointStyle = $isAlternateDay ? ' style="color:#4b5563;"' : '';

        return '<tr class="jem-pdf-timeline-row">'
            . '<td width="11%" class="jem-pdf-time">' . htmlspecialchars($start, ENT_COMPAT, 'UTF-8') . ($end !== '' ? '<br /><small>' . htmlspecialchars($end, ENT_COMPAT, 'UTF-8') . '</small>' : '') . '</td>'
            . '<td width="3%" class="jem-pdf-axis"' . $axisStyle . '><span class="jem-pdf-point"' . $pointStyle . '>&#9679;</span></td>'
            . '<td width="86%"><table class="jem-pdf-card" width="100%" cellpadding="0" cellspacing="0" style="' . $cardStyle . '"><tr><td>' . $cardInner . '</td></tr></table></td>'
            . '</tr><tr><td colspan="3" height="3mm">&nbsp;</td></tr>';
    }

    private static function buildTimelinePdfCategoryBadges(array $categories): string
    {
        $badges = array();

        foreach ($categories as $category) {
            $name = trim((string) ($category->catname ?? ''));

            if ($name === '') {
                continue;
            }

            $color = self::normalisePdfColor($category->color ?? '');
            $color = $color !== '' ? $color : '#6c757d';
            $textColor = self::getContrastingTextColor($color);
            $label = htmlspecialchars($name, ENT_COMPAT, 'UTF-8');
            $slug = $category->catslug ?? $category->slug ?? $category->id ?? '';
            $style = 'background-color:' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8') . '; color:' . htmlspecialchars($textColor, ENT_COMPAT, 'UTF-8') . ';';

            if ($slug !== '') {
                $label = '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_(JemHelperRoute::getCategoryRoute($slug), false)), ENT_COMPAT, 'UTF-8') . '" style="color:' . htmlspecialchars($textColor, ENT_COMPAT, 'UTF-8') . '; text-decoration:none;">' . $label . '</a>';
            }

            $badges[] = '<span class="jem-pdf-category-badge" style="' . $style . '">' . $label . '</span>';
        }

        return implode(' ', $badges);
    }

    private static function getTimelinePdfAccentColor($row, string $mode, string $fallback): string
    {
        if ($mode === 'custom') {
            return $fallback;
        }

        if ($mode === 'type') {
            $typeColor = self::normalisePdfColor($row->type_color ?? '');

            if ($typeColor !== '') {
                return $typeColor;
            }
        }

        if ($mode === 'venue') {
            foreach (array('l_color', 'venuecolor') as $field) {
                $venueColor = self::normalisePdfColor($row->$field ?? '');

                if ($venueColor !== '') {
                    return self::lightenPdfColor($venueColor);
                }
            }
        }

        foreach ((array) ($row->categories ?? array()) as $category) {
            $categoryColor = self::normalisePdfColor($category->color ?? '');

            if ($categoryColor !== '') {
                return $categoryColor;
            }
        }

        return $fallback;
    }

    private static function normalisePdfColor($color): string
    {
        $color = trim((string) $color);

        if ($color !== '' && $color[0] !== '#') {
            $color = '#' . $color;
        }

        return preg_match('/^#[0-9a-fA-F]{3,6}$/', $color) ? $color : '';
    }

    private static function lightenPdfColor(string $color, int $colorWeight = 45): string
    {
        $color = self::normalisePdfColor($color);

        if ($color === '') {
            return '#dce8e6';
        }

        if (strlen($color) < 5) {
            $scan = sscanf($color, '#%1x%1x%1x');
            $rgb = array($scan[0] * 17, $scan[1] * 17, $scan[2] * 17);
        } else {
            $rgb = sscanf($color, '#%2x%2x%2x');
        }

        $colorWeight = max(0, min(100, $colorWeight)) / 100;

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($rgb[0] * $colorWeight) + (255 * (1 - $colorWeight))),
            (int) round(($rgb[1] * $colorWeight) + (255 * (1 - $colorWeight))),
            (int) round(($rgb[2] * $colorWeight) + (255 * (1 - $colorWeight)))
        );
    }

    private static function sortTimelineRows(array $rows): array
    {
        usort($rows, static function ($left, $right): int {
            $leftDate = (string) ($left->dates ?? '');
            $rightDate = (string) ($right->dates ?? '');

            if ($leftDate !== $rightDate) {
                return strcmp($leftDate, $rightDate);
            }

            $leftStart = self::timelineSortTimestamp($left, 'times', PHP_INT_MAX);
            $rightStart = self::timelineSortTimestamp($right, 'times', PHP_INT_MAX);

            if ($leftStart !== $rightStart) {
                return $leftStart <=> $rightStart;
            }

            $leftEnd = self::timelineSortTimestamp($left, 'endtimes', $leftStart === PHP_INT_MAX ? PHP_INT_MAX : $leftStart + 3600);
            $rightEnd = self::timelineSortTimestamp($right, 'endtimes', $rightStart === PHP_INT_MAX ? PHP_INT_MAX : $rightStart + 3600);

            if ($leftEnd !== $rightEnd) {
                return $leftEnd <=> $rightEnd;
            }

            return strcmp((string) ($left->title ?? ''), (string) ($right->title ?? ''));
        });

        return $rows;
    }

    private static function timelineSortTimestamp($row, string $field, int $fallback): int
    {
        $time = trim((string) ($row->$field ?? ''));

        if ($time === '') {
            return $fallback;
        }

        $timestamp = strtotime((string) ($row->dates ?? '') . ' ' . $time);

        return $timestamp ?: $fallback;
    }

    private static function groupTimelineRowsByDisplayDay(array $rows, string $day, $params): array
    {
        $startDate = JemHelper::isValidDate($day) ? $day : date('Y-m-d');
        $requestedDays = Factory::getApplication()->input->getInt('timeline_days_to_show', 0);
        $daysToShow = $requestedDays > 0
            ? max(1, min(30, $requestedDays))
            : ($params && method_exists($params, 'get') ? max(1, min(30, (int) $params->get('timeline_days_to_show', 1))) : 1);
        $showEmptyDays = $params && method_exists($params, 'get') ? (bool) $params->get('timeline_show_empty_days', 0) : false;
        $rangeStart = new DateTimeImmutable($startDate);
        $rangeEnd = $rangeStart->modify('+' . ($daysToShow - 1) . ' days');
        $groups = array();

        for ($index = 0; $index < $daysToShow; $index++) {
            $groups[$rangeStart->modify('+' . $index . ' days')->format('Y-m-d')] = array();
        }

        foreach ($rows as $row) {
            $rowStart = (string) ($row->dates ?? '');

            if (!JemHelper::isValidDate($rowStart)) {
                continue;
            }

            $rowEnd = (string) ($row->enddates ?? '');
            if (!JemHelper::isValidDate($rowEnd) || $rowEnd < $rowStart) {
                $rowEnd = $rowStart;
            }

            $activeStart = new DateTimeImmutable(max($rowStart, $rangeStart->format('Y-m-d')));
            $activeEnd = new DateTimeImmutable(min($rowEnd, $rangeEnd->format('Y-m-d')));

            if ($activeStart > $activeEnd) {
                if ($daysToShow === 1) {
                    $displayRow = clone $row;
                    $displayRow->dates = $rangeStart->format('Y-m-d');
                    $groups[$rangeStart->format('Y-m-d')][] = $displayRow;
                }

                continue;
            }

            for ($date = $activeStart; $date <= $activeEnd; $date = $date->modify('+1 day')) {
                $dateKey = $date->format('Y-m-d');

                if (!array_key_exists($dateKey, $groups)) {
                    continue;
                }

                $displayRow = clone $row;
                $displayRow->dates = $dateKey;
                $groups[$dateKey][] = $displayRow;
            }
        }

        foreach ($groups as $date => $groupRows) {
            $groups[$date] = self::sortTimelineRows($groupRows);

            if (!$showEmptyDays && !$groupRows) {
                unset($groups[$date]);
            }
        }

        return $groups;
    }

    private static function formatTimelineRangeLabel(string $date, $params): string
    {
        if (!JemHelper::isValidDate($date)) {
            return '';
        }

        $requestedDays = Factory::getApplication()->input->getInt('timeline_days_to_show', 0);
        $daysToShow = $requestedDays > 0
            ? max(1, min(30, $requestedDays))
            : ($params && method_exists($params, 'get') ? max(1, min(30, (int) $params->get('timeline_days_to_show', 1))) : 1);

        if ($daysToShow <= 1) {
            return self::formatTimelineDayLabel($date);
        }

        $rangeStart = new DateTimeImmutable($date);
        $rangeEnd = $rangeStart->modify('+' . ($daysToShow - 1) . ' days');

        return self::formatTimelineDayLabel($rangeStart->format('Y-m-d')) . ' - ' . self::formatTimelineDayLabel($rangeEnd->format('Y-m-d'));
    }

    private static function formatTimelineDayLabel(string $date): string
    {
        if (!JemHelper::isValidDate($date)) {
            return '';
        }

        return HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC3'));
    }

    private static function buildTimelinePdfImage(string $imageFile, string $type, string $alt, int $maxWidth, int $maxHeight): string
    {
        $imageFile = trim($imageFile);

        if ($imageFile === '') {
            return '';
        }

        $image = JemImage::flyercreator($imageFile, $type);

        if (!is_array($image)) {
            return '';
        }

        $source = !empty($image['thumb']) && is_file(JPATH_SITE . '/' . $image['thumb'])
            ? $image['thumb']
            : ($image['original'] ?? '');

        if ($source === '' || !is_file(JPATH_SITE . '/' . $source)) {
            return '';
        }

        $path = JPATH_SITE . '/' . $source;
        $size = @getimagesize($path);
        $width = $maxWidth;
        $height = 0;

        if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
            $ratio = min($maxWidth / (int) $size[0], $maxHeight / (int) $size[1]);
            $width = max(1, round((int) $size[0] * $ratio, 1));
            $height = max(1, round((int) $size[1] * $ratio, 1));
        }

        $attributes = ' width="' . htmlspecialchars((string) $width, ENT_COMPAT, 'UTF-8') . 'mm"';

        if ($height > 0) {
            $attributes .= ' height="' . htmlspecialchars((string) $height, ENT_COMPAT, 'UTF-8') . 'mm"';
        }

        return '<img class="jem-pdf-image" src="' . htmlspecialchars(str_replace('\\', '/', $path), ENT_COMPAT, 'UTF-8') . '"' . $attributes . ' alt="' . htmlspecialchars($alt, ENT_COMPAT, 'UTF-8') . '" />';
    }

    private static function truncatePlainText(string $text, int $limit): array
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));

        if ($text === '' || $limit <= 0) {
            return array('text' => $text, 'truncated' => false);
        }

        if (function_exists('mb_strlen') && mb_strlen($text) <= $limit || !function_exists('mb_strlen') && strlen($text) <= $limit) {
            return array('text' => $text, 'truncated' => false);
        }

        if (function_exists('mb_substr')) {
            return array('text' => rtrim(mb_substr($text, 0, $limit - 1)) . '...', 'truncated' => true);
        }

        return array('text' => rtrim(substr($text, 0, $limit - 1)) . '...', 'truncated' => true);
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

    private static function buildVenueListRows(array $rows, bool $includeMapColumns = false, string $mapProvider = 'osm', bool $includeMapLink = false): array
    {
        $tableRows = array();

        foreach ($rows as $row) {
            $tableRow = array(
                self::buildPdfVenueListLink($row),
                (string) ($row->city ?? ''),
                (string) ($row->state ?? ''),
                self::formatPdfCountryName((string) ($row->country ?? '')),
                self::buildPdfExternalLink((string) ($row->url ?? '')),
            );

            if ($includeMapColumns) {
                $tableRow[] = self::formatCoordinate($row->latitude ?? '');
                $tableRow[] = self::formatCoordinate($row->longitude ?? '');
            }

            if ($includeMapColumns || $includeMapLink) {
                $tableRow[] = self::buildPdfMapLink($row, $mapProvider, 'COM_JEM_MAP_LINK');
            }

            $tableRows[] = $tableRow;
        }

        return $tableRows;
    }

    private static function formatPdfCountryName(string $country): string
    {
        $country = trim($country);

        if ($country === '') {
            return '';
        }

        if (class_exists('JemHelperCountries', false) && method_exists('JemHelperCountries', 'getCountryName')) {
            $countryName = JemHelperCountries::getCountryName($country);

            if (trim((string) $countryName) !== '') {
                return (string) $countryName;
            }
        }

        return $country;
    }

    private static function buildVenueMapPreviewHtml(array $rows, string $mapProvider): string
    {
        $url = self::buildStaticMapImageUrl($rows, $mapProvider);

        if ($url === '') {
            return '';
        }

        $imagePath = self::cacheRemoteMapImage($url);

        if ($imagePath === '') {
            $imagePath = self::buildFallbackVenueMapPreviewImage($rows);
        }

        if ($imagePath === '') {
            return '';
        }

        return '<div class="jem-pdf-map-preview">'
            . '<img src="' . htmlspecialchars(str_replace('\\', '/', $imagePath), ENT_COMPAT, 'UTF-8') . '" alt="' . Text::_('COM_JEM_MAP') . '" width="270mm" style="border:0.2mm solid #cbd5e1; margin-bottom:4mm;" />'
            . '</div>';
    }

    private static function cacheRemoteMapImage(string $url): string
    {
        $cacheDir = self::getPdfMapCacheDirectory();

        if ($cacheDir === '') {
            return '';
        }

        $hash = sha1($url);
        $basePath = $cacheDir . '/pdf-map-' . $hash;
        foreach (array('png', 'jpg', 'gif') as $extension) {
            $cached = $basePath . '.' . $extension;
            if (is_file($cached) && filesize($cached) > 0 && filemtime($cached) > time() - 86400) {
                return $cached;
            }
        }

        $data = self::downloadRemoteMapImage($url);
        if ($data === '' || strlen($data) > 5242880) {
            return '';
        }

        $imageInfo = @getimagesizefromstring($data);
        if (!is_array($imageInfo) || empty($imageInfo[2])) {
            return '';
        }

        $extension = 'png';
        if ((int) $imageInfo[2] === IMAGETYPE_JPEG) {
            $extension = 'jpg';
        } elseif ((int) $imageInfo[2] === IMAGETYPE_GIF) {
            $extension = 'gif';
        } elseif ((int) $imageInfo[2] !== IMAGETYPE_PNG) {
            return '';
        }

        $path = $basePath . '.' . $extension;

        return @file_put_contents($path, $data) !== false ? $path : '';
    }

    private static function getPdfMapCacheDirectory(): string
    {
        $candidates = array(
            JPATH_SITE . '/media/com_jem/cache',
            JPATH_CACHE . '/com_jem',
        );

        foreach ($candidates as $candidate) {
            if ((is_dir($candidate) || @mkdir($candidate, 0755, true)) && is_writable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function downloadRemoteMapImage(string $url): string
    {
        try {
            $response = HttpFactory::getHttp()->get($url, array('User-Agent' => 'JEM PDF map preview'), 15);
            if ((int) ($response->code ?? 0) >= 200 && (int) ($response->code ?? 0) < 300) {
                return (string) ($response->body ?? '');
            }
        } catch (\Throwable $e) {
            // Fall back to PHP streams below.
        }

        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 15,
                'header' => "User-Agent: JEM PDF map preview\r\n",
            ),
            'https' => array(
                'timeout' => 15,
                'header' => "User-Agent: JEM PDF map preview\r\n",
            ),
        ));
        $data = @file_get_contents($url, false, $context);

        return is_string($data) ? $data : '';
    }

    private static function buildFallbackVenueMapPreviewImage(array $rows): string
    {
        $points = self::getVenueMapPoints($rows);

        if (!$points) {
            return '';
        }

        $cacheDir = self::getPdfMapCacheDirectory();
        if ($cacheDir === '') {
            return '';
        }

        $hash = sha1(json_encode($points));
        $pngPath = $cacheDir . '/pdf-map-fallback-' . $hash . '.png';
        $svgPath = $cacheDir . '/pdf-map-fallback-' . $hash . '.svg';

        if (is_file($pngPath) && filesize($pngPath) > 0) {
            return $pngPath;
        }

        if (function_exists('imagecreatetruecolor')) {
            $generated = self::buildFallbackVenueMapFromOsmTiles($points, $cacheDir . '/pdf-map-osm-' . $hash . '.png');
            if ($generated !== '') {
                return $generated;
            }

            $generated = self::buildFallbackVenueMapPng($points, $pngPath);
            if ($generated !== '') {
                return $generated;
            }
        }

        if (is_file($svgPath) && filesize($svgPath) > 0) {
            return $svgPath;
        }

        return self::buildFallbackVenueMapSvg($points, $svgPath);
    }

    private static function buildFallbackVenueMapFromOsmTiles(array $points, string $path): string
    {
        if (is_file($path) && filesize($path) > 0 && filemtime($path) > time() - 86400) {
            return $path;
        }

        $width = 1600;
        $height = 700;
        $zoom = max(2, min(13, self::estimateStaticMapZoom(array_map(static function (array $point): array {
            return array($point['lat'], $point['lng']);
        }, $points))));

        $centerLat = array_sum(array_column($points, 'lat')) / count($points);
        $centerLng = array_sum(array_column($points, 'lng')) / count($points);
        $centerPixel = self::latLngToOsmPixel($centerLat, $centerLng, $zoom);
        $topLeftX = $centerPixel['x'] - ($width / 2);
        $topLeftY = $centerPixel['y'] - ($height / 2);
        $tileStartX = (int) floor($topLeftX / 256);
        $tileStartY = (int) floor($topLeftY / 256);
        $tileEndX = (int) floor(($topLeftX + $width) / 256);
        $tileEndY = (int) floor(($topLeftY + $height) / 256);
        $maxTile = (1 << $zoom) - 1;
        $image = imagecreatetruecolor($width, $height);

        if (!$image) {
            return '';
        }

        $background = imagecolorallocate($image, 232, 238, 246);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        $loadedTiles = 0;

        for ($x = $tileStartX; $x <= $tileEndX; $x++) {
            $wrappedX = (($x % ($maxTile + 1)) + ($maxTile + 1)) % ($maxTile + 1);
            for ($y = $tileStartY; $y <= $tileEndY; $y++) {
                if ($y < 0 || $y > $maxTile) {
                    continue;
                }

                $tilePath = self::cacheOsmTile($zoom, $wrappedX, $y);
                if ($tilePath === '') {
                    continue;
                }

                $tile = @imagecreatefrompng($tilePath);
                if (!$tile) {
                    continue;
                }

                imagecopy(
                    $image,
                    $tile,
                    (int) round(($x * 256) - $topLeftX),
                    (int) round(($y * 256) - $topLeftY),
                    0,
                    0,
                    256,
                    256
                );
                imagedestroy($tile);
                $loadedTiles++;
            }
        }

        if ($loadedTiles === 0) {
            imagedestroy($image);

            return '';
        }

        self::drawVenueMapMarkers($image, $points, $topLeftX, $topLeftY, $zoom);
        $ok = imagepng($image, $path);
        imagedestroy($image);

        return $ok && is_file($path) ? $path : '';
    }

    private static function cacheOsmTile(int $zoom, int $x, int $y): string
    {
        $cacheDir = self::getPdfMapCacheDirectory();
        if ($cacheDir === '') {
            return '';
        }

        $tileDir = $cacheDir . '/osm-tiles/' . $zoom . '/' . $x;
        if (!is_dir($tileDir) && !@mkdir($tileDir, 0755, true) && !is_dir($tileDir)) {
            return '';
        }

        $path = $tileDir . '/' . $y . '.png';
        if (is_file($path) && filesize($path) > 0 && filemtime($path) > time() - 604800) {
            return $path;
        }

        $data = self::downloadRemoteMapImage('https://tile.openstreetmap.org/' . $zoom . '/' . $x . '/' . $y . '.png');
        if ($data === '' || strlen($data) > 1048576 || @getimagesizefromstring($data) === false) {
            return '';
        }

        return @file_put_contents($path, $data) !== false ? $path : '';
    }

    private static function getVenueMapPoints(array $rows): array
    {
        $points = array();

        foreach ($rows as $row) {
            $lat = self::normaliseCoordinate($row->latitude ?? null);
            $lng = self::normaliseCoordinate($row->longitude ?? null);

            if ($lat === null || $lng === null) {
                continue;
            }

            $label = trim((string) ($row->venue ?? $row->title ?? ''));
            $points[] = array(
                'lat' => $lat,
                'lng' => $lng,
                'label' => $label !== '' ? $label : self::formatCoordinate($lat) . ', ' . self::formatCoordinate($lng),
            );
        }

        return $points;
    }

    private static function buildFallbackVenueMapPng(array $points, string $path): string
    {
        $width = 1600;
        $height = 620;
        $padding = 18;
        $plot = self::projectVenueMapPoints($points, $width, $height, $padding);

        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            return '';
        }

        $background = imagecolorallocate($image, 248, 250, 252);
        $grid = imagecolorallocate($image, 226, 232, 240);
        $border = imagecolorallocate($image, 148, 163, 184);
        $marker = imagecolorallocate($image, 29, 78, 216);
        $markerBorder = imagecolorallocate($image, 15, 23, 42);
        $text = imagecolorallocate($image, 17, 24, 39);
        $muted = imagecolorallocate($image, 100, 116, 139);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        for ($i = 1; $i < 6; $i++) {
            $x = (int) round($padding + ($width - 2 * $padding) * $i / 6);
            $y = (int) round($padding + ($height - 2 * $padding) * $i / 6);
            imageline($image, $x, $padding, $x, $height - $padding, $grid);
            imageline($image, $padding, $y, $width - $padding, $y, $grid);
        }

        imagerectangle($image, $padding, $padding, $width - $padding, $height - $padding, $border);
        imagestring($image, 3, $padding, 14, Text::_('COM_JEM_VENUES_MAP'), $text);
        imagestring($image, 2, $padding, $height - 26, self::buildVenueMapBoundsLabel($plot['bounds']), $muted);

        $labelCount = 0;
        foreach ($plot['points'] as $point) {
            imagefilledellipse($image, (int) $point['x'], (int) $point['y'], 12, 12, $marker);
            imageellipse($image, (int) $point['x'], (int) $point['y'], 12, 12, $markerBorder);

            if ($labelCount < 18) {
                imagestring($image, 2, (int) $point['x'] + 8, max($padding, (int) $point['y'] - 7), self::asciiImageLabel($point['label']), $text);
                $labelCount++;
            }
        }

        $ok = imagepng($image, $path);
        imagedestroy($image);

        return $ok && is_file($path) ? $path : '';
    }

    private static function drawVenueMapMarkers($image, array $points, float $topLeftX, float $topLeftY, int $zoom): void
    {
        $marker = imagecolorallocate($image, 29, 78, 216);
        $markerBorder = imagecolorallocate($image, 15, 23, 42);
        $text = imagecolorallocate($image, 17, 24, 39);
        $labelBackground = imagecolorallocate($image, 255, 255, 255);
        $labelCount = 0;

        foreach ($points as $point) {
            $pixel = self::latLngToOsmPixel($point['lat'], $point['lng'], $zoom);
            $x = (int) round($pixel['x'] - $topLeftX);
            $y = (int) round($pixel['y'] - $topLeftY);
            imagefilledellipse($image, $x, $y, 16, 16, $marker);
            imageellipse($image, $x, $y, 16, 16, $markerBorder);

            if ($labelCount < 18) {
                $label = self::asciiImageLabel($point['label']);
                imagefilledrectangle($image, $x + 10, $y - 9, $x + 12 + strlen($label) * 6, $y + 7, $labelBackground);
                imagestring($image, 2, $x + 12, $y - 7, $label, $text);
                $labelCount++;
            }
        }
    }

    private static function latLngToOsmPixel(float $lat, float $lng, int $zoom): array
    {
        $lat = max(-85.05112878, min(85.05112878, $lat));
        $sinLat = sin(deg2rad($lat));
        $scale = 256 * (1 << $zoom);

        return array(
            'x' => ($lng + 180.0) / 360.0 * $scale,
            'y' => (0.5 - log((1 + $sinLat) / (1 - $sinLat)) / (4 * pi())) * $scale,
        );
    }

    private static function buildFallbackVenueMapSvg(array $points, string $path): string
    {
        $width = 1600;
        $height = 620;
        $padding = 18;
        $plot = self::projectVenueMapPoints($points, $width, $height, $padding);
        $svg = array();
        $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
        $svg[] = '<rect width="100%" height="100%" fill="#f8fafc"/>';

        for ($i = 1; $i < 6; $i++) {
            $x = round($padding + ($width - 2 * $padding) * $i / 6, 2);
            $y = round($padding + ($height - 2 * $padding) * $i / 6, 2);
            $svg[] = '<line x1="' . $x . '" y1="' . $padding . '" x2="' . $x . '" y2="' . ($height - $padding) . '" stroke="#e2e8f0"/>';
            $svg[] = '<line x1="' . $padding . '" y1="' . $y . '" x2="' . ($width - $padding) . '" y2="' . $y . '" stroke="#e2e8f0"/>';
        }

        $svg[] = '<rect x="' . $padding . '" y="' . $padding . '" width="' . ($width - 2 * $padding) . '" height="' . ($height - 2 * $padding) . '" fill="none" stroke="#94a3b8"/>';
        $svg[] = '<text x="' . $padding . '" y="24" font-size="14" font-family="Helvetica, Arial, sans-serif" fill="#111827">' . htmlspecialchars(Text::_('COM_JEM_VENUES_MAP'), ENT_COMPAT, 'UTF-8') . '</text>';
        $svg[] = '<text x="' . $padding . '" y="' . ($height - 12) . '" font-size="11" font-family="Helvetica, Arial, sans-serif" fill="#64748b">' . htmlspecialchars(self::buildVenueMapBoundsLabel($plot['bounds']), ENT_COMPAT, 'UTF-8') . '</text>';

        $labelCount = 0;
        foreach ($plot['points'] as $point) {
            $svg[] = '<circle cx="' . $point['x'] . '" cy="' . $point['y'] . '" r="6" fill="#1d4ed8" stroke="#0f172a"/>';
            if ($labelCount < 18) {
                $svg[] = '<text x="' . ($point['x'] + 9) . '" y="' . max($padding + 12, $point['y'] + 4) . '" font-size="11" font-family="Helvetica, Arial, sans-serif" fill="#111827">' . htmlspecialchars($point['label'], ENT_COMPAT, 'UTF-8') . '</text>';
                $labelCount++;
            }
        }

        $svg[] = '</svg>';

        return @file_put_contents($path, implode("\n", $svg)) !== false ? $path : '';
    }

    private static function projectVenueMapPoints(array $points, int $width, int $height, int $padding): array
    {
        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');
        $minLat = min($lats);
        $maxLat = max($lats);
        $minLng = min($lngs);
        $maxLng = max($lngs);

        if (abs($maxLat - $minLat) < 0.000001) {
            $minLat -= 0.01;
            $maxLat += 0.01;
        }

        if (abs($maxLng - $minLng) < 0.000001) {
            $minLng -= 0.01;
            $maxLng += 0.01;
        }

        $projected = array();
        foreach ($points as $point) {
            $x = $padding + (($point['lng'] - $minLng) / ($maxLng - $minLng)) * ($width - 2 * $padding);
            $y = $height - $padding - (($point['lat'] - $minLat) / ($maxLat - $minLat)) * ($height - 2 * $padding);
            $projected[] = array(
                'x' => round($x, 2),
                'y' => round($y, 2),
                'label' => $point['label'],
            );
        }

        return array(
            'points' => $projected,
            'bounds' => array(
                'minLat' => $minLat,
                'maxLat' => $maxLat,
                'minLng' => $minLng,
                'maxLng' => $maxLng,
            ),
        );
    }

    private static function buildVenueMapBoundsLabel(array $bounds): string
    {
        return 'Lat ' . self::formatCoordinate($bounds['minLat']) . '...' . self::formatCoordinate($bounds['maxLat'])
            . ' / Lng ' . self::formatCoordinate($bounds['minLng']) . '...' . self::formatCoordinate($bounds['maxLng']);
    }

    private static function asciiImageLabel(string $label): string
    {
        $label = preg_replace('/[^\x20-\x7E]/', '', $label);

        return strlen($label) > 38 ? substr($label, 0, 35) . '...' : $label;
    }

    private static function buildStaticMapImageUrl(array $rows, string $mapProvider): string
    {
        $points = array();

        foreach ($rows as $row) {
            $lat = self::normaliseCoordinate($row->latitude ?? null);
            $lng = self::normaliseCoordinate($row->longitude ?? null);

            if ($lat === null || $lng === null) {
                continue;
            }

            $points[] = array($lat, $lng);
        }

        if (!$points) {
            return '';
        }

        $markerPoints = array_slice($points, 0, 40);
        $centerLat = array_sum(array_column($points, 0)) / count($points);
        $centerLng = array_sum(array_column($points, 1)) / count($points);
        $settings = JemHelper::globalattribs();
        $googleApiKey = trim((string) ($settings && method_exists($settings, 'get') ? $settings->get('global_googleapi', '') : ''));

        if ($mapProvider === 'google' && $googleApiKey !== '') {
            $url = 'https://maps.googleapis.com/maps/api/staticmap?size=640x320&scale=2&maptype=roadmap'
                . '&center=' . rawurlencode(self::formatCoordinate($centerLat) . ',' . self::formatCoordinate($centerLng))
                . '&zoom=' . self::estimateStaticMapZoom($points);

            foreach ($markerPoints as $point) {
                $url .= '&markers=' . rawurlencode(self::formatCoordinate($point[0]) . ',' . self::formatCoordinate($point[1]));
            }

            return $url . '&key=' . rawurlencode($googleApiKey);
        }

        $markers = array_map(static function (array $point): string {
            return JemPdfView::formatCoordinate($point[0]) . ',' . JemPdfView::formatCoordinate($point[1]) . ',red-pushpin';
        }, $markerPoints);

        return 'https://staticmap.openstreetmap.de/staticmap.php?'
            . 'center=' . rawurlencode(self::formatCoordinate($centerLat) . ',' . self::formatCoordinate($centerLng))
            . '&zoom=' . self::estimateStaticMapZoom($points)
            . '&size=900x320'
            . '&markers=' . rawurlencode(implode('|', $markers));
    }

    private static function normaliseCoordinate($value): ?float
    {
        $value = trim((string) $value);

        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function formatCoordinate($value): string
    {
        $coordinate = self::normaliseCoordinate($value);

        if ($coordinate === null) {
            return '';
        }

        return rtrim(rtrim(number_format($coordinate, 6, '.', ''), '0'), '.');
    }

    private static function estimateStaticMapZoom(array $points): int
    {
        if (count($points) < 2) {
            return 15;
        }

        $latitudes = array_column($points, 0);
        $longitudes = array_column($points, 1);
        $spread = max(max($latitudes) - min($latitudes), max($longitudes) - min($longitudes));

        if ($spread > 40) {
            return 2;
        }

        if ($spread > 20) {
            return 3;
        }

        if ($spread > 10) {
            return 4;
        }

        if ($spread > 5) {
            return 5;
        }

        if ($spread > 2) {
            return 6;
        }

        if ($spread > 1) {
            return 7;
        }

        if ($spread > 0.5) {
            return 9;
        }

        if ($spread > 0.1) {
            return 11;
        }

        return 13;
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

    private static function buildTableHtml(string $title, array $headers, array $rows, string $paperSize, string $filterSummary = '', string $preTableHtml = ''): string
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
            .jem-pdf-day-date { text-align: center; font-family: ' . $headerFontFamily . '; font-size: ' . ($baseFontSize + 2) . 'pt; font-weight: bold; margin: 0 0 1.5mm 0; }
            .jem-pdf-special-days { text-align: center; margin: 0 0 4mm 0; }
            .jem-pdf-special-day-badge { border: 0.2mm solid #9ca3af; border-radius: 1mm; font-family: ' . $headerFontFamily . '; font-weight: bold; padding: 0.6mm 1.6mm; }
        </style>';
        $html[] = '<h1>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h1>';
        if ($preTableHtml !== '') {
            $html[] = $preTableHtml;
        }
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
        $gridEnd = $gridStart->modify('+' . ($cellCount - 1) . ' days');
        $eventsByDate = self::buildCalendarEventsByDate($rows, $gridStart, $gridEnd);
        $specialDaysByDate = JemHelper::calendarSpecialDays($gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d'));
        $legend = self::buildCalendarCategoryLegend($rows);
        $useCategoryBackground = $params && method_exists($params, 'get') && (int) $params->get('eventbg_usecatcolor', 0) === 1;
        $weekdays = $firstWeekDay === 0
            ? array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT')
            : array('MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN');

        if (self::getCalendarPdfLayout($params) === 'agenda') {
            return self::buildCalendarAgendaHtml($title, $monthStart, $monthEnd, $eventsByDate, $specialDaysByDate, $legend, $paperSize, $monthStart->format('F Y'));
        }

        return self::buildCalendarGridHtml($title, $monthStart, $monthEnd, $gridStart, $cellCount, $weekdays, $eventsByDate, $specialDaysByDate, $legend, $paperSize, true, $monthStart->format('F Y'), $useCategoryBackground);
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
        $specialDaysByDate = JemHelper::calendarSpecialDays($weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d'));
        $legend = self::buildCalendarCategoryLegend($rows);
        $useCategoryBackground = $params && method_exists($params, 'get') && (int) $params->get('eventbg_usecatcolor', 0) === 1;
        $weekdays = $firstWeekDay === 0
            ? array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT')
            : array('MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN');

        if (self::getCalendarPdfLayout($params) === 'agenda') {
            return self::buildCalendarAgendaHtml($title, $weekStart, $weekEnd, $eventsByDate, $specialDaysByDate, $legend, $paperSize, Text::sprintf('COM_JEM_WEEKCAL_WEEK_NUMBER', (int) $weekStart->format('W'), (int) $weekStart->format('o')));
        }

        return self::buildCalendarGridHtml($title, $weekStart, $weekEnd, $weekStart, 7, $weekdays, $eventsByDate, $specialDaysByDate, $legend, $paperSize, false, Text::sprintf('COM_JEM_WEEKCAL_WEEK_NUMBER', (int) $weekStart->format('W'), (int) $weekStart->format('o')), $useCategoryBackground);
    }

    private static function buildCalendarAgendaHtml(
        string $title,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        array $eventsByDate,
        array $specialDaysByDate,
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
            .jem-pdf-special-days { margin-top: 1mm; }
            .jem-pdf-special-day-badge { border: 0.2mm solid #9ca3af; border-radius: 1mm; font-family: ' . $headerFontFamily . '; font-weight: bold; padding: 0.5mm 1.2mm; }
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
                    $html[] = '<td width="13%" rowspan="' . count($dayEvents) . '"><span class="jem-pdf-agenda-date">' . (int) $date->format('j') . '</span><br /><span class="jem-pdf-agenda-weekday">' . htmlspecialchars($date->format('D'), ENT_COMPAT, 'UTF-8') . '</span>' . self::buildSpecialDayBadgesHtml($specialDaysByDate[$dateKey] ?? array()) . '</td>';
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
        array $specialDaysByDate,
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
            .jem-pdf-special-days { margin-top: 0.6mm; }
            .jem-pdf-special-day-badge { border: 0.2mm solid #9ca3af; border-radius: 1mm; font-family: ' . $headerFontFamily . '; font-weight: bold; padding: 0.4mm 1mm; }
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
            $html[] = self::buildSpecialDayBadgesHtml($specialDaysByDate[$dateKey] ?? array());

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

    private static function buildTypeHeaderHtml($type): string
    {
        if (!$type) {
            return '';
        }

        $name = trim((string) ($type->name ?? ''));
        $description = trim((string) ($type->description ?? ''));
        $icon = trim((string) ($type->icon ?? ''));
        $color = trim((string) ($type->color ?? ''));

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6b7280';
        }

        $iconText = $icon !== '' ? htmlspecialchars($icon, ENT_COMPAT, 'UTF-8') . ' ' : '';
        $html = '<div class="jem-pdf-type-header" style="border:0.25mm solid #d1d5db; background-color:#f8fafc; padding:2mm; margin-bottom:4mm;">'
            . '<div style="font-weight:bold; color:' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8') . ';">'
            . $iconText . htmlspecialchars($name, ENT_COMPAT, 'UTF-8') . '</div>';

        if ($description !== '') {
            $html .= '<div style="color:#374151; margin-top:1mm;">' . self::normaliseEditorHtmlForPdf($description) . '</div>';
        }

        return $html . '</div>';
    }

    private static function buildCategoryDetailIntroHtml($category, array $children, string $description): string
    {
        $html = array();
        $description = trim($description);

        if ($description !== '' && $description !== Text::_('COM_JEM_NO_DESCRIPTION')) {
            $html[] = '<div class="jem-pdf-category-description" style="border:0.25mm solid #d1d5db; background-color:#f8fafc; padding:2mm; margin-bottom:4mm;">'
                . self::normaliseEditorHtmlForPdf($description)
                . '</div>';
        }

        $subcategories = array();
        foreach ($children as $child) {
            $name = trim((string) ($child->catname ?? $child->title ?? ''));
            if ($name !== '') {
                $subcategories[] = htmlspecialchars($name, ENT_COMPAT, 'UTF-8');
            }
        }

        if ($subcategories) {
            $html[] = '<div class="jem-pdf-subcategories" style="margin-bottom:4mm;"><strong>'
                . Text::_('COM_JEM_SUBCATEGORIES') . ':</strong> '
                . implode(', ', $subcategories)
                . '</div>';
        }

        return implode("\n", $html);
    }

    private static function buildCategoryListHtml(string $title, array $rows, array $typeItems, string $paperSize): string
    {
        $scale = JemPdf::getPosterScale($paperSize);
        $settings = JemHelper::config();
        $titleFontFamily = self::getPdfFontFamily($settings, 'pdf_title_font_family');
        $headerFontFamily = self::getPdfFontFamily($settings, 'pdf_header_font_family');
        $bodyFontFamily = self::getPdfFontFamily($settings, 'pdf_body_font_family');
        $baseFontSize = max(7, min(14, (int) round(8 * $scale)));
        $titleFontSize = max(14, min(30, (int) round(18 * $scale)));
        $categoryTitleSize = max(11, min(20, (int) round(14 * $scale)));
        $html = array();

        $html[] = '<style>
            body { font-family: ' . $bodyFontFamily . '; }
            h1 { font-family: ' . $titleFontFamily . '; font-size: ' . $titleFontSize . 'pt; margin: 0 0 4mm 0; color: #111827; }
            a { color: #1f5b99; text-decoration: underline; }
            .jem-pdf-view-intro, .jem-pdf-view-footer-text { font-size: ' . $baseFontSize . 'pt; line-height: ' . ($baseFontSize + 3) . 'pt; }
            .jem-pdf-view-intro { margin-bottom: 4mm; }
            .jem-pdf-view-footer-text { margin-top: 4mm; border-top: 0.2mm solid #d1d5db; padding-top: 2mm; }
            .jem-pdf-category-type { font-family: ' . $headerFontFamily . '; font-size: ' . ($baseFontSize + 3) . 'pt; font-weight: bold; color: #111827; border-bottom: 0.35mm solid #d1d5db; padding-bottom: 1mm; margin: 5mm 0 2mm 0; }
            .jem-pdf-category-card { border: 0.2mm solid #d1d5db; background-color: #f8fafc; padding: 2.2mm; margin-bottom: 2.5mm; }
            .jem-pdf-category-name { font-family: ' . $headerFontFamily . '; font-size: ' . $categoryTitleSize . 'pt; font-weight: bold; color: #1f5b99; }
            .jem-pdf-category-description { color: #374151; font-size: ' . $baseFontSize . 'pt; line-height: ' . ($baseFontSize + 3) . 'pt; margin-top: 1mm; }
            .jem-pdf-category-meta { color: #4b5563; font-size: ' . $baseFontSize . 'pt; margin-top: 1mm; }
            .jem-pdf-type-badge { color: #ffffff; font-weight: bold; border-radius: 1.5mm; padding: 0.5mm 1.6mm; }
            .jem-pdf-preview-title { font-family: ' . $headerFontFamily . '; font-weight: bold; color: #111827; margin-top: 2mm; }
            .jem-pdf-preview-event { border-top: 0.15mm solid #e5e7eb; padding: 1.3mm 0; font-size: ' . $baseFontSize . 'pt; }
            .jem-pdf-preview-event-title { font-family: ' . $headerFontFamily . '; font-weight: bold; color: #1f5b99; }
            .jem-pdf-preview-meta { color: #111827; line-height: ' . ($baseFontSize + 3) . 'pt; }
            .jem-pdf-muted { color: #6b7280; }
        </style>';
        $html[] = '<h1>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h1>';
        $intro = self::buildViewTextBlock('intro');

        if ($intro !== '') {
            $html[] = $intro;
        }

        if (!$rows) {
            $html[] = '<div class="jem-pdf-category-card jem-pdf-muted">' . Text::_('COM_JEM_NO_CATEGORY') . '</div>';
        }

        $currentTypeId = null;
        foreach ($rows as $row) {
            $typeId = (int) ($row->type_id ?? 0);

            if (!empty($typeItems) && $typeId !== $currentTypeId) {
                $currentTypeId = $typeId;
                $typeName = '';

                if ($typeId > 0 && !empty($typeItems[$typeId])) {
                    $typeName = trim((string) ($typeItems[$typeId]->name ?? ''));
                }

                if ($typeName !== '') {
                    $html[] = '<div class="jem-pdf-category-type">' . htmlspecialchars($typeName, ENT_COMPAT, 'UTF-8') . '</div>';
                }
            }

            $categoryName = trim((string) ($row->catname ?? $row->title ?? ''));
            $categoryTitle = htmlspecialchars($categoryName, ENT_COMPAT, 'UTF-8');

            if (!empty($row->linktarget)) {
                $categoryTitle = '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_($row->linktarget, false)), ENT_COMPAT, 'UTF-8') . '">' . $categoryTitle . '</a>';
            } elseif (!empty($row->slug)) {
                $categoryTitle = '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_(JemHelperRoute::getCategoryRoute($row->slug), false)), ENT_COMPAT, 'UTF-8') . '">' . $categoryTitle . '</a>';
            }

            $typeBadge = self::buildCategoryTypeBadge($row, $typeItems);
            $description = trim((string) ($row->description ?? ''));
            $descriptionHtml = '';

            if ($description !== '' && $description !== Text::_('COM_JEM_NO_DESCRIPTION')) {
                $descriptionHtml = '<div class="jem-pdf-category-description">' . self::normaliseEditorHtmlForPdf($description) . '</div>';
            }

            $subcats = self::buildCategorySubcategoryList((array) ($row->subcats ?? array()));
            $eventCount = (int) ($row->assignedevents ?? 0);
            $meta = array();

            if ($typeBadge !== '') {
                $meta[] = $typeBadge;
            }

            if ($eventCount > 0) {
                $meta[] = htmlspecialchars((string) $eventCount . ' ' . Text::_('COM_JEM_EVENTS'), ENT_COMPAT, 'UTF-8');
            }

            if ($subcats !== '') {
                $meta[] = '<strong>' . Text::_('COM_JEM_SUBCATEGORIES') . ':</strong> ' . $subcats;
            }

            $html[] = '<div class="jem-pdf-category-card">'
                . '<div class="jem-pdf-category-name">' . $categoryTitle . '</div>'
                . ($meta ? '<div class="jem-pdf-category-meta">' . implode(' &nbsp; ', $meta) . '</div>' : '')
                . $descriptionHtml
                . self::buildCategoryEventPreviewHtml((array) ($row->events ?? array()))
                . '</div>';
        }

        $footer = self::buildViewTextBlock('footer');

        if ($footer !== '') {
            $html[] = $footer;
        }

        return implode("\n", $html);
    }

    private static function buildCategoryTypeBadge($row, array $typeItems): string
    {
        $typeId = (int) ($row->type_id ?? 0);

        if ($typeId <= 0 || empty($typeItems[$typeId])) {
            return '';
        }

        $type = $typeItems[$typeId];
        $name = trim((string) ($type->name ?? ''));

        if ($name === '') {
            return '';
        }

        $color = trim((string) ($type->color ?? ''));

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6b7280';
        }

        return '<span class="jem-pdf-type-badge" style="background-color:' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8')
            . '; color:' . self::getContrastingTextColor($color) . ';">'
            . htmlspecialchars($name, ENT_COMPAT, 'UTF-8')
            . '</span>';
    }

    private static function buildCategorySubcategoryList(array $subcats): string
    {
        $items = array();

        foreach ($subcats as $subcat) {
            $name = trim((string) ($subcat->catname ?? $subcat->title ?? ''));

            if ($name === '') {
                continue;
            }

            $label = htmlspecialchars($name, ENT_COMPAT, 'UTF-8');
            $slug = $subcat->slug ?? $subcat->catslug ?? '';

            if ($slug !== '') {
                $label = '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_(JemHelperRoute::getCategoryRoute($slug), false)), ENT_COMPAT, 'UTF-8') . '">' . $label . '</a>';
            }

            $items[] = $label;
        }

        return implode(', ', $items);
    }

    private static function buildCategoryEventPreviewHtml(array $events): string
    {
        if (!$events) {
            return '';
        }

        $html = array();
        $html[] = '<div class="jem-pdf-preview-title">' . Text::_('COM_JEM_EVENTS') . '</div>';

        foreach ($events as $event) {
            $title = (string) (self::buildPdfEventLink($event)['html'] ?? '');
            $date = self::htmlToPlainText(JemOutput::formatShortDateTime($event->dates ?? '', $event->times ?? '', $event->enddates ?? '', $event->endtimes ?? ''));
            $meta = array();

            if ($date !== '') {
                $meta[] = '&#9719; ' . htmlspecialchars($date, ENT_COMPAT, 'UTF-8');
            }

            if (!empty($event->venue)) {
                $venue = (string) (self::buildPdfVenueLink($event)['html'] ?? '');
                if ($venue !== '') {
                    $meta[] = '&#9679; ' . $venue;
                }
            }

            if (!empty($event->city)) {
                $meta[] = '&#9638; ' . htmlspecialchars((string) $event->city, ENT_COMPAT, 'UTF-8');
            }

            if (!empty($event->state)) {
                $meta[] = '&#9636; ' . htmlspecialchars((string) $event->state, ENT_COMPAT, 'UTF-8');
            }

            $categories = self::buildPdfCategoryLinks((array) ($event->categories ?? array()));
            if ($categories !== '') {
                $meta[] = '&#9670; ' . $categories;
            }

            $html[] = '<div class="jem-pdf-preview-event">'
                . '<div class="jem-pdf-preview-event-title">' . $title . '</div>'
                . ($meta ? '<div class="jem-pdf-preview-meta">' . implode(' &nbsp; ', $meta) . '</div>' : '')
                . '</div>';
        }

        return implode("\n", $html);
    }

    private static function buildCategoryListRows(array $rows, array $typeItems): array
    {
        $tableRows = array();

        foreach ($rows as $row) {
            $categoryName = trim((string) ($row->catname ?? $row->title ?? ''));
            $categoryHtml = htmlspecialchars($categoryName, ENT_COMPAT, 'UTF-8');
            if (!empty($row->linktarget)) {
                $categoryHtml = '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_($row->linktarget, false)), ENT_COMPAT, 'UTF-8') . '">' . $categoryHtml . '</a>';
            } elseif (!empty($row->slug)) {
                $categoryHtml = '<a href="' . htmlspecialchars(self::absoluteUrl(Route::_(JemHelperRoute::getCategoryRoute($row->slug), false)), ENT_COMPAT, 'UTF-8') . '">' . $categoryHtml . '</a>';
            }

            $typeName = '';
            $typeId = (int) ($row->type_id ?? 0);
            if ($typeId > 0 && !empty($typeItems[$typeId])) {
                $typeName = (string) ($typeItems[$typeId]->name ?? '');
            }

            $description = self::htmlToPlainText((string) ($row->description ?? ''));
            $description = self::truncatePlainText($description, 180)['text'];

            $subcats = array();
            foreach ((array) ($row->subcats ?? array()) as $subcat) {
                $subName = trim((string) ($subcat->catname ?? $subcat->title ?? ''));
                if ($subName !== '') {
                    $subcats[] = $subName;
                }
            }

            $tableRows[] = array(
                array('html' => $categoryHtml),
                $typeName,
                $description,
                (string) (int) ($row->assignedevents ?? 0),
                implode(', ', $subcats),
            );
        }

        return $tableRows;
    }

    private static function buildLinkedEventListHtml(string $title, array $rows, string $paperSize, string $preListHtml = ''): string
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

        if ($preListHtml !== '') {
            $html[] = $preListHtml;
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

    private static function buildPdfMapLink($row, string $mapProvider, string $labelKey = 'COM_JEM_MAP'): array
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

        return array('html' => '<a href="' . htmlspecialchars($url, ENT_COMPAT, 'UTF-8') . '">' . Text::_($labelKey) . '</a>');
    }

    private static function buildSpecialDayBadgesHtml(array $specialDays): string
    {
        if (!$specialDays) {
            return '';
        }

        $badges = array();

        foreach ($specialDays as $specialDay) {
            $title = trim((string) (($specialDay['title'] ?? '') ?: ($specialDay['type'] ?? '')));

            if ($title === '') {
                continue;
            }

            $color = !empty($specialDay['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $specialDay['color'])
                ? strtolower((string) $specialDay['color'])
                : '#e5e7eb';
            $textColor = self::getContrastingTextColor($color);

            $badges[] = '<span class="jem-pdf-special-day-badge" style="background-color:' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8')
                . '; color:' . htmlspecialchars($textColor, ENT_COMPAT, 'UTF-8') . ';">'
                . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</span>';
        }

        return $badges ? '<div class="jem-pdf-special-days">' . implode(' ', $badges) . '</div>' : '';
    }

    private static function buildPdfEventIndicators($row, bool $includeType = true): string
    {
        $html = '';

        if (!empty($row->featured)) {
            $html .= ' <span class="jem-pdf-muted">!</span>';
        }

        if ($includeType && !empty($row->type_name)) {
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

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
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * Event-Raw
 */
class JemViewEvent extends HtmlView
{
    /**
     * Creates the output for the event view
     */
    public function display($tpl = null)
    {
        $settings = JemHelper::globalattribs();
        $app = Factory::getApplication();
        $layout = $app->input->getCmd('layout', '');

        if ($layout === 'pdf') {
            $this->renderPdf();

            return;
        }

        // check iCal global setting
        if ($settings->get('global_show_ical_icon','0')==1) {
            // Get data from the model
            $row = $this->get('Item');

            if (empty($row)) {
                return;
            }

            $row->categories = $this->get('Categories');
            $row->id         = $row->did;
            $row->slug       = $row->alias ? ($row->id.':'.$row->alias) : $row->id;
            $params          = $row->params;

            // check individual iCal Event setting
            if ($params->get('event_show_ical_icon',1)) {
                // initiate new CALENDAR
                $vcal     = JemHelper::getCalendarTool();
                $filename = "event" . $row->did . ".ics";
                JemHelper::icalAddEvent($vcal, $row);
                // generate and redirect output to user browser
                JemHelper::sendCalendar($vcal, $filename);
            }
        }
    }

    /**
     * Creates a PDF document for the current event.
     */
    private function renderPdf(): void
    {
        if (!class_exists('JemPdf', false) || !JemPdf::isAvailable()) {
            Factory::getApplication()->close();

            return;
        }

        $row = $this->get('Item');

        if (empty($row)) {
            Factory::getApplication()->close();

            return;
        }

        $row->categories = $this->get('Categories');
        $row->contacts = $this->get('Contacts');
        $row->id = $row->did;
        $row->slug = $row->alias ? ($row->id . ':' . $row->alias) : $row->id;
        $row->venueslug = $row->localias ? ($row->locid . ':' . $row->localias) : $row->locid;
        $this->applyPdfEventPageParams($row);
        $this->applyPdfInheritedEventData($row);

        $jemsettings = JemHelper::config();
        $title = trim((string) $row->title);
        $paperSize = $this->normalisePdfPaperSize((string) ($jemsettings->pdf_paper_size ?? 'A4'));
        $singlePageTarget = JemPdf::prefersSinglePage($paperSize);
        $pdf = JemPdf::createDocument(
            $title !== '' ? $title : Text::_('COM_JEM_EVENT'),
            $this->normalisePdfOrientation((string) ($jemsettings->pdf_orientation ?? 'P')),
            $paperSize
        );

        if (!$pdf) {
            Factory::getApplication()->close();

            return;
        }

        $margins = JemPdf::fitSinglePageMargins($this->getPdfMargins($jemsettings), $paperSize);
        $pdf->SetMargins($margins['left'], $margins['top'], $margins['right']);
        $pdf->SetAutoPageBreak(!$singlePageTarget, $singlePageTarget ? 0 : $margins['bottom']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->AddPage();
        $pdf->writeHTML($this->buildEventPdfHtml($row, $singlePageTarget, $paperSize), true, false, true, false, '');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $alias = trim((string) ($row->alias ?: $row->did));
        $alias = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $alias);
        $pdf->Output('jem-event-' . $alias . '.pdf', 'D');
        Factory::getApplication()->close();
    }

    /**
     * Builds the TCPDF-compatible HTML for an event.
     */
    private function buildEventPdfHtml($row, bool $singlePageTarget = false, string $paperSize = 'A4'): string
    {
        $categoryNames = array();
        $jemsettings = JemHelper::config();
        $params = (!empty($row->params) && is_object($row->params) && method_exists($row->params, 'get'))
            ? $row->params
            : JemHelper::globalattribs();
        $posterScale = JemPdf::getPosterScale($paperSize);
        $fallbackImageWidth = max(1, min(200, (int) ($jemsettings->pdf_imagewidth ?? 40)));
        $fallbackImageHeight = max(1, min(200, (int) ($jemsettings->pdf_imageheight ?? 40)));
        $eventImageWidth = max(1, min(200, (int) ($jemsettings->pdf_event_imagewidth ?? $fallbackImageWidth)));
        $eventImageHeight = max(1, min(200, (int) ($jemsettings->pdf_event_imageheight ?? $fallbackImageHeight)));
        $venueImageWidth = max(1, min(200, (int) ($jemsettings->pdf_venue_imagewidth ?? $fallbackImageWidth)));
        $venueImageHeight = max(1, min(200, (int) ($jemsettings->pdf_venue_imageheight ?? $fallbackImageHeight)));
        if ($singlePageTarget) {
            $eventImageWidth = max(1, min(200, (int) round($eventImageWidth * $posterScale)));
            $eventImageHeight = max(1, min(200, (int) round($eventImageHeight * $posterScale)));
            $venueImageWidth = max(1, min(200, (int) round($venueImageWidth * $posterScale)));
            $venueImageHeight = max(1, min(200, (int) round($venueImageHeight * $posterScale)));
        }
        $showImages = (int) ($jemsettings->pdf_event_show_images ?? 1) === 1;
        $eventImageHtml = $showImages ? $this->buildPdfEventImageHtml($row, $eventImageWidth, $eventImageHeight) : '';
        $venueImageHtml = $showImages ? $this->buildPdfImageHtml($row->locimage ?? '', 'venue', (string) $row->venue, $venueImageWidth, $venueImageHeight) : '';
        $includeLinks = (int) ($jemsettings->pdf_event_include_links ?? 1) === 1;
        $includeAttachments = (int) ($jemsettings->pdf_event_include_attachments ?? 1) === 1;
        $eventLinksHtml = $includeLinks && !empty($row->event_links) ? $this->buildPdfEventLinksHtml((array) $row->event_links) : '';
        $eventAttachmentsHtml = $includeAttachments && !empty($row->attachments) ? $this->buildPdfAttachmentsHtml(Text::_('COM_JEM_FILES'), (array) $row->attachments) : '';
        $venueAttachmentsHtml = $includeAttachments && !empty($row->vattachments) ? $this->buildPdfAttachmentsHtml(Text::_('COM_JEM_FILES'), (array) $row->vattachments) : '';
        $eventImagePosition = $this->normalisePdfImagePosition((string) ($jemsettings->pdf_event_image_position ?? 'right'));
        $venueImagePosition = $this->normalisePdfImagePosition((string) ($jemsettings->pdf_venue_image_position ?? 'right'));
        $venueMode = in_array((string) ($jemsettings->pdf_event_venue_mode ?? 'full'), array('full', 'summary', 'none'), true)
            ? (string) $jemsettings->pdf_event_venue_mode
            : 'full';
        $isCompact = (string) ($jemsettings->pdf_event_layout ?? 'details') === 'compact';
        $showEventDetailsTitle = (int) $params->get('event_show_detailstitle', 1) === 1;
        $showEventVenueName = (int) $params->get('event_show_venue_name', 0) === 1;
        $showEventCategory = (int) $params->get('event_show_category', 0) === 1;
        $showEventAuthor = (int) $params->get('event_show_author', 0) === 1;
        $showEventDescription = (int) $params->get('event_show_description', 1) === 1;
        $showVenueSection = (int) $params->get('event_show_venue', 1) === 1;
        $showVenueAddress = (int) $params->get('event_show_detailsadress', 1) === 1;
        $showVenueDescription = (int) $params->get('event_show_locdescription', 1) === 1;
        $showVenueMap = in_array((int) $params->get('event_show_mapserv', 0), array(1, 2, 3, 4, 5), true);
        $eventHeadingDisplay = (string) $params->get('event_heading_display', 'label_name');
        $eventHeadingDisplay = in_array($eventHeadingDisplay, array('label', 'label_name', 'name'), true) ? $eventHeadingDisplay : 'label_name';
        $eventVenueHeadingDisplay = (string) $params->get('event_venue_heading_display', 'label_name');
        $eventVenueHeadingDisplay = in_array($eventVenueHeadingDisplay, array('label', 'label_name', 'name'), true) ? $eventVenueHeadingDisplay : 'label_name';
        $showOnlineMeeting = (int) $params->get('event_show_online_meeting', 1) === 1;
        $showContacts = (int) $params->get('event_show_contact', 0) === 1;
        $showRegistration = (int) $params->get('event_show_registration', 1) === 1;
        $eventDescriptionMode = $this->normalisePdfDescriptionMode((string) ($jemsettings->pdf_event_description_mode ?? 'complete'));
        $venueDescriptionMode = $this->normalisePdfDescriptionMode((string) ($jemsettings->pdf_venue_description_mode ?? 'complete'));
        $baseFontSize = max(6, min(16, (int) ($jemsettings->pdf_base_font_size ?? 8)));
        $headingFontSize = max(8, min(24, (int) ($jemsettings->pdf_heading_font_size ?? 12)));
        $titleFontSize = max(8, min(40, (int) ($jemsettings->pdf_title_font_size ?? 18)));
        if ($singlePageTarget) {
            $baseFontSize = max(6, min(18, (int) round($baseFontSize * $posterScale)));
            $headingFontSize = max(8, min(28, (int) round($headingFontSize * $posterScale)));
            $titleFontSize = max(8, min(42, (int) round($titleFontSize * $posterScale)));
        }
        $titleFontSize = min($singlePageTarget ? 42 : 28, $titleFontSize);
        $titleMargin = $singlePageTarget ? 2 : 5;
        $headingMarginTop = $singlePageTarget ? 3 : 6;
        $sectionLineHeight = $baseFontSize + ($singlePageTarget ? 2 : 3);
        $footerFontSize = max(6, $baseFontSize - 1);
        $backgroundColor = $this->normalisePdfColor((string) ($jemsettings->pdf_background_color ?? '#ffffff'), '#ffffff');
        $accentColor = $this->normalisePdfColor((string) ($jemsettings->pdf_accent_color ?? '#1d4ed8'), '#1d4ed8');

        foreach ((array) $row->categories as $category) {
            $name = trim((string) ($category->catname ?? ''));

            if ($name !== '') {
                $categoryNames[] = $name;
            }
        }

        $description = $this->normalisePdfHtml($this->getPdfEventDescriptionHtml($row, $eventDescriptionMode));
        $venueDescription = $this->normalisePdfHtml($this->getPdfSplitDescriptionHtml((string) ($row->locdescription ?? ''), $venueDescriptionMode));
        $address = array_filter(array(
            trim((string) ($row->street ?? '')),
            trim(trim((string) ($row->postalCode ?? '') . ' ' . trim((string) ($row->city ?? '')))),
            trim((string) ($row->state ?? '')),
            trim((string) ($row->country ?? '')),
        ));

        $html = array();
        $html[] = '<style>
            body { background-color: ' . $backgroundColor . '; color: #111827; font-size: ' . $baseFontSize . 'pt; }
            h1 { color: #111827; font-size: ' . $titleFontSize . 'pt; line-height: ' . ($titleFontSize + 4) . 'pt; margin: 0 0 ' . $titleMargin . 'mm 0; }
            h2 { color: #111827; font-size: ' . $headingFontSize . 'pt; margin: ' . $headingMarginTop . 'mm 0 1.5mm 0; border-bottom: 0.25mm solid #cbd5e1; }
            .jem-pdf-event-kicker { color: #4b5563; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5pt; }
            .jem-pdf-event-summary { background-color: #ffffff; padding: 1mm 0; }
            .jem-pdf-event-summary td { font-size: ' . $baseFontSize . 'pt; padding: 1mm; vertical-align: top; }
            .jem-pdf-title-with-type { font-size: ' . $titleFontSize . 'pt; font-weight: bold; color: #111827; }
            .jem-pdf-type-badge { color: #ffffff; font-size: 7pt; font-weight: bold; border-radius: 2mm; padding: 1mm 2mm; }
            .jem-pdf-label { color: #111827; font-weight: bold; width: 24%; }
            .jem-pdf-image-cell { text-align: right; vertical-align: top; }
            .jem-pdf-image { border: 0.3mm solid #d1d5db; }
            .jem-pdf-description-image { border: 0.2mm solid #d1d5db; margin: 0 3mm 2mm 0; }
            .jem-pdf-section { font-size: ' . $baseFontSize . 'pt; line-height: ' . $sectionLineHeight . 'pt; }
            .jem-pdf-inline-link { color: ' . $accentColor . '; text-decoration: underline; }
            .jem-pdf-link-card { border: 0.25mm solid ' . $accentColor . '; border-radius: 1.5mm; padding: 2mm; background-color: #ffffff; }
            .jem-pdf-link-card td { font-size: ' . $baseFontSize . 'pt; vertical-align: middle; }
            .jem-pdf-link-bar { width: 2.5mm; }
            .jem-pdf-link-image { border: 0.2mm solid #d1d5db; }
            .jem-pdf-link-title { color: ' . $accentColor . '; font-weight: bold; }
            .jem-pdf-link-description { color: #6d28d9; font-size: 7pt; }
            .jem-pdf-file-table td { font-size: ' . $baseFontSize . 'pt; padding: 1mm 1.5mm; vertical-align: top; }
            .jem-pdf-file-icon { color: #111827; font-weight: bold; }
            .jem-pdf-button { background-color: #b45309; color: #ffffff; font-weight: bold; text-align: center; border-radius: 1.4mm; padding: 2mm 5mm; }
            .jem-pdf-separator { border-top: 0.25mm solid #d1d5db; height: 2mm; }
            .jem-pdf-view-intro, .jem-pdf-view-footer-text { font-size: ' . $baseFontSize . 'pt; line-height: ' . $sectionLineHeight . 'pt; }
            .jem-pdf-view-intro { margin-bottom: 4mm; }
            .jem-pdf-view-footer-text { margin-top: 4mm; border-top: 0.2mm solid #d1d5db; padding-top: 2mm; }
            .jem-pdf-muted { color: #6b7280; }
        </style>';
        $html[] = '<div class="jem-pdf-title-with-type">' . htmlspecialchars((string) $row->title, ENT_COMPAT, 'UTF-8') . '</div>';
        $intro = JemPdfView::buildViewTextBlock('intro');

        if ($intro !== '') {
            $html[] = $intro;
        }

        $html[] = '<h2>' . $this->buildPdfBlockHeading($eventHeadingDisplay, Text::_('COM_JEM_EVENT'), (string) $row->title) . ' &#8635;</h2>';
        $eventSummaryHtml = array();
        $eventSummaryHtml[] = '<table class="jem-pdf-event-summary" width="100%" cellpadding="2" cellspacing="0">';
        if ($showEventDetailsTitle) {
            $eventSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_TITLE'), htmlspecialchars((string) $row->title, ENT_COMPAT, 'UTF-8') . $this->buildPdfTypeBadge($row));
        }
        $eventSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_WHEN'), htmlspecialchars($this->htmlToPlainText(JemOutput::formatLongDateTime($row->dates, $row->times, $row->enddates, $row->endtimes)), ENT_COMPAT, 'UTF-8'));

        if ($showEventVenueName && !empty($row->venue)) {
            $venue = htmlspecialchars((string) $row->venue, ENT_COMPAT, 'UTF-8');
            $location = trim(implode(', ', array_filter(array($row->city ?? '', $row->state ?? '', $row->country ?? ''))));
            $eventSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_WHERE'), '<a class="jem-pdf-inline-link" href="' . htmlspecialchars($this->buildPdfVenueUrl($row), ENT_COMPAT, 'UTF-8') . '">' . $venue . '</a>' . ($location !== '' ? ' - ' . htmlspecialchars($location, ENT_COMPAT, 'UTF-8') : ''));
        }

        if ($showEventCategory && $categoryNames) {
            $eventSummaryHtml[] = $this->buildSummaryRow(count($categoryNames) > 1 ? Text::_('COM_JEM_CATEGORIES') : Text::_('COM_JEM_CATEGORY'), htmlspecialchars(implode(', ', $categoryNames), ENT_COMPAT, 'UTF-8'));
        }

        if (!$isCompact && $showEventAuthor && !empty($row->author)) {
            $eventSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_EVENT_CREATED_BY_LABEL'), htmlspecialchars((string) ($row->created_by_alias ?: $row->author), ENT_COMPAT, 'UTF-8'));
        }

        $eventSummaryHtml[] = '</table>';
        $html[] = $this->buildPdfImageLayoutHtml(implode("\n", $eventSummaryHtml), $eventImageHtml, $eventImagePosition, 68, 32);

        if ($showEventDescription && $description !== '') {
            $html[] = '<h2>' . Text::_('COM_JEM_DESCRIPTION') . '</h2>';
            $html[] = '<div class="jem-pdf-section">' . $description . '</div>';
        }

        if ($showOnlineMeeting && (int) ($jemsettings->pdf_event_include_online_meeting ?? 1) === 1 && !empty($row->online_meeting_url)) {
            $meetingLabel = !empty($row->online_meeting_label) ? (string) $row->online_meeting_label : Text::_('COM_JEM_ONLINE_MEETING');
            $html[] = '<h2>' . htmlspecialchars($meetingLabel, ENT_COMPAT, 'UTF-8') . '</h2>';
            $html[] = '<div class="jem-pdf-section"><a class="jem-pdf-inline-link" href="' . htmlspecialchars((string) $row->online_meeting_url, ENT_COMPAT, 'UTF-8') . '">' . htmlspecialchars((string) $row->online_meeting_url, ENT_COMPAT, 'UTF-8') . '</a></div>';
        }

        if ($eventLinksHtml !== '') {
            $html[] = $eventLinksHtml;
        }

        if ($eventAttachmentsHtml !== '') {
            $html[] = $eventAttachmentsHtml;
        }

        if ($showVenueSection && $venueMode !== 'none' && !empty($row->venue)) {
            $html[] = '<h2>' . $this->buildPdfBlockHeading($eventVenueHeadingDisplay, Text::_('COM_JEM_VENUE'), (string) $row->venue) . '</h2>';
            $venueSummaryHtml = array();
            $venueSummaryHtml[] = '<table class="jem-pdf-event-summary" width="100%" cellpadding="2" cellspacing="0">';
            $venueSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_LOCATION'), '<a class="jem-pdf-inline-link" href="' . htmlspecialchars($this->buildPdfVenueUrl($row), ENT_COMPAT, 'UTF-8') . '">' . htmlspecialchars((string) $row->venue, ENT_COMPAT, 'UTF-8') . '</a>' . $this->buildPdfTypedBadge($row, 'venue_type_', 'venue'));

            if ($showVenueAddress && !empty($row->street)) {
                $venueSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_STREET'), htmlspecialchars((string) $row->street, ENT_COMPAT, 'UTF-8'));
            }

            if ($showVenueAddress && !empty($row->postalCode)) {
                $venueSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_ZIP'), htmlspecialchars((string) $row->postalCode, ENT_COMPAT, 'UTF-8'));
            }

            if ($showVenueAddress && !empty($row->city)) {
                $venueSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_CITY'), htmlspecialchars((string) $row->city, ENT_COMPAT, 'UTF-8'));
            }

            if ($showVenueAddress && !empty($row->state)) {
                $venueSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_STATE'), htmlspecialchars((string) $row->state, ENT_COMPAT, 'UTF-8'));
            }

            if ($showVenueAddress && !empty($row->country)) {
                $venueSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_COUNTRY'), htmlspecialchars((string) $row->country, ENT_COMPAT, 'UTF-8'));
            }

            if (!empty($row->url)) {
                $venueSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_WEBSITE'), '<a class="jem-pdf-inline-link" href="' . htmlspecialchars((string) $row->url, ENT_COMPAT, 'UTF-8') . '">' . htmlspecialchars((string) $row->url, ENT_COMPAT, 'UTF-8') . '</a>');
            }

            if (!empty($row->email)) {
                $venueSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_EMAIL'), '<a class="jem-pdf-inline-link" href="mailto:' . htmlspecialchars((string) $row->email, ENT_COMPAT, 'UTF-8') . '">' . htmlspecialchars((string) $row->email, ENT_COMPAT, 'UTF-8') . '</a>');
            }

            if ($showVenueMap && (string) ($jemsettings->pdf_event_include_venue_map ?? 'none') === 'link') {
                $mapUrl = $this->buildPdfVenueMapUrl($row);

                if ($mapUrl !== '') {
                    $venueSummaryHtml[] = $this->buildSummaryRow(Text::_('COM_JEM_MAP'), '<a class="jem-pdf-inline-link" href="' . htmlspecialchars($mapUrl, ENT_COMPAT, 'UTF-8') . '">' . Text::_('COM_JEM_MAP') . '</a>');
                }
            }

            $venueSummaryHtml[] = '</table>';
            $html[] = $this->buildPdfImageLayoutHtml(implode("\n", $venueSummaryHtml), $venueImageHtml, $venueImagePosition, 72, 28);

            if ($venueMode === 'full' && $showVenueDescription && $venueDescription !== '') {
                $html[] = '<h2>' . Text::_('COM_JEM_DESCRIPTION') . '</h2>';
                $html[] = '<div class="jem-pdf-section">' . $venueDescription . '</div>';
            }

            if ($venueMode === 'full' && $venueAttachmentsHtml !== '') {
                $html[] = $venueAttachmentsHtml;
            }
        }

        if ($showContacts && (int) ($jemsettings->pdf_event_include_contacts ?? 1) === 1 && !empty($row->contacts)) {
            $html[] = $this->buildPdfContactsHtml((array) $row->contacts);
        }

        if ($showRegistration && (int) ($jemsettings->pdf_event_include_registration ?? 1) === 1 && $this->eventAllowsRegistration($row)) {
            $html[] = $this->buildPdfRegistrationHtml($row);
        }

        $footer = JemPdfView::buildViewTextBlock('footer');

        if ($footer !== '') {
            $html[] = $footer;
        }

        return implode("\n", $html);
    }

    /**
     * Applies the same Event Page parameter precedence used by the HTML event view.
     */
    private function applyPdfEventPageParams($row): void
    {
        $app = Factory::getApplication();
        $params = $app->getParams('com_jem');
        $menuitem = $app->getMenu()->getActive();
        $useMenuItemParams = ($menuitem
            && isset($menuitem->query['option'], $menuitem->query['view'], $menuitem->query['id'])
            && $menuitem->query['option'] === 'com_jem'
            && $menuitem->query['view'] === 'event'
            && (int) $menuitem->query['id'] === (int) $row->id);

        if (empty($row->params) || !is_object($row->params) || !method_exists($row->params, 'merge')) {
            $row->params = JemHelper::globalattribs();
        }

        if ($useMenuItemParams) {
            $row->params->merge($params);

            return;
        }

        $eventParams = $row->params;
        $row->params = clone $params;
        $row->params->merge($eventParams);
    }

    /**
     * Inherits links and attachments from the root event for recurring instances.
     */
    private function applyPdfInheritedEventData($row): void
    {
        if (empty($row->recurrence_first_id)) {
            return;
        }

        if (!empty($row->attachments) && !empty($row->event_links)) {
            return;
        }

        $model = $this->getModel();

        if (!$model || !method_exists($model, 'getItem')) {
            return;
        }

        $root = $model->getItem((int) $row->recurrence_first_id);

        if (!$root) {
            return;
        }

        if (empty($row->attachments) && !empty($root->attachments)) {
            $row->attachments = $root->attachments;
        }

        if (empty($row->event_links) && !empty($root->event_links)) {
            $row->event_links = $root->event_links;
        }
    }

    /**
     * Builds an image/text layout block for the PDF.
     */
    private function buildPdfImageLayoutHtml(string $contentHtml, string $imageHtml, string $position, int $contentWidth, int $imageWidth): string
    {
        if ($imageHtml === '') {
            return $contentHtml;
        }

        if ($position === 'top') {
            return '<div style="text-align:center;">' . $imageHtml . '</div>' . "\n" . $contentHtml;
        }

        if ($position === 'left') {
            return '<table width="100%" cellpadding="0" cellspacing="0"><tr>'
                . '<td class="jem-pdf-image-cell" width="' . (int) $imageWidth . '%" style="text-align:left;">' . $imageHtml . '</td>'
                . '<td width="' . (int) $contentWidth . '%">' . $contentHtml . '</td>'
                . '</tr></table>';
        }

        return '<table width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td width="' . (int) $contentWidth . '%">' . $contentHtml . '</td>'
            . '<td class="jem-pdf-image-cell" width="' . (int) $imageWidth . '%">' . $imageHtml . '</td>'
            . '</tr></table>';
    }

    /**
     * Returns configured PDF margins in mm.
     */
    private function getPdfMargins($settings): array
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

        return in_array($orientation, array('P', 'L'), true) ? $orientation : 'P';
    }

    /**
     * Normalises a hex colour value.
     */
    private function normalisePdfColor(string $color, string $fallback): string
    {
        $color = trim($color);

        return preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : $fallback;
    }

    /**
     * Normalises a PDF image position value.
     */
    private function normalisePdfImagePosition(string $position): string
    {
        return in_array($position, array('left', 'right', 'top'), true) ? $position : 'right';
    }

    /**
     * Normalises a PDF description mode value.
     */
    private function normalisePdfDescriptionMode(string $mode): string
    {
        return in_array($mode, array('none', 'intro', 'full', 'complete'), true) ? $mode : 'complete';
    }

    /**
     * Returns the selected event description segment.
     */
    private function getPdfEventDescriptionHtml($row, string $mode): string
    {
        if ($mode === 'none') {
            return '';
        }

        $intro = trim((string) ($row->introtext ?? ''));
        $full = trim((string) ($row->fulltext ?? ''));

        if ($mode === 'intro') {
            return $intro;
        }

        if ($mode === 'full') {
            return $full;
        }

        return trim($intro . ' ' . $full);
    }

    /**
     * Returns the selected segment from an HTML field that may contain
     * Joomla's read more separator.
     */
    private function getPdfSplitDescriptionHtml(string $html, string $mode): string
    {
        if ($mode === 'none') {
            return '';
        }

        $parts = $this->splitPdfReadmoreHtml($html);

        if ($mode === 'intro') {
            return $parts['intro'];
        }

        if ($mode === 'full') {
            return $parts['full'];
        }

        return trim($parts['intro'] . ' ' . $parts['full']);
    }

    /**
     * Returns the intro segment before Joomla's read more separator.
     */
    private function getPdfIntroHtml(string $html): string
    {
        $parts = $this->splitPdfReadmoreHtml($html);

        return $parts['intro'];
    }

    /**
     * Splits an HTML field by Joomla's read more separator.
     */
    private function splitPdfReadmoreHtml(string $html): array
    {
        $parts = preg_split('#<hr\s+id=["\']system-readmore["\']\s*/?>#i', $html, 2);

        return array(
            'intro' => trim((string) ($parts[0] ?? $html)),
            'full' => trim((string) ($parts[1] ?? '')),
        );
    }

    /**
     * Builds a venue map URL when possible.
     */
    private function buildPdfVenueMapUrl($row): string
    {
        if (!empty($row->latitude) && !empty($row->longitude)) {
            return 'https://www.openstreetmap.org/?mlat=' . urlencode((string) $row->latitude)
                . '&mlon=' . urlencode((string) $row->longitude)
                . '#map=15/' . urlencode((string) $row->latitude)
                . '/' . urlencode((string) $row->longitude);
        }

        $address = implode(', ', array_filter(array(
            trim((string) ($row->venue ?? '')),
            trim((string) ($row->street ?? '')),
            trim((string) ($row->postalCode ?? '')),
            trim((string) ($row->city ?? '')),
            trim((string) ($row->state ?? '')),
            trim((string) ($row->country ?? '')),
        )));

        return $address !== '' ? 'https://www.openstreetmap.org/search?query=' . urlencode($address) : '';
    }

    /**
     * Builds the optional event type badge.
     */
    private function buildPdfTypeBadge($row): string
    {
        return $this->buildPdfTypedBadge($row, 'type_', 'event');
    }

    /**
     * Builds an optional entity type badge.
     */
    private function buildPdfTypedBadge($row, string $prefix = 'type_', string $entity = 'event'): string
    {
        JemOutput::translateType($row, $prefix);

        $typeName = trim((string) ($row->{$prefix . 'name'} ?? ''));

        if ($typeName === '') {
            return '';
        }

        $color = trim((string) ($row->{$prefix . 'color'} ?? ''));

        if (!preg_match('/^#[0-9a-f]{3,6}$/i', $color)) {
            $color = '#6d28d9';
        }

        $badge = '<span class="jem-pdf-type-badge" style="background-color: ' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8') . ';">' . htmlspecialchars($typeName, ENT_COMPAT, 'UTF-8') . '</span>';
        $typeId = (int) ($row->{$prefix . 'id'} ?? 0);

        if ($typeId > 0) {
            $slug = $typeId;
            $alias = (string) ($row->{$prefix . 'alias'} ?? '');

            if ($alias !== '') {
                $slug .= ':' . $alias;
            }

            $route = $entity === 'venue'
                ? JemHelperRoute::getTypevenuesRoute($slug)
                : JemHelperRoute::getTypeeventsRoute($slug);
            $badge = '<a href="' . htmlspecialchars($this->buildPdfAbsoluteUrl(Route::_($route, false)), ENT_COMPAT, 'UTF-8') . '">' . $badge . '</a>';
        }

        return ' ' . $badge;
    }

    /**
     * Builds one summary row.
     */
    private function buildSummaryRow(string $label, string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        return '<tr><td class="jem-pdf-label" width="24%">' . htmlspecialchars($label, ENT_COMPAT, 'UTF-8') . '</td><td width="76%">' . $value . '</td></tr>';
    }

    private function buildPdfBlockHeading(string $display, string $label, string $name): string
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

    /**
     * Normalises stored HTML for TCPDF output.
     */
    private function normalisePdfHtml(string $html): string
    {
        $html = str_replace('<hr id="system-readmore" />', '', $html);
        $html = str_replace('<hr id="system-readmore">', '', $html);
        $html = $this->normalisePdfDescriptionImages($html);
        $html = strip_tags($html, '<p><br><strong><b><em><i><ul><ol><li><a><img>');

        return trim($html);
    }

    /**
     * Converts frontend HTML snippets to plain text for PDF summary values.
     */
    private function htmlToPlainText(string $value): string
    {
        $value = str_replace(array('<br>', '<br/>', '<br />'), "\n", $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace("/[ \t]+/", ' ', $value);
        $value = preg_replace("/ *\n */", "\n", $value);

        return trim((string) $value);
    }

    /**
     * Builds an image tag for event and venue flyers.
     */
    private function buildPdfImageHtml(string $image, string $type, string $alt, int $maxWidth, int $maxHeight): string
    {
        $flyer = JemImage::flyercreator($image, $type);

        if (!empty($flyer['original'])) {
            $path = JPATH_SITE . '/' . $flyer['original'];

            if (is_file($path)) {
                return $this->buildPdfImageTag($path, $alt, $maxWidth, $maxHeight);
            }
        }

        $path = $this->resolvePdfImagePath($image, $type);

        if ($path === '') {
            return '';
        }

        return $this->buildPdfImageTag($path, $alt, $maxWidth, $maxHeight);
    }

    /**
     * Builds the configured event image.
     */
    private function buildPdfEventImageHtml($row, int $maxWidth, int $maxHeight): string
    {
        $candidates = array(
            $row->datimage ?? '',
            $row->article_content_image ?? '',
        );

        foreach ($candidates as $candidate) {
            $html = $this->buildPdfImageHtml((string) $candidate, 'event', (string) $row->title, $maxWidth, $maxHeight);

            if ($html !== '') {
                return $html;
            }
        }

        return '';
    }

    /**
     * Keeps local images embedded in descriptions and converts them to TCPDF-friendly tags.
     */
    private function normalisePdfDescriptionImages(string $html): string
    {
        if (stripos($html, '<img') === false) {
            return $html;
        }

        return (string) preg_replace_callback('/<img\b[^>]*>/i', function (array $match): string {
            $tag = (string) $match[0];
            $src = $this->extractPdfImageAttribute($tag, 'src');

            if ($src === '') {
                return '';
            }

            $path = $this->resolvePdfImagePath($src, 'event');

            if ($path === '') {
                return '';
            }

            $alt = $this->extractPdfImageAttribute($tag, 'alt');

            return $this->buildPdfDescriptionImageTag($path, $alt);
        }, $html);
    }

    /**
     * Builds an image tag for an image embedded in the description.
     */
    private function buildPdfDescriptionImageTag(string $path, string $alt): string
    {
        $size = @getimagesize($path);
        $width = 45;
        $height = 0;

        if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
            $ratio = min($width / (int) $size[0], 80 / (int) $size[1]);
            $width = max(1, round((int) $size[0] * $ratio, 1));
            $height = max(1, round((int) $size[1] * $ratio, 1));
        }

        $attributes = ' class="jem-pdf-description-image" src="' . htmlspecialchars(str_replace('\\', '/', $path), ENT_COMPAT, 'UTF-8') . '"'
            . ' width="' . htmlspecialchars((string) $width, ENT_COMPAT, 'UTF-8') . 'mm"';

        if ($height > 0) {
            $attributes .= ' height="' . htmlspecialchars((string) $height, ENT_COMPAT, 'UTF-8') . 'mm"';
        }

        return '<img' . $attributes . ' alt="' . htmlspecialchars($alt, ENT_COMPAT, 'UTF-8') . '" />';
    }

    /**
     * Builds an image tag for an already resolved local image path.
     */
    private function buildPdfImageTag(string $path, string $alt, int $maxWidth, int $maxHeight): string
    {
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

    /**
     * Resolves JEM image names, site-relative paths, and local absolute URLs to a local file path.
     */
    private function resolvePdfImagePath(string $image, string $type): string
    {
        $image = trim(html_entity_decode($image, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($image === '' || stripos($image, 'data:') === 0) {
            return '';
        }

        $path = $image;
        $parts = parse_url($image);

        if (is_array($parts) && !empty($parts['scheme'])) {
            if (!in_array(strtolower((string) $parts['scheme']), array('http', 'https'), true)) {
                return '';
            }

            $host = strtolower((string) ($parts['host'] ?? ''));
            $siteHost = strtolower(Uri::getInstance()->getHost());

            $localHosts = array('localhost', '127.0.0.1', '::1');

            if ($host !== '' && $siteHost !== '' && $host !== $siteHost && !in_array($host, $localHosts, true)) {
                return '';
            }

            $path = (string) ($parts['path'] ?? '');
        }

        $path = rawurldecode(str_replace('\\', '/', $path));
        $basePath = trim((string) Uri::root(true), '/');

        if ($basePath !== '' && strpos(ltrim($path, '/'), $basePath . '/') === 0) {
            $path = substr(ltrim($path, '/'), strlen($basePath) + 1);
        }

        $path = ltrim($path, '/');

        if ($path !== '' && is_file(JPATH_SITE . '/' . $path)) {
            return JPATH_SITE . '/' . $path;
        }

        $imagesPos = strpos($path, 'images/');

        if ($imagesPos !== false && $imagesPos > 0) {
            $imagePath = substr($path, $imagesPos);

            if (is_file(JPATH_SITE . '/' . $imagePath)) {
                return JPATH_SITE . '/' . $imagePath;
            }
        }

        $folders = array(
            'event' => 'events',
            'venue' => 'venues',
            'category' => 'categories',
        );

        if (strpos($path, '/') === false && isset($folders[$type]) && is_file(JPATH_SITE . '/images/jem/' . $folders[$type] . '/' . $path)) {
            return JPATH_SITE . '/images/jem/' . $folders[$type] . '/' . $path;
        }

        return '';
    }

    /**
     * Extracts an attribute value from an image tag.
     */
    private function extractPdfImageAttribute(string $tag, string $attribute): string
    {
        $attribute = preg_quote($attribute, '/');

        if (preg_match('/\b' . $attribute . '\s*=\s*(["\'])(.*?)\1/i', $tag, $match)) {
            return trim((string) $match[2]);
        }

        if (preg_match('/\b' . $attribute . '\s*=\s*([^>\s]+)/i', $tag, $match)) {
            return trim((string) $match[1], " \t\n\r\0\x0B\"'");
        }

        return '';
    }

    /**
     * Builds the PDF event links block.
     */
    private function buildPdfEventLinksHtml(array $links): string
    {
        $html = array();
        $html[] = '<h2>' . Text::_('COM_JEM_EVENT_LINKS') . '</h2>';
        $cards = array();

        foreach ($links as $link) {
            $label = trim((string) ($link->title ?? ''));
            $url = trim((string) ($link->url ?? ''));
            $description = trim((string) ($link->description ?? ''));
            $image = trim((string) ($link->image ?? ''));
            $color = trim((string) ($link->color ?? ''));

            if ($label === '' && $url === '' && $description === '' && $image === '') {
                continue;
            }

            if ($label === '') {
                $label = $url !== '' ? $url : $description;
            }

            if (!preg_match('/^#[0-9a-f]{3,6}$/i', $color)) {
                $color = '#1d4ed8';
            }

            $linkUrl = $url !== '' ? $this->buildPdfAbsoluteUrl($url) : '';
            $imageHtml = $this->buildPdfLinkImageHtml($image, $label);

            if ($linkUrl !== '' && $imageHtml !== '') {
                $imageHtml = '<a href="' . htmlspecialchars($linkUrl, ENT_COMPAT, 'UTF-8') . '">' . $imageHtml . '</a>';
            }

            $title = htmlspecialchars($label, ENT_COMPAT, 'UTF-8');

            if ($linkUrl !== '') {
                $title = '<a class="jem-pdf-link-title" href="' . htmlspecialchars($linkUrl, ENT_COMPAT, 'UTF-8') . '">' . $title . '</a>';
            }

            $text = '<span class="jem-pdf-link-title">' . $title . '</span>';

            if ($description !== '') {
                $text .= '<br /><span class="jem-pdf-link-description">' . nl2br(htmlspecialchars($description, ENT_COMPAT, 'UTF-8')) . '</span>';
            }

            if ($linkUrl !== '' && $description === '') {
                $text .= '<br /><span class="jem-pdf-muted">' . htmlspecialchars($linkUrl, ENT_COMPAT, 'UTF-8') . '</span>';
            }

            $cards[] = '<table class="jem-pdf-link-card" width="100%" cellpadding="3" cellspacing="0">'
                . '<tr>'
                . '<td class="jem-pdf-link-bar" width="2%" style="background-color: ' . htmlspecialchars($color, ENT_COMPAT, 'UTF-8') . ';">&nbsp;</td>'
                . ($imageHtml !== '' ? '<td width="18%">' . $imageHtml . '</td><td width="80%">' . $text . '</td>' : '<td width="98%">' . $text . '</td>')
                . '</tr>'
                . '</table>';
        }

        if (!$cards) {
            return '';
        }

        $html[] = implode('<br />', $cards);

        return implode("\n", $html);
    }

    /**
     * Builds a PDF attachments block.
     */
    private function buildPdfAttachmentsHtml(string $title, array $attachments): string
    {
        $html = array();
        $html[] = '<h2>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h2>';
        $rows = array();

        foreach ($attachments as $file) {
            $label = trim((string) (($file->name ?? '') ?: ($file->file ?? '')));

            if ($label === '') {
                continue;
            }

            $size = $this->getPdfAttachmentSize($file);
            $line = '<a class="jem-pdf-inline-link" href="' . htmlspecialchars($this->buildPdfAttachmentUrl($file), ENT_COMPAT, 'UTF-8') . '">' . htmlspecialchars($label, ENT_COMPAT, 'UTF-8') . '</a>';

            if ($size !== '') {
                $line .= ' <span class="jem-pdf-muted">[' . htmlspecialchars($size, ENT_COMPAT, 'UTF-8') . ']</span>';
            }

            if (!empty($file->description)) {
                $line .= '<br /><span class="jem-pdf-muted">' . nl2br(htmlspecialchars((string) $file->description, ENT_COMPAT, 'UTF-8')) . '</span>';
            }

            $rows[] = '<tr><td class="jem-pdf-file-icon" width="5%">&#9679;</td><td width="95%">' . $line . '</td></tr>';
        }

        if (!$rows) {
            return '';
        }

        $html[] = '<table class="jem-pdf-file-table" width="100%" cellpadding="1" cellspacing="0">' . implode('', $rows) . '</table>';

        return implode("\n", $html);
    }

    /**
     * Builds a local image tag for event link buttons.
     */
    private function buildPdfLinkImageHtml(string $image, string $alt): string
    {
        if ($image === '') {
            return '';
        }

        $thumb = JemImage::linkThumbnail($image, 120, 60, true);

        if ($thumb === '' || preg_match('#^(?:https?:)?//#i', $thumb)) {
            return '';
        }

        $path = JPATH_SITE . '/' . ltrim($thumb, '/\\');

        if (!is_file($path)) {
            return '';
        }

        return '<img class="jem-pdf-link-image" src="' . htmlspecialchars(str_replace('\\', '/', $path), ENT_COMPAT, 'UTF-8') . '" width="15mm" alt="' . htmlspecialchars($alt, ENT_COMPAT, 'UTF-8') . '" />';
    }

    /**
     * Builds an attachment download URL.
     */
    private function buildPdfAttachmentUrl($file): string
    {
        $id = (int) ($file->id ?? 0);

        if ($id < 1) {
            return '#';
        }

        return $this->buildPdfAbsoluteUrl(Route::_('index.php?option=com_jem&task=getfile&format=raw&file=' . $id . '&' . Session::getFormToken() . '=1', false));
    }

    /**
     * Returns a printable attachment size.
     */
    private function getPdfAttachmentSize($file): string
    {
        $path = '';

        if (!empty($file->id)) {
            try {
                $path = JemAttachment::getAttachmentPath((int) $file->id);
            } catch (Exception $e) {
                $path = '';
            }
        }

        if ($path === '' || !is_file($path)) {
            return '';
        }

        return $this->formatPdfBytes((int) filesize($path));
    }

    /**
     * Formats bytes for the PDF attachment list.
     */
    private function formatPdfBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Checks whether the event exposes registration.
     */
    private function eventAllowsRegistration($row): bool
    {
        return !empty($row->registra) && ((int) $row->registra & 3);
    }

    /**
     * Builds a compact registration block for the PDF.
     */
    private function buildPdfRegistrationHtml($row): string
    {
        $return = base64_encode(JemHelperRoute::getRoute($row->slug));
        $login = $this->buildPdfAbsoluteUrl(Route::_('index.php?option=com_users&view=login&return=' . $return, false));
        $params = (!empty($row->params) && is_object($row->params) && method_exists($row->params, 'get'))
            ? $row->params
            : JemHelper::globalattribs();
        $intro = $this->normalisePdfHtml((string) $params->get('registration_intro', ''));
        $footer = $this->normalisePdfHtml((string) $params->get('registration_footer', ''));

        $html = array();
        $html[] = '<h2>' . Text::_('COM_JEM_REGISTRATION') . '</h2>';
        if ($intro !== '') {
            $html[] = '<div class="jem-pdf-section">' . $intro . '</div>';
        }
        $html[] = '<div class="jem-pdf-section" style="text-align:center;">'
            . '<p>' . Text::_('COM_JEM_LOGIN_REQUIRED_FOR_REGISTER') . '</p>'
            . '<a class="jem-pdf-button" href="' . htmlspecialchars($login, ENT_COMPAT, 'UTF-8') . '">' . Text::_('COM_JEM_LOGIN') . '</a>'
            . '</div>';
        if ($footer !== '') {
            $html[] = '<div class="jem-pdf-section">' . $footer . '</div>';
        }

        return implode("\n", $html);
    }

    /**
     * Builds an absolute venue URL.
     */
    private function buildPdfVenueUrl($row): string
    {
        if (empty($row->locid)) {
            return '#';
        }

        $slug = $row->localias ? ((int) $row->locid . ':' . $row->localias) : (int) $row->locid;

        return $this->buildPdfAbsoluteUrl(Route::_(JemHelperRoute::getVenueRoute($slug), false));
    }

    /**
     * Converts a Joomla route to an absolute URL for PDF links.
     */
    private function buildPdfAbsoluteUrl(string $url): string
    {
        if ($url === '' || $url === '#') {
            return '#';
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
     * Builds a PDF contacts block.
     */
    private function buildPdfContactsHtml(array $contacts): string
    {
        $html = array();
        $html[] = '<h2>' . Text::_('COM_JEM_CONTACT_INFO') . '</h2>';
        $html[] = '<ul>';

        foreach ($contacts as $contact) {
            $name = trim((string) ($contact->conname ?? ''));

            if ($name === '') {
                continue;
            }

            $line = '<strong>' . htmlspecialchars($name, ENT_COMPAT, 'UTF-8') . '</strong>';
            $details = array_filter(array(
                trim((string) ($contact->conposition ?? '')),
                trim((string) ($contact->contelephone ?? '')),
                trim((string) ($contact->conmobile ?? '')),
                trim((string) ($contact->conemail ?? '')),
                trim((string) ($contact->conwebsite ?? '')),
            ));

            if ($details) {
                $line .= '<br />' . htmlspecialchars(implode(' | ', $details), ENT_COMPAT, 'UTF-8');
            }

            $html[] = '<li>' . $line . '</li>';
        }

        $html[] = '</ul>';

        return count($html) > 2 ? implode("\n", $html) : '';
    }
}
?>

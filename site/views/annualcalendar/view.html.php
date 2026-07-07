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

/**
 * Annual Calendar view.
 */
class JemViewAnnualcalendar extends JemView
{
    /**
     * Creates the Annual Calendar View.
     */
    public function display($tpl = null)
    {
        $app          = Factory::getApplication();
        $document     = $app->getDocument();
        $menu         = $app->getMenu();
        $menuitem     = $menu->getActive();
        $jemsettings  = JemHelper::config();
        $settings     = JemHelper::globalattribs();
        $user         = JemFactory::getUser();
        $params       = $app->getParams();
        $top_category = (int) $params->get('top_category', 0);
        $jinput       = $app->input;
        $print        = $jinput->getBool('print', false);
        $task         = $jinput->getCmd('task', '');
        $displayMode  = $jinput->getCmd('mode', (string) $params->get('annual_display_mode', 'calendar'));

        if (!in_array($displayMode, array('calendar', 'agenda'), true)) {
            $displayMode = 'calendar';
        }

        $this->param_topcat = $top_category > 0 ? ('&topcat=' . $top_category) : '';
        $url = Uri::root();

        JemHelper::loadCss('jem');
        JemHelper::loadCss('calendar');
        JemHelper::loadCustomCss();
        JemHelper::loadCustomTag();

        if ($print) {
            JemHelper::loadCss('print');
            $document->setMetaData('robots', 'noindex, nofollow');
        }

        $currentdaycolor = $params->get('currentdaycolor', '#CCCC99');
        $document->addStyleDeclaration('
        .jem-annual-calendar .jem-annual-day.is-today {
            background-color:' . $currentdaycolor . ';
        }');

        $calendarScript = JPATH_ROOT . '/media/com_jem/js/calendar.js';
        $document->addScript($url . 'media/com_jem/js/calendar.js' . (is_file($calendarScript) ? '?v=' . filemtime($calendarScript) : ''));

        $year       = (int) $jinput->getInt('yearID', date('Y'));
        $startMonth = max(1, min(12, (int) $params->get('annual_start_month', 1)));
        $periodStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));
        $periodEnd   = $periodStart->modify('+12 months -1 day');

        $model = $this->getModel();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $model->getState('params');
        $model->setState('filter.calendar_from', ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), ' . $db->quote($periodStart->format('Y-m-d')) . ') >= 0');
        $model->setState('filter.calendar_to', ' DATEDIFF(a.dates, ' . $db->quote($periodEnd->format('Y-m-d')) . ') <= 0');
        $model->setState('filter.date.from', $periodStart->format('Y-m-d'));
        $model->setState('filter.date.to', $periodEnd->format('Y-m-d'));
        $model->setState('filter.published', 1);
        $model->setState('filter.show_archived_events', (bool) $params->get('show_archived_events', 0));
        $model->setState('filter.calendar_multiday', true);
        $model->setState('filter.calendar_startdayonly', (bool) $params->get('show_only_start', false));
        $model->setState('filter.groupby', array('a.id'));
        $model->setState('list.start', 0);
        $model->setState('list.limit', 0);
        $rows  = $this->get('Items');

        $pagetitle = $params->def('page_title', $menuitem ? $menuitem->title : Text::_('COM_JEM_ANNUALCALENDAR_VIEW_DEFAULT_TITLE'));
        $params->def('page_heading', $pagetitle);
        $pageclass_sfx = $params->get('pageclass_sfx');

        $pathway = $app->getPathWay();
        if ($menuitem) {
            $pathwayKeys = array_keys($pathway->getPathway());
            $lastPathwayEntryIndex = end($pathwayKeys);
            $pathway->setItemName($lastPathwayEntryIndex, $menuitem->title);
        }

        $permissions = new stdClass();
        $catIds = $model->getCategories('all');
        $permissions->canAddEvent = $user->can('add', 'event', false, false, $catIds);
        $permissions->canAddVenue = $user->can('add', 'venue', false, false, $catIds);

        $itemid = $jinput->getInt('Itemid', 0);
        $partItemid = ($itemid > 0) ? '&Itemid=' . $itemid : '';
        $partDate = '&yearID=' . $year;
        $url_base = 'index.php?option=com_jem&view=annualcalendar';

        $partMode = $displayMode === 'agenda' ? '&mode=agenda' : '';

        $print_link = Route::_($url_base . $partItemid . $partDate . $partMode . ($task == 'archive' ? '&task=archive' : '') . '&print=1');
        $pdf_link = Route::_($url_base . $partItemid . $partDate . $partMode . ($task == 'archive' ? '&task=archive' : '') . '&format=raw&layout=pdf');
        $ical_link = $partDate;
        $archive_link = Route::_($url_base . $partItemid . $partDate . $partMode);

        if ($task == 'archive') {
            $pathway->addItem(Text::_('COM_JEM_ARCHIVE'), Route::_($url_base . $partItemid . $partDate . $partMode . '&task=archive'));
            $pagetitle .= ' - ' . Text::_('COM_JEM_ARCHIVE');
            $params->set('page_heading', $params->get('page_heading') . ' - ' . Text::_('COM_JEM_ARCHIVE'));
        }

        if ($app->get('sitename_pagetitles', 0) == 1) {
            $pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
        }

        $document->setTitle($pagetitle);
        $document->setMetaData('title', $pagetitle);

        $periodLabel = (int) $startMonth === 1
            ? (string) $year
            : $year . ' / ' . (int) $periodEnd->format('Y');

        $this->rows          = $rows;
        $this->catIds        = $catIds;
        $this->params        = $params;
        $this->jemsettings   = $jemsettings;
        $this->settings      = $settings;
        $this->permissions   = $permissions;
        $this->pageclass_sfx = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;
        $this->print_link    = $print_link;
        $this->pdf_link      = $pdf_link;
        $this->ical_link     = $ical_link;
        $this->print         = $print;
        $this->archive_link  = $archive_link;
        $this->task          = $task;
        $this->year          = $year;
        $this->itemid        = $itemid;
        $this->startMonth    = $startMonth;
        $this->periodStart   = $periodStart;
        $this->periodEnd     = $periodEnd;
        $this->periodLabel   = $periodLabel;
        $this->displayMode   = $displayMode;
        $this->calendar_link = Route::_($url_base . $partItemid . $partDate . ($task == 'archive' ? '&task=archive' : ''));
        $this->agenda_link   = Route::_($url_base . $partItemid . $partDate . '&mode=agenda' . ($task == 'archive' ? '&task=archive' : ''));
        $this->previous_link = Route::_($url_base . $partItemid . '&yearID=' . ($year - 1) . $partMode . ($task == 'archive' ? '&task=archive' : ''));
        $this->next_link     = Route::_($url_base . $partItemid . '&yearID=' . ($year + 1) . $partMode . ($task == 'archive' ? '&task=archive' : ''));

        parent::display($tpl);
    }
}
?>

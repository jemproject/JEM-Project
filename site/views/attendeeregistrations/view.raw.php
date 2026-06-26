<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Raw attendee registrations view.
 */
class JemViewAttendeeregistrations extends HtmlView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();

        if ($app->input->getCmd('layout', '') !== 'pdf') {
            $app->close();

            return;
        }

        $user = JemFactory::getUser();

        if (!$user->get('id')) {
            $uri = Uri::getInstance();
            $app->enqueueMessage(Text::_('COM_JEM_ATTENDEE_REGISTRATIONS_LOGIN_REQUIRED'), 'warning');
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($uri->toString()), false));

            return;
        }

        if (!$user->authorise('core.manage', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $params = $app->getParams();
        $columns = self::getColumns($params);
        $model = $this->getModel();
        $model->setState('limit', 0);
        $model->setState('limitstart', 0);
        $rows = (array) $model->getData();

        JemPdfView::renderTable(
            $params->get('page_title', Text::_('COM_JEM_ATTENDEE_REGISTRATIONS')),
            self::buildHeaders($columns),
            self::buildRows($rows, $columns),
            'jem-attendee-registrations.pdf',
            'list',
            self::buildFilterSummary()
        );
    }

    protected static function getColumns($params): array
    {
        $defaults = array('registration_id', 'name', 'username', 'places', 'uregdate', 'event', 'event_date', 'status');
        $selected = (array) $params->get('registration_columns', $defaults);
        $allowed = array('registration_id', 'name', 'username', 'user_id', 'email', 'places', 'uregdate', 'event', 'event_date', 'venue', 'status', 'comment');
        $selected = array_values(array_intersect($selected, $allowed));

        return $selected ?: $defaults;
    }

    protected static function buildHeaders(array $columns): array
    {
        $labels = self::columnLabels();

        return array_map(static fn ($column) => $labels[$column] ?? $column, $columns);
    }

    protected static function buildRows(array $rows, array $columns): array
    {
        $tableRows = array();

        foreach ($rows as $row) {
            $tableRow = array();

            foreach ($columns as $column) {
                $tableRow[] = self::columnValue($row, $column);
            }

            $tableRows[] = $tableRow;
        }

        return $tableRows;
    }

    protected static function columnLabels(): array
    {
        return array(
            'registration_id' => Text::_('COM_JEM_ATTENDEES_REGID'),
            'name' => Text::_('COM_JEM_NAME'),
            'username' => Text::_('COM_JEM_USERNAME'),
            'user_id' => Text::_('COM_JEM_USER_ID'),
            'email' => Text::_('COM_JEM_EMAIL'),
            'places' => Text::_('COM_JEM_TABLE_PLACES'),
            'uregdate' => Text::_('COM_JEM_ATTENDEE_REGISTRATION_DATE'),
            'event' => Text::_('COM_JEM_EVENT'),
            'event_date' => Text::_('COM_JEM_ATTENDEE_EVENT_DATE'),
            'venue' => Text::_('COM_JEM_VENUE'),
            'status' => Text::_('COM_JEM_STATUS'),
            'comment' => Text::_('COM_JEM_COMMENT'),
        );
    }

    protected static function columnValue($row, string $column)
    {
        switch ($column) {
            case 'registration_id':
                return (string) ($row->registration_id ?? '');
            case 'name':
                return (string) ($row->name ?? '');
            case 'username':
                return (string) ($row->username ?? '');
            case 'user_id':
                return (string) ($row->uid ?? '');
            case 'email':
                return (string) ($row->email ?? '');
            case 'places':
                return (string) ($row->places ?? '');
            case 'uregdate':
                return (string) ($row->uregdate ?? '');
            case 'event':
                $slug = !empty($row->event_alias) ? (int) $row->eventid . ':' . $row->event_alias : (int) ($row->eventid ?? 0);
                $title = htmlspecialchars((string) ($row->event_title ?? ''), ENT_COMPAT, 'UTF-8');

                return $slug ? array('html' => '<a href="' . self::absoluteUrl(Route::_(JemHelperRoute::getEventRoute($slug), false)) . '">' . $title . '</a>') : $title;
            case 'event_date':
                return JemOutput::formatShortDateTime($row->dates ?? '', $row->times ?? '', $row->enddates ?? '', $row->endtimes ?? '');
            case 'venue':
                $slug = !empty($row->venue_alias) ? (int) $row->venue_id . ':' . $row->venue_alias : (int) ($row->venue_id ?? 0);
                $venue = htmlspecialchars((string) ($row->venue ?? ''), ENT_COMPAT, 'UTF-8');

                return $slug ? array('html' => '<a href="' . self::absoluteUrl(Route::_(JemHelperRoute::getVenueRoute($slug), false)) . '">' . $venue . '</a>') : $venue;
            case 'status':
                return self::statusLabel($row);
            case 'comment':
                return (string) ($row->comment ?? '');
        }

        return '';
    }

    protected static function statusLabel($row): string
    {
        $status = (int) ($row->status ?? 0);

        if ($status === 1 && (int) ($row->waiting ?? 0) === 1) {
            return Text::_('COM_JEM_ATTENDEES_ON_WAITINGLIST');
        }

        switch ($status) {
            case 1:
                return Text::_('COM_JEM_ATTENDEES_ATTENDING');
            case 0:
                return Text::_('COM_JEM_ATTENDEES_INVITED');
            case -1:
                return Text::_('COM_JEM_ATTENDEES_NOT_ATTENDING');
        }

        return Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN');
    }

    protected static function absoluteUrl(string $url): string
    {
        if ($url === '' || preg_match('#^(?:https?:)?//#i', $url)) {
            return $url;
        }

        return rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
    }

    protected static function buildFilterSummary(): string
    {
        $app = Factory::getApplication();
        $filter = $app->input->getInt('filter', 0);
        $status = $app->input->getInt('filter_status', -2);
        $search = trim($app->input->getString('filter_search', ''));
        $order = $app->input->getCmd('filter_order', 'r.uregdate');
        $direction = strtoupper($app->input->getWord('filter_order_Dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $filterLabels = array(
            0 => Text::_('JALL'),
            1 => Text::_('COM_JEM_NAME'),
            2 => Text::_('COM_JEM_USERNAME'),
            3 => Text::_('COM_JEM_EVENT'),
        );
        $statusLabels = array(
            -2 => Text::_('COM_JEM_ATT_FILTER_ALL'),
            -1 => Text::_('COM_JEM_ATT_FILTER_NOT_ATTENDING'),
            0 => Text::_('COM_JEM_ATT_FILTER_INVITED'),
            1 => Text::_('COM_JEM_ATT_FILTER_ATTENDING'),
            2 => Text::_('COM_JEM_ATT_FILTER_WAITING'),
        );
        $orderLabels = array(
            'r.id' => Text::_('COM_JEM_ATTENDEES_REGID'),
            'u.name' => Text::_('COM_JEM_NAME'),
            'u.username' => Text::_('COM_JEM_USERNAME'),
            'r.uid' => Text::_('COM_JEM_USER_ID'),
            'r.places' => Text::_('COM_JEM_TABLE_PLACES'),
            'r.uregdate' => Text::_('COM_JEM_ATTENDEE_REGISTRATION_DATE'),
            'a.title' => Text::_('COM_JEM_EVENT'),
            'a.dates' => Text::_('COM_JEM_ATTENDEE_EVENT_DATE'),
            'r.status' => Text::_('COM_JEM_STATUS'),
            'v.venue' => Text::_('COM_JEM_VENUE'),
        );
        $parts = array(
            Text::_('COM_JEM_PDF_FILTER_STATUS') . ': ' . ($statusLabels[$status] ?? $statusLabels[-2]),
        );

        if ($search !== '') {
            $parts[] = Text::_('COM_JEM_SEARCH') . ': ' . $search . ' (' . ($filterLabels[$filter] ?? $filterLabels[0]) . ')';
        } else {
            $parts[] = Text::_('COM_JEM_SEARCH') . ': -';
        }

        $parts[] = Text::_('COM_JEM_PDF_FILTER_ORDER') . ': ' . ($orderLabels[$order] ?? $order) . ' ' . $direction;

        return Text::_('COM_JEM_PDF_FILTERS') . ': ' . implode('; ', $parts);
    }
}

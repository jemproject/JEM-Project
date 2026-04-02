<?php
/**
 * @package    JEM
 * @subpackage JEM Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_jem/models', 'JemModel');

/**
 * Module-Basic Helper
 */
abstract class ModJemHelper
{
    /**
     * Method to get the events
     *
     * @param   object  &$params  Module parameters
     * @return  array
     */
    public static function getList(&$params)
    {
        mb_internal_encoding('UTF-8');

        $db       = Factory::getContainer()->get('DatabaseDriver');
        $user     = JemFactory::getUser();
        $levels   = $user->getAuthorisedViewLevels();
        $settings = JemHelper::config();

        // Date format logic
        $dateFormat = $params->get('formatdate', '');
        if (empty($dateFormat)) {
            if (isset($settings->formatShortDate) && $settings->formatShortDate) {
                $dateFormat = $settings->formatShortDate;
            } else {
                $dateFormat = Text::_('COM_JEM_FORMAT_SHORT_DATE');
            }
        }

        $timeFormat = $params->get('formattime', '');
        $addSuffix  = empty($timeFormat);

        // Retrieve Eventslist model
        $model = BaseDatabaseModel::getInstance('Eventslist', 'JemModel', array('ignore_request' => true));
        $model->setState('params', $params);

        $type = (int) $params->get('type');

        // Archived events
        if ($type == 2) {
            $model->setState('filter.published', 2);
            $model->setState('filter.orderby', array('a.dates DESC', 'a.times DESC', 'a.created DESC'));
            $cal_from = "";
        } 
        // Upcoming or running events
        else {
            $model->setState('filter.published', 1);
            $model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC', 'a.created ASC'));

            $offset_minutes = 60 * (int) $params->get('offset_hours', 0);
            $cal_from = "((TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(a.dates,' ',IFNULL(a.times,'00:00:00'))) > $offset_minutes) ";
            $cal_from .= ($type == 1) ? " OR (TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(IFNULL(a.enddates,a.dates),' ',IFNULL(a.endtimes,'23:59:59'))) > $offset_minutes)) " : ") ";
        }

        $model->setState('filter.calendar_from', $cal_from);
        $model->setState('filter.groupby', 'a.id');

        // Filter categories
        $catids = JemHelper::getValidIds($params->get('catid'));
        if ($catids) {
            $model->setState('filter.category_id', $catids);
            $model->setState('filter.category_id.include', true);
        }

        // Filter venues
        $venids = JemHelper::getValidIds($params->get('venid'));
        if ($venids) {
            $model->setState('filter.venue_id', $venids);
            $model->setState('filter.venue_id.include', true);
        }

        $model->setState('list.limit', (int) $params->get('count', '2'));

        $events = $model->getItems();
        $lists  = array();

        if ($events) {
            $cutLimit = (int) $params->get('cuttitle', '18');

            foreach ($events as $row) {
                $item = new stdClass();

                // Process Title
                $title = $row->title ?? '';
                if (mb_strlen($title) > $cutLimit) {
                    $title = mb_substr($title, 0, $cutLimit) . '...';
                }

                $item->eventid     = $row->id;
                $item->title       = htmlspecialchars($title, ENT_COMPAT, 'UTF-8');
                $item->link        = Route::_(JemHelperRoute::getEventRoute($row->slug));
                $item->dates       = $row->dates;
                $item->times       = $row->times;
                $item->enddates    = $row->enddates;
                $item->endtimes    = $row->endtimes;
                $item->dateinfo    = JEMOutput::formatDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $dateFormat, $timeFormat, $addSuffix);
                $item->dateschema  = JEMOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, true);

                $item->venue       = htmlspecialchars($row->venue ?? '', ENT_COMPAT, 'UTF-8');
                $item->city        = htmlspecialchars($row->city ?? '', ENT_COMPAT, 'UTF-8');
                $item->postalCode  = htmlspecialchars($row->postalCode ?? '', ENT_COMPAT, 'UTF-8');
                $item->street      = htmlspecialchars($row->street ?? '', ENT_COMPAT, 'UTF-8');
                $item->state       = htmlspecialchars($row->state ?? '', ENT_COMPAT, 'UTF-8');
                $item->country     = htmlspecialchars($row->country ?? '', ENT_COMPAT, 'UTF-8');
                $item->venueurl    = !empty($row->venueslug) ? Route::_(JEMHelperRoute::getVenueRoute($row->venueslug)) : null;
                $item->featured    = $row->featured;
				$item->text        = $params->get('showtitle', 0) ? $item->title : ($params->get('showtitle', 0)? $item->venue : $item->dateinfo);

                // Provide custom fields safely
                for ($n = 1; $n <= 10; ++$n) {
                    $var = 'custom' . $n;
                    $item->$var = htmlspecialchars($row->$var ?? '', ENT_COMPAT, 'UTF-8');
                }

                $lists[] = $item;
            }
        }

        return $lists;
    }

    /**
     * Method to get a valid url
     *
     * @param   string  $url  URL to format
     * @return  string
     */
    protected static function _format_url($url)
    {
        if (!empty($url)) {
            $url = trim($url);
            if (stripos($url, 'http://') !== 0 && stripos($url, 'https://') !== 0) {
                $url = 'https://' . $url;
            }
        }
        return $url;
    }
}
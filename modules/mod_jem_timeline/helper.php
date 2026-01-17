<?php
/**
 * @package JEM
 * @subpackage JEM Timeline Module
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_jem/models', 'JemModel');

abstract class ModJemTimelineHelper
{
    public static function getList(&$params)
    {
        // Wir benutzen dieselbe Logik wie Banner, nur ohne Shuffle- und Limit-Logik für Timeline
        $model = BaseDatabaseModel::getInstance('Eventslist', 'JemModel', array('ignore_request' => true));
        $model->setState('params', $params);

        // basic filters
        $model->setState('filter.published', 1);
        $model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));

        $events = $model->getItems();
        $lists = array();

        foreach ($events as $i => $row) {
            $lists[$i] = new stdClass();
            $lists[$i]->title = $row->title;
            $lists[$i]->date  = $row->dates;
            $lists[$i]->time  = $row->times;
            $lists[$i]->venue = $row->venue;
            $lists[$i]->catname = implode(', ', JemOutput::getCategoryList($row->categories, ', ', false));
            $lists[$i]->link = Route::_(JemHelperRoute::getEventRoute($row->slug));
            $lists[$i]->eventimage = $row->datimage ? JemImage::flyercreator($row->datimage, 'event')['thumb'] : '';
        }

        return $lists;
    }
}

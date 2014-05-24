<?php
/**
 * @version 1.9.7
 * @package JEM
 * @subpackage JEM Module
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');

/**
 * Module-Basic
 */
abstract class modJEMHelper
{

	/**
	 * Method to get the events
	 *
	 * @access public
	 * @return array
	 */
	public static function getList(&$params)
	{
		mb_internal_encoding('UTF-8');
		
		$db		= JFactory::getDBO();
		$user	= JFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();
		
		# Retrieve Eventslist model for the data
		$model = JModelLegacy::getInstance('Eventslist', 'JemModel', array('ignore_request' => true));
		
		# Set params for the model
		# has to go before the getItems function
		$model->setState('params', $params);
		$model->setState('filter.access',true);
		
		# filter published
		#  0: unpublished
		#  1: published
		#  2: archived
		# -2: trashed
		
		# upcoming events
		if ($params->get('type')==0) {
			$model->setState('filter.published',1);
			$model->setState('filter.orderby',array('a.dates ASC','a.times ASC'));

			$cal_from = "(TIMEDIFF(CONCAT(a.dates,' ',IFNULL(a.times,'00:00:00')),NOW()) > 1 OR (a.enddates AND TIMEDIFF(CONCAT(a.enddates,' ',IFNULL(a.times,'00:00:00')),NOW())) > 1) ";
		}
		
		# archived events
		if ($params->get('type')==1) {
			$model->setState('filter.published',2);
			$model->setState('filter.orderby',array('a.dates DESC','a.times DESC'));
			$cal_from = "";
		}
		
		
		$model->setState('filter.calendar_from',$cal_from);
		$model->setState('filter.groupby','a.id');
		

		$catid 	= trim($params->get('catid'));
		$venid 	= trim($params->get('venid'));

		if ($catid) {
			$ids = explode(',', $catid);
			JArrayHelper::toInteger($ids);
			$categories = ' AND (c.id=' . implode(' OR c.id=', $ids) . ')';
		}
		if ($venid) {
			$ids = explode(',', $venid);
			JArrayHelper::toInteger($ids);
			$venues = ' AND (l.id=' . implode(' OR l.id=', $ids) . ')';
		}
		
		
		# count
		$count = $params->get('count', '2');
		
		$model->setState('list.limit',$count);
		
		# Retrieve the available Events
		$events = $model->getItems();
		
		# Loop through the result rows and prepare data
		$i		= 0;
		$lists	= array();
		
		foreach ($events as $row)
		{
			//cut titel
			$length = mb_strlen($row->title);

			if ($length > $params->get('cuttitle', '18')) {
				$row->title = mb_substr($row->title, 0, $params->get('cuttitle', '18'));
				$row->title = $row->title.'...';
			}

			$lists[$i] = new stdClass;
			$lists[$i]->link		= JRoute::_(JEMHelperRoute::getEventRoute($row->slug));
			$lists[$i]->dateinfo 	= JEMOutput::formatShortDateTime($row->dates, $row->times,
						$row->enddates, $row->endtimes);
			$lists[$i]->text		= $params->get('showtitloc', 0) ? $row->title : htmlspecialchars($row->venue, ENT_COMPAT, 'UTF-8');
			$lists[$i]->city		= htmlspecialchars($row->city, ENT_COMPAT, 'UTF-8');
			$lists[$i]->venueurl 	= !empty($row->venueslug) ? JRoute::_(JEMHelperRoute::getVenueRoute($row->venueslug)) : null;
			$i++;
		}
		
		

		return $lists;
	}

	/**
	 * Method to get a valid url
	 *
	 * @access public
	 * @return string
	 */
	protected static function _format_url($url)
	{
		if(!empty($url) && strtolower(substr($url, 0, 7)) != "http://") {
			$url = 'http://'.$url;
		}
		return $url;
	}
}
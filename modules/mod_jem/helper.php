<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @subpackage JEM Module
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;

JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');

/**
 * Module-Basic
 */
abstract class ModJemHelper
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

        $db       = Factory::getContainer()->get('DatabaseDriver');
		$user     = JemFactory::getUser();
		$levels   = $user->getAuthorisedViewLevels();
		$settings = JemHelper::config();

		// Use (short) format saved in module settings or in component settings or format in language file otherwise
		$dateFormat = $params->get('formatdate', '');
		if (empty($dateFormat)) {
			// on empty format long format will be used but we need short format
			if (isset($settings->formatShortDate) && $settings->formatShortDate) {
				$dateFormat = $settings->formatShortDate;
			} else {
				$dateFormat = Text::_('COM_JEM_FORMAT_SHORT_DATE');
			}
		}
		$timeFormat = $params->get('formattime', '');
		$addSuffix  = false;
		if (empty($timeFormat)) {
			// on empty format component's format will be used, so also use component's time suffix
			$addSuffix = true;
		}

		# Retrieve Eventslist model for the data
		$model = JModelLegacy::getInstance('Eventslist', 'JemModel', array('ignore_request' => true));

		# Set params for the model
		# has to go before the getItems function
		$model->setState('params', $params);

		# filter published
		#  0: unpublished
		#  1: published
		#  2: archived
		# -2: trashed

		$type = $params->get('type');

		# archived events
		if ($type == 2) {
			$model->setState('filter.published',2);
			$model->setState('filter.orderby',array('a.dates DESC', 'a.times DESC', 'a.created DESC'));
			$cal_from = "";
		}

		# upcoming or running events, on mistake default to upcoming events
		else {
			$model->setState('filter.published',1);
			$model->setState('filter.orderby',array('a.dates ASC', 'a.times ASC', 'a.created ASC'));

			$offset_minutes = 60 * $params->get('offset_hours', 0);

			$cal_from = "((TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(a.dates,' ',IFNULL(a.times,'00:00:00'))) > $offset_minutes) ";
			$cal_from .= ($type == 1) ? " OR (TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(IFNULL(a.enddates,a.dates),' ',IFNULL(a.endtimes,'23:59:59'))) > $offset_minutes)) " : ") ";
		}

		$model->setState('filter.calendar_from',$cal_from);
		$model->setState('filter.groupby','a.id');

		# filter category's
		$catids = JemHelper::getValidIds($params->get('catid'));
		if ($catids) {
			$model->setState('filter.category_id',$catids);
			$model->setState('filter.category_id.include',true);
		}

		# filter venue's
		$venids = JemHelper::getValidIds($params->get('venid'));
		if ($venids) {
			$model->setState('filter.venue_id',$venids);
			$model->setState('filter.venue_id.include',true);
		}

		# count
		$count = $params->get('count', '2');
		$model->setState('list.limit',$count);

		# Retrieve the available Events
		$events = $model->getItems();

		# Loop through the result rows and prepare data
		$i     = -1;
		$lists = array();

		foreach ($events as $row)
		{
			# cut titel
			$length = mb_strlen($row->title);

			if ($length > $params->get('cuttitle', '18')) {
				$row->title = mb_substr($row->title, 0, $params->get('cuttitle', '18'));
				$row->title = $row->title.'...';
			}

			$lists[++$i] = new stdClass;

			$lists[$i]->link     = Route::_(JemHelperRoute::getEventRoute($row->slug));
			$lists[$i]->dateinfo = JemOutput::formatDateTime($row->dates, $row->times, $row->enddates, $row->endtimes,
			                                                 $dateFormat, $timeFormat, $addSuffix);
			$lists[$i]->text     = $params->get('showtitloc', 0) ? $row->title : htmlspecialchars($row->venue, ENT_COMPAT, 'UTF-8');
			$lists[$i]->city     = htmlspecialchars($row->city ?? '', ENT_COMPAT, 'UTF-8');
            $lists[$i]->country  = htmlspecialchars($row->country ?? '', ENT_COMPAT, 'UTF-8');
			$lists[$i]->venueurl = !empty($row->venueslug) ? Route::_(JEMHelperRoute::getVenueRoute($row->venueslug)) : null;
			$lists[$i]->featured = $row->featured;
			
			# provide custom fields
			for ($n = 1; $n <= 10; ++$n) {
				$var = 'custom'.$n;
				$lists[$i]->$var = htmlspecialchars($row->$var, ENT_COMPAT, 'UTF-8');
			}
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
		if(!empty($url) && strtolower(substr($url, 0, 7)) != "https://") {
			$url = 'https://'.$url;
		}
		return $url;
	}
}
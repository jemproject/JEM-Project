<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


require_once dirname(__FILE__) . '/eventslist.php';
/**
 * Model-Venues
 */
class JemModelVenues extends JemModelEventslist
{

	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// parent::populateState($ordering, $direction);

		$app 			= JFactory::getApplication();
		$settings		= JemHelper::globalattribs();
		$jinput			= JFactory::getApplication()->input;
		$itemid 		= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);
		$params 		= $app->getParams();
		$task           = $jinput->get('task','','cmd');

		// List state information
		$limitstart = JRequest::getInt('limitstart');
		$this->setState('list.start', $limitstart);

		$limit		= JRequest::getInt('limit', $params->get('display_venues_num'));
		$this->setState('list.limit', $limit);

		# params
		$this->setState('params', $params);

		$this->setState('filter.published',1);

		$this->setState('filter.groupby',array('l.id','l.venue'));

	}

	/**
	 * Method to get a list of events.
	 */
	public function getItems()
	{
		$params = clone $this->getState('params');
		$items	= parent::getItems();

		$app = JFactory::getApplication();
		$params = $app->getParams('com_jem');

		// Lets load the content if it doesn't already exist
		if ($items) {

			foreach ($items as $item) {

				// Create image information
				$item->limage = JEMImage::flyercreator($item->locimage, 'venue');

				//Generate Venuedescription
				if (!$item->locdescription == '' || !$item->locdescription == '<br />') {
					//execute plugins
					$item->text	= $item->locdescription;
					$item->title 	= $item->venue;
					JPluginHelper::importPlugin('content');
					$app->triggerEvent('onContentPrepare', array('com_jem.venue', &$item, &$params, 0));
					$item->locdescription = $item->text;
				}

				//build the url
				if(!empty($item->url) && strtolower(substr($item->url, 0, 7)) != "http://") {
					$item->url = 'http://'.$item->url;
				}


				//prepare the url for output
				// TODO: Should be part of view! Then use $this->escape()
				if (strlen($item->url) > 35) {
					$item->urlclean = htmlspecialchars(substr($item->url, 0 , 35)).'...';
				} else {
					$item->urlclean = htmlspecialchars($item->url);
				}

				//create flag
				if ($item->country) {
					$item->countryimg = JemHelperCountries::getCountryFlag($item->country);
				}

				//create target link
				$task 	= JRequest::getVar('task', '', '', 'string');

				if ($task == 'archive') {
					$item->targetlink = JRoute::_(JEMHelperRoute::getVenueRoute($item->venueslug.'&task=archive'));
				} else {
					$item->targetlink = JRoute::_(JEMHelperRoute::getVenueRoute($item->venueslug));

				}

		}

			return $items;
		}

		return array();

	}


	/**
	 * @return	JDatabaseQuery
	 */
	function getListQuery()
	{
		// Create a new query object.
		$query	= parent::getListQuery();
		$jinput	= JFactory::getApplication()->input;
		$task	= $jinput->get('task','','cmd');
		$user = JFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();

		$query->select('CASE WHEN a.id IS NULL THEN 0 ELSE COUNT(a.id) END AS assignedevents');
		$query->where('l.published = 1');

		return $query;
	}
}
?>
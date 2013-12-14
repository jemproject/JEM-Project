<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the Venues View
 *
 * @package JEM
 * 
 */
class JEMViewVenues extends JViewLegacy
{
	/**
	 * Creates the Event Feed of the Venue
	 *
	 * 
	 */
	function display( )
	{
		$app =  JFactory::getApplication();

		$doc 	=  JFactory::getDocument();

		// Get some data from the model
		JRequest::setVar('limit', $app->getCfg('feed_limit'));
		$rows =  $this->get('Data');

		foreach ( $rows as $row )
		{
			// strip html from feed item title
			$title = $this->escape( $row->venue );
			$title = html_entity_decode( $title );

			// url link to article
			// used & instead of &amp; as this is converted by feed creator
			$link = JRoute::_(JEMHelperRoute::getVenueRoute($row->id), false);

			// strip html from feed item description text
			$description = $row->locdescription;
			@$created = ( $row->created ? date( 'r', strtotime($row->created) ) : '' );

			// load individual item creator class
			$item = new JFeedItem();
			$item->title 		= $title;
			$item->link 		= $link;
			$item->description 	= $description;
			$item->date			= $created;

			// loads item info into rss array
			$doc->addItem( $item );
		}
	}
}
?>
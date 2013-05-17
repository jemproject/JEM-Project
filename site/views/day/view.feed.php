<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

/**
 * HTML View class for the JEM View
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewDay extends JViewLegacy
{
	/**
	 * Creates the Event Feed
	 *
	 * @since 0.9
	 */
	function display()
	{
		$mainframe = JFactory::getApplication();

		$doc = JFactory::getDocument();
		$jemsettings = JEMHelper::config();

		// Get some data from the model
		JRequest::setVar('limit', $mainframe->getCfg('feed_limit'));
		$rows = $this->get('Data');

		foreach ($rows as $row)
		{
			// strip html from feed item title
			$title = $this->escape($row->title);
			$title = html_entity_decode($title);

			// strip html from feed item category
			/*
			$category = $this->escape($row->catname);
			$category = html_entity_decode($category);
			*/
			if (!empty($row->categories)) {
				$category = array();
				foreach ($row->categories AS $category2) {
					$category[] = $category2->catname;
				}

				// ading the , to the list when there are multiple category's
				$category = $this->escape(implode(', ', $category));
				$category = html_entity_decode($category);
			} else {
				$category = '';
			}

			//Format date and time
			$displaydate = JEMOutput::formatLongDateTime($row->dates, $row->times,
				$row->enddates, $row->endtimes);

			// url link to article
			// & used instead of &amp; as this is converted by feed creator
			$link = JRoute::_(JEMHelperRoute::getRoute($row->eventid));

			// feed item description text
			$description = JText::_('COM_JEM_TITLE').': '.$title.'<br />';
			$description .= JText::_('COM_JEM_VENUE').': '.$row->venue.' / '.$row->city.'<br />';
			$description .= JText::_('COM_JEM_CATEGORY').': '.$category.'<br />';
			$description .= JText::_('COM_JEM_DATE').': '.$displaydate.'<br />';
			$description .= JText::_('COM_JEM_DESCRIPTION').': '.$row->datdescription;

			@$created = ($row->created ? date('r', strtotime($row->created)) : '');

			// load individual item creator class
			$item = new JFeedItem();
			$item->title 		= $title;
			$item->link 		= $link;
			$item->description 	= $description;
			$item->date 		= $created;
			$item->category 	= $category;

			// loads item info into rss array
			$doc->addItem($item);
		}
	}
}
?>
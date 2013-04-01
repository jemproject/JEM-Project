<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * View class for the EventList home screen
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class EventListViewEventList extends JViewLegacy {

	function display($tpl = null)
	{
		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	=  JFactory::getDocument();
		$user 		=  JFactory::getUser();

		//build toolbar
		JToolBarHelper::title( JText::_( 'COM_EVENTLIST_EVENTLIST' ), 'home' );
		JToolBarHelper::help( 'el.intro', true );

		// Get data from the model
		$events      =  $this->get( 'Eventsdata');
		$venue       =  $this->get( 'Venuesdata');
		$category	 =  $this->get( 'Categoriesdata' );

		//add css and submenu to document
		$document->addStyleSheet('components/com_eventlist/assets/css/eventlistbackend.css');

		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_EVENTLIST' ), 'index.php?option=com_eventlist', true);
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_EVENTS' ), 'index.php?option=com_eventlist&view=events');
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_VENUES' ), 'index.php?option=com_eventlist&view=venues');
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_CATEGORIES' ), 'index.php?option=com_eventlist&view=categories');
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_ARCHIVESCREEN' ), 'index.php?option=com_eventlist&view=archive');
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_GROUPS' ), 'index.php?option=com_eventlist&view=groups');
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_HELP' ), 'index.php?option=com_eventlist&view=help');
		if ($user->get('gid') > 24) {
			JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_SETTINGS' ), 'index.php?option=com_eventlist&controller=settings&task=edit');
		}
        JToolBarhelper::preferences('com_eventlist');
		
		
		//assign vars to the template
		$this->assignRef('events'		, $events);
		$this->assignRef('venue'		, $venue);
		$this->assignRef('category'		, $category);
		$this->assignRef('user'			, $user);

		parent::display($tpl);

	}

	/**
	 * Creates the buttons view
	 *
	 * @param string $link targeturl
	 * @param string $image path to image
	 * @param string $text image description
	 * @param boolean $modal 1 for loading in modal
	 */
	function quickiconButton( $link, $image, $text, $modal = 0 )
	{
		//initialise variables
		$lang 		=  JFactory::getLanguage();
  		?>

		<div style="float:<?php echo ($lang->isRTL()) ? 'right' : 'left'; ?>;">
			<div class="icon">
				<?php
				if ($modal == 1) {
					JHTML::_('behavior.modal');
				?>
					<a href="<?php echo $link.'&amp;tmpl=component'; ?>" style="cursor:pointer" class="modal" rel="{handler: 'iframe', size: {x: 650, y: 400}}">
				<?php
				} else {
				?>
					<a href="<?php echo $link; ?>">
				<?php
				}

					echo JHTML::_('image', 'administrator/components/com_eventlist/assets/images/'.$image, $text );
				?>
					<span><?php echo $text; ?></span>
				</a>
			</div>
		</div>
		<?php
	}
}
?>
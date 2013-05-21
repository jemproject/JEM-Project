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

defined( '_JEXEC' ) or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Archive Controller
 *
 * @package JEM
 * @since 0.9
 */
class JEMControllerArchive extends JEMController
{
	/**
	 * Constructor
	 *
	 *@since 0.9
	 */
	function __construct()
	{
		parent::__construct();
		
		$this->registerTask( 'copy',	 	'edit' );
	}

	
	/**
	 * logic to create the edit event screen
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function edit( )
	{
		JRequest::setVar( 'view', 'event' );
		JRequest::setVar( 'hidemainmenu', 1 );
	
		$model 	= $this->getModel('event');
		$task 	= JRequest::getVar('task');
	
		if ($task == 'copy') {
			JRequest::setVar( 'task', $task );
		} else {
	
			$user	= JFactory::getUser();
			// Error if checkedout by another administrator
			if ($model->isCheckedOut( $user->get('id') )) {
				$this->setRedirect( 'index.php?option=com_jem&view=events', JText::_( 'COM_JEM_EDITED_BY_ANOTHER_ADMIN' ) );
			}
			$model->checkout();
		}
		parent::display();
	}
	
	
	
	
	
	
	/**
	 * unarchives an Event
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function unarchive()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_UNARCHIVE' ) );
		}

		$model = $this->getModel('archive');

		if(!$model->publish($cid, 0)) {
			echo "<script> alert('".$model->getError(true)."'); window.history.go(-1); </script>\n";
		}

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENTS_UNARCHIVED');

		$this->setRedirect( 'index.php?option=com_jem&view=archive', $msg );
	}

	/**
	 * removes an Event
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function remove()
	{
		$cid = JRequest::getVar( 'cid', array(0), 'post', 'array' );

		$total = count( $cid );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_( 'COM_JEM_SELECT_ITEM_TO_DELETE' ) );
		}

		$model = $this->getModel('archive');
		if(!$model->delete($cid)) {
			echo "<script> alert('".$model->getError(true)."'); window.history.go(-1); </script>\n";
		}

		$msg = $total.' '.JText::_( 'COM_JEM_EVENTS_DELETED');

		$this->setRedirect( 'index.php?option=com_jem&view=archive', $msg );
	}
}
?>
<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
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
 * JEM Component Venues Controller
 *
 * @package JEM
 * @since 0.9
 */
class JEMControllerVenues extends JEMController
{
	/**
	 * Constructor
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'add', 		'edit' );
		$this->registerTask( 'apply', 		'save' );
	}

	/**
	 * Logic to publish venues
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function publish()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_( 'COM_JEM_SELECT_AN_ITEM_TO_PUBLISH' ) );
		}

		$model = $this->getModel('venues');
		if(!$model->publish($cid, 1)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_VENUE_PUBLISHED');

		$this->setRedirect( 'index.php?option=com_jem&view=venues', $msg );
	}

	/**
	 * Logic to unpublish venues
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function unpublish()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_( 'COM_JEM_SELECT_AN_ITEM_TO_UNPUBLISH' ) );
		}

		$model = $this->getModel('venues');
		if(!$model->publish($cid, 0)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_VENUE_UNPUBLISHED');

		$this->setRedirect( 'index.php?option=com_jem&view=venues', $msg );
	}

	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function cancel()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );
		
		$session 	=  JFactory::getSession();
		
		$session->clear('venueform', 'com_jem');
		
		$venue = JTable::getInstance('jem_venues', '');
		$venue->bind(JRequest::get('post'));
		$venue->checkin();

		$this->setRedirect( 'index.php?option=com_jem&view=venues' );
	}

	/**
	 * logic for remove venues
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function remove()
	{
		$cid = JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_( 'COM_JEM_SELECT_AN_ITEM_TO_DELETE' ) );
		}

		$model = $this->getModel('venues');

		$msg = $model->delete($cid);

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		$this->setRedirect( 'index.php?option=com_jem&view=venues', $msg );
	}

	/**
	 * logic to orderup a venue
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function orderup()
	{
		$model = $this->getModel('venues');
		$model->move(-1);

		$this->setRedirect( 'index.php?option=com_jem&view=venues');
	}

	/**
	 * logic to orderdown a venue
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function orderdown()
	{
		$model = $this->getModel('venues');
		$model->move(1);

		$this->setRedirect( 'index.php?option=com_jem&view=venues');
	}

	/**
	 * logic to create the edit venue view
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function edit( )
	{
		JRequest::setVar( 'view', 'venue' );
		JRequest::setVar( 'hidemainmenu', 1 );

		$model 	= $this->getModel('venue');
		$user	= JFactory::getUser();

		// Error if checkedout by another administrator
		if ($model->isCheckedOut( $user->get('id') )) {
			$this->setRedirect( 'index.php?option=com_jem&view=venues', JText::_( 'COM_JEM_EDITED_BY_ANOTHER_ADMIN' ) );
		}

		$model->checkout();
		
		parent::display();
	}

	/**
	 * saves the venue in the database
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );
		
		$task		= JRequest::getVar('task');

		// Sanitize
		$post = JRequest::get( 'post' );
		$post['locdescription'] = JRequest::getVar( 'locdescription', '', 'post', 'string', JREQUEST_ALLOWRAW );
		$post['locdescription']	= str_replace( '<br>', '<br />', $post['locdescription'] );
		
		if (JRequest::getVar( 'latitude', '', 'post', 'string') == '') {
			$post['latitude'] = null;
		}
		
    	if (JRequest::getVar( 'longitude', '', 'post', 'string') == '') {
      		unset($post['longitude']);
    	}

    	//sticky forms
		$session = JFactory::getSession();
		$session->set('venueform', $post, 'com_jem');

		$model = $this->getModel('venue');

		if ($returnid = $model->store($post)) {

			switch ($task)
			{
				case 'apply':
					$link = 'index.php?option=com_jem&view=venue&hidemainmenu=1&cid[]='.$returnid;
					break;

				default:
					$link = 'index.php?option=com_jem&view=venues';
					break;
			}
			$msg	= JText::_( 'COM_JEM_VENUE_SAVED');

			$cache = JFactory::getCache('com_jem');
			$cache->clean();
			
			$session->clear('venueform', 'com_jem');

		} else {

			$msg 	= '';
			$link 	= 'index.php?option=com_jem&view=venue';

		}

		$model->checkin();

		$this->setRedirect( $link, $msg );
	}

	/**
	 * saves the venue in the database
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function addvenue()
	{
		// Sanitize
		$post = JRequest::get( 'post' );
		$post['locdescription'] = JRequest::getVar( 'locdescription', '', 'post', 'string', JREQUEST_ALLOWRAW );


		$model = $this->getModel('venue');
		$model->store($post);
		$model->checkin();

		$msg	= JText::_( 'COM_JEM_VENUE_SAVED');
		$link 	= 'index.php?option=com_jem&view=event&layout=addvenue&tmpl=component';

		$this->setRedirect( $link, $msg );
	}
}
?>
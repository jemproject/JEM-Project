<?php
/**
 * @version 1.1 $Id$
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

 if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined( '_JEXEC' ) or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Controller
 *
 * @package JEM
 * @since 0.9
 */
class JEMController extends JControllerLegacy
{
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'applycss', 	'savecss' );
	}

	/**
	 * Display the view
	 */
	function display($cachable = false, $urlparams = false)

	{
		parent::display();

	}

	/**
	 * Saves the css
	 *
	 */
	function savecss()
	{
		$app = JFactory::getApplication();
		
		JRequest::checkToken() or die( 'Invalid Token' );

		// Initialize some variables
		$filename		= JRequest::getVar('filename', '', 'post', 'cmd');
		$filecontent	= JRequest::getVar('filecontent', '', '', '', JREQUEST_ALLOWRAW);

		if (!$filecontent) {
			$app->redirect('index.php?option=com_jem', JText::_('OPERATION FAILED').': '.JText::_('CONTENT EMPTY'));
		}	
		
		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');
		$ftp = JClientHelper::getCredentials('ftp');
		
		$file = JPATH_SITE.DS.'components'.DS.'com_jem'.DS.'assets'.DS.'css'.DS.$filename;
		
		// Try to make the css file writeable
		if (!$ftp['enabled'] && JPath::isOwner($file) && !JPath::setPermissions($file, '0755')) {
			JError::raiseNotice('SOME_ERROR_CODE', 'COULD NOT MAKE CSS FILE WRITABLE');
		}

		jimport('joomla.filesystem.file');
		$return = JFile::write($file, $filecontent);

		// Try to make the css file unwriteable
		if (!$ftp['enabled'] && JPath::isOwner($file) && !JPath::setPermissions($file, '0555')) {
			JError::raiseNotice('SOME_ERROR_CODE', 'COULD NOT MAKE CSS FILE UNWRITABLE');
		}
		
		if ($return)
		{
			$task = JRequest::getVar('task');
			switch($task)
			{
				case 'applycss' :
					$app->redirect('index.php?option=com_jem&view=editcss', JText::_('CSS FILE SUCCESSFULLY ALTERED'));
					break;

				case 'savecss'  :
				default         :
					$app->redirect('index.php?option=com_jem', JText::_('CSS FILE SUCCESSFULLY ALTERED') );
					break;
			}
		} else {
			$app->redirect('index.php?option=com_jem', JText::_('OPERATION FAILED').': '.JText::sprintf('FAILED TO OPEN FILE FOR WRITING', $file));
		}
	}

	/**
	 * displays the fast addvenue screen
	 *
	 * @since 0.9
	 */
	function addvenue( )
	{
		JRequest::setVar( 'view', 'event' );
		JRequest::setVar( 'layout', 'addvenue'  );

		parent::display();
	}
	
	
	function clearrecurrences()
	{
		$model = $this->getModel('events');
		$model->clearrecurrences();
		$this->setRedirect( 'index,php?option=com_jem', Jtext::_('Recurrences cleared') );
	}

	/**
	 * Delete attachment
	 *
	 * @return true on sucess
	 * @access private
	 * @since 1.1
	 */
	function ajaxattachremove()
	{
		$id     = JRequest::getVar( 'id', 0, 'request', 'int' );

		$res = JEMAttachment::remove($id);
		if (!$res) {
			echo 0;
			exit();
		}

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		echo 1;
		exit();
	}
}
?>
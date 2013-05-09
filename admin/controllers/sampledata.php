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
 * JEM Component Sampledata Controller
 *
 * @package JEM
 * @since 0.9
 */
class JEMControllerSampledata extends JEMController
{
	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();
	}
 	
 	/**
	 * Process sampledata
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function load()
	{
		//get model
		$model 	= $this->getModel('sampledata');
		if (!$model->loaddata()) {
			$msg 	= JText::_( 'SAMPLEDATA FAILED' );
		} else {
			$msg 	= JText::_( 'SAMPLEDATA SUCCESSFULL' );
		}
		
		$link 	= 'index.php?option=com_jem&view=jem';
		
		$this->setRedirect($link, $msg);
 	}
}
?>
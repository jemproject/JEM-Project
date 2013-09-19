<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Attendees Controller
 *
 * @package JEM
 * 
 */
class JEMControllerAttendees extends JControllerLegacy
{
	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

	}


	/**
	 * redirect to events page
	 */
  function back()
  {
  	$fid = JRequest::getInt('Itemid');
  	$link = 'index.php?option=com_jem&view=myevents&Itemid='.$fid;

    $this->setRedirect( $link );
    $this->redirect();
  }


}
?>
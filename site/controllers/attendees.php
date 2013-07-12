<?php
/**
 * @version 1.9
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
 * @since 0.9
 */
class JEMControllerAttendees extends JEMController
{
	/**
	 * Constructor
	 *
	 *@since 0.9
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
  	$link = 'index.php?option=com_jem&view=my&Itemid='.$fid;
  	
    $this->setRedirect( $link );
    $this->redirect();
  }

  
}
?>
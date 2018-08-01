<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Myvenues Controller
 *
 * @package JEM
 *
 */
class JemControllerMyvenues extends JControllerLegacy
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Logic to publish venues
	 *
	 * @access public
	 * @return void
	 */
	public function publish()
	{
		$this->setStatus(1, 'COM_JEM_VENUE_PUBLISHED');
	}

	/**
	 * Logic unpublish venues
	 */
	public function unpublish()
	{
		$this->setStatus(0, 'COM_JEM_VENUE_UNPUBLISHED');
	}

	/**
	 * Logic to trash venues - NOT SUPPORTED YET
	 *
	 * @access public
	 * @return void
	 */
	/*
	public function trash()
	{
		$this->setStatus(-2, 'COM_JEM_VENUE_TRASHED');
	}
	*/

	/**
	 * Logic to publish/unpublish/trash venues
	 *
	 * @access protected
	 * @return void
	 */
	protected function setStatus($status, $message)
	{
		// Check for request forgeries
		JSession::checkToken() or jexit('Invalid Token');

		$app = JFactory::getApplication();
		$input = $app->input;

		$cid = $input->get('cid', array(), 'array');

		if (empty($cid)) {
			JError::raiseNotice(100, JText::_('COM_JEM_SELECT_ITEM_TO_PUBLISH'));
			$this->setRedirect(JemHelperRoute::getMyVenuesRoute());
			return;
		}

		$model = $this->getModel('myvenues');
		if (!$model->publish($cid, $status)) {
			echo "<script> alert('" . $model->getError() . "'); window.history.go(-1); </script>\n";
		}

		$total = count($cid);
		$msg   = $total . ' ' . JText::_($message);

		$this->setRedirect(JemHelperRoute::getMyVenuesRoute(), $msg);
	}
}
?>
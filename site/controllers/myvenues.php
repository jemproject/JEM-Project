<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;

/**
 * JEM Component Myvenues Controller
 *
 * @package JEM
 *
 */
class JemControllerMyvenues extends BaseController
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

		$app = Factory::getApplication();
		$input = $app->input;

		$cid = $input->get('cid', array(), 'array');

		if (empty($cid)) {
			Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_SELECT_ITEM_TO_PUBLISH'), 'notice');
			$this->setRedirect(JemHelperRoute::getMyVenuesRoute());
			return;
		}

		$model = $this->getModel('myvenues');
		if (!$model->publish($cid, $status)) {
			echo "<script> alert('" . $model->getError() . "'); window.history.go(-1); </script>\n";
		}

		$total = count($cid);
		$msg   = $total . ' ' . Text::_($message);

		$this->setRedirect(JemHelperRoute::getMyVenuesRoute(), $msg);
	}
}
?>

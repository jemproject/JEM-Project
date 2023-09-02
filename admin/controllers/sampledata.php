<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;

/**
 * JEM Component Sampledata Controller
 * @package JEM
 */
class JemControllerSampledata extends BaseController
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Process sampledata
	 */
	public function load()
	{
		$model = $this->getModel('sampledata');

		if (!$model->loadData()) {
			$msg = Text::_('COM_JEM_SAMPLEDATA_FAILED');
		} else {
			$msg = Text::_('COM_JEM_SAMPLEDATA_SUCCESSFULL');
		}

		$link = 'index.php?option=com_jem&view=main';

		$this->setRedirect($link, $msg);
 	}
}
?>

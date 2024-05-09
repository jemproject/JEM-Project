<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * Based on: https://gist.github.com/dongilbert/4195504
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Session\Session;

/**
 * JEM Component Export Controller
 *
 */
class JemControllerExport extends AdminController
{
	/**
	* Proxy for getModel.
	*
	*/
	public function getModel($name = 'Export', $prefix = 'JemModel', $config=array()) {
		$model = parent::getModel($name, $prefix, array('ignore_request' => true));
		return $model;
	}

	public function export()
	{
		// Check for request forgeries
		Session::checkToken() or jexit('Invalid Token');

		$this->sendHeaders("jem_export-" . date('Ymd-His') . ".csv", "text/csv");
		$this->getModel()->getCsv();
		jexit();
	}

	public function exportcats()
	{
		// Check for request forgeries
		Session::checkToken() or jexit('Invalid Token');

		$this->sendHeaders("categories.csv", "text/csv");
		$this->getModel()->getCsvcats();
		jexit();
	}

	public function exportvenues()
	{
		// Check for request forgeries
		Session::checkToken() or jexit('Invalid Token');

		$this->sendHeaders("venues.csv", "text/csv");
		$this->getModel()->getCsvvenues();
		jexit();
	}

	public function exportcatevents()
	{
		// Check for request forgeries
		Session::checkToken() or jexit('Invalid Token');

		$this->sendHeaders("catevents.csv", "text/csv");
		$this->getModel()->getCsvcatsevents();
		jexit();
	}

	private function sendHeaders($filename = 'export.csv', $contentType = 'text/plain')
	{
		// TODO: Use UTF-8
		// We have to fix the model->getCsv* methods too!
		// header("Content-type: text/csv; charset=UTF-8");
		header("Content-type: text/csv;");
		header("Content-Disposition: attachment; filename=" . $filename);
		header("Pragma: no-cache");
		header("Expires: 0");
	}
}

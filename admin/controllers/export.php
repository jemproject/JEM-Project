<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Based on: https://gist.github.com/dongilbert/4195504
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controlleradmin');


/**
 * JEM Component Export Controller
 *
 */
class JEMControllerExport extends JControllerAdmin {
	/**
	* Proxy for getModel.
	*
	*/
	public function getModel($name = 'Export', $prefix = 'JEMModel', $config=array()) {
		$model = parent::getModel($name, $prefix, array('ignore_request' => true));
		return $model;
	}

	public function export() {
		$this->sendHeaders("events.csv", "text/csv");
		$this->getModel()->getCsv();
		jexit();
	}

	public function exportcats() {
		$this->sendHeaders("categories.csv", "text/csv");
		$this->getModel()->getCsvcats();
		jexit();
	}

	public function exportvenues() {
		$this->sendHeaders("venues.csv", "text/csv");
		$this->getModel()->getCsvvenues();
		jexit();
	}

	public function exportcatevents() {
		$this->sendHeaders("catevents.csv", "text/csv");
		$this->getModel()->getCsvcatsevents();
		jexit();
	}

	private function sendHeaders($filename = 'export.csv', $contentType = 'text/plain') {
		// TODO: Use UTF-8
		// We have to fix the model->getCsv* methods too!
		// header("Content-type: text/csv; charset=UTF-8");
		header("Content-type: text/csv;");
		header("Content-Disposition: attachment; filename=" . $filename);
		header("Pragma: no-cache");
		header("Expires: 0");
	}
}

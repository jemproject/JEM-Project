<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
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
 * 
 * ***
 * Based on: https://gist.github.com/dongilbert/4195504
 */


// No direct access.
defined('_JEXEC') or die;

jimport('joomla.application.component.controlleradmin');

class JEMControllerExport extends JControllerAdmin {
	/**
	* Proxy for getModel.
	* @since 1.6
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

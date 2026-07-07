<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * Based on: https://gist.github.com/dongilbert/4195504
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * JEM Component Export Controller
 *
 */
class JemControllerExport extends AdminController
{
    /**
     * Check whether the current user can export JEM data.
     *
     * @return void
     */
    private function assertCanExport()
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_jem')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
    * Proxy for getModel.
    *
    */
    public function getModel($name = 'Export', $prefix = 'JemModel', $config=array()) {
        $model = parent::getModel($name, $prefix, array('ignore_request' => true));
        return $model;
    }

    public function export() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportEvents')), "text/csv");
        $this->getModel()->getCsv();
        jexit();
    }

    public function exportcats() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportCategories')), "text/csv");
        $this->getModel()->getCsvcats();
        jexit();
    }

    public function exportvenues() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportVenues')), "text/csv");
        $this->getModel()->getCsvvenues();
        jexit();
    }

    public function exportcatevents() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportCatEvents')), "text/csv");
        $this->getModel()->getCsvcatsevents();
        jexit();
    }

    public function exportattachments() {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportAttachments')), "text/csv");
        $this->getModel()->getCsvattachments();
        jexit();
    }

    public function exporttypes() {
        Session::checkToken() or jexit('Invalid Token');
        $this->assertCanExport();

        $this->sendHeaders($this->getRequestedFilename($this->buildExportFilename('JEM-exportTypes')), "text/csv");
        $this->getModel()->getCsvtypes();
        jexit();
    }

    private function getRequestedFilename($default = 'export.csv') {
        $filename = Factory::getApplication()->input->post->getString('export_filename', $default);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($filename));

        if ($filename === '' || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'csv') {
            return $default;
        }

        return $filename;
    }

    private function buildExportFilename($name) {
        return $name . '-' . date('Ymd-His') . '.csv';
    }

    private function sendHeaders($filename = 'export.csv', $contentType = 'text/plain') {
        // TODO: Use UTF-8
        // We have to fix the model->getCsv* methods too!
        // header("Content-type: text/csv; charset=UTF-8");
        header("Content-type: text/csv;");
        header("Content-Disposition: attachment; filename=\"" . basename($filename) . "\"");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
}

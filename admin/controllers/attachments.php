<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

/**
 * JEM Component Attachments Controller
 */
class JemControllerAttachments extends AdminController
{
    protected $text_prefix = 'COM_JEM_ATTACHMENTS';

    public function getModel($name = 'Attachment', $prefix = 'JemModel', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function deleteFiles()
    {
        Session::checkToken() or jexit(Text::_('COM_JEM_GLOBAL_INVALID_TOKEN'));

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.delete', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $cid = $app->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);
        $cid = array_filter($cid);

        if (empty($cid)) {
            throw new \Exception(Text::_('COM_JEM_SELECT_AN_ITEM_TO_DELETE'), 500);
        }

        $model = $this->getModel();

        if ($model->deleteWithFiles($cid)) {
            $app->enqueueMessage(Text::plural('COM_JEM_ATTACHMENTS_N_ITEMS_DELETED_WITH_FILES', count($cid)));
        } else {
            $app->enqueueMessage($model->getError(), 'error');
        }

        $this->setRedirect('index.php?option=com_jem&view=attachments');
    }

    public function download()
    {
        Session::checkToken('request') or jexit(Text::_('COM_JEM_GLOBAL_INVALID_TOKEN'));

        if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $id = Factory::getApplication()->input->getInt('id', 0);
        $model = $this->getModel();
        $path = $model->getAttachmentPath($id);

        if (!$path || !is_file($path)) {
            JemAttachment::logDownloadError($id, 'backend', 'File not found');
            throw new \Exception(Text::_('JGLOBAL_RESOURCE_NOT_FOUND'), 404);
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        while (ob_get_level()) {
            ob_end_clean();
        }
        $delivered = readfile($path);

        if ($delivered !== false) {
            JemAttachment::recordDownload($id);
        } else {
            JemAttachment::logDownloadError($id, 'backend', 'File delivery failed');
        }

        Factory::getApplication()->close();
    }

    public function export()
    {
        Session::checkToken() or jexit(Text::_('COM_JEM_GLOBAL_INVALID_TOKEN'));

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.manage', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $cid = $app->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);
        $cid = array_values(array_filter($cid));

        if (empty($cid)) {
            $app->enqueueMessage(Text::_('COM_JEM_ATTACHMENTS_SELECT_ITEM_TO_EXPORT'), 'warning');
            $this->setRedirect('index.php?option=com_jem&view=attachments');
            return;
        }

        $model = $this->getModel('Attachments');
        $filename = 'exportAttachments-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv;');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $model->exportCsv($cid);
        $app->close();
    }
}

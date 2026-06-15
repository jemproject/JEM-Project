<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * JEM Component Cssmanager Controller
 */
class JemControllerCssmanager extends AdminController
{

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        // Register Extra task
        $this->registerTask('setlinenumber',         'linenumber');
        $this->registerTask('disablelinenumber',     'linenumber');
        $this->registerTask('copycustom',            'copycustom');
        $this->registerTask('deletecustom',          'deletecustom');
    }


    /**
     * Proxy for getModel.
     */
    public function getModel($name = 'Cssmanager', $prefix = 'JemModel', $config = array()) {
        $model = parent::getModel($name, $prefix, array('ignore_request' => true));
        return $model;
    }

    /**
     *
     */
    public function cancel() {
        $this->setRedirect('index.php?option=com_jem&view=main');
    }

    public function back() {
        $this->setRedirect('index.php?option=com_jem&view=main');
    }
    /**
     *
     */
    public function linenumber() {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.manage', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $task  = $app->input->getCmd('task', '');
        $model = $this->getModel();

        switch ($task) {
            case 'setlinenumber' :
                $model->setStatusLinenumber(1);
                break;

            default :
                $model->setStatusLinenumber(0);
                break;
        }

        $this->setRedirect('index.php?option=com_jem&view=cssmanager');
    }

    public function copycustom()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.edit', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $file = $app->input->getString('file', '');
        $targetFile = $app->input->getString('customfile', '');
        $model = $this->getModel();

        if (!$model->copyCustomFile($file, $targetFile)) {
            $app->enqueueMessage($model->getError(), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jem&view=cssmanager', false));
            return false;
        }

        $targetFile = $targetFile !== '' ? $targetFile : $file;
        $app->enqueueMessage(Text::sprintf('COM_JEM_CSSMANAGER_CUSTOM_FILE_CREATED', $targetFile), 'message');
        $this->setRedirect(Route::_('index.php?option=com_jem&task=source.edit&id=' . base64_encode('custom#:' . $targetFile), false));

        return true;
    }

    public function deletecustom()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.delete', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $file = $app->input->getString('file', '');
        $model = $this->getModel();

        if (!$model->deleteCustomFile($file)) {
            $app->enqueueMessage($model->getError(), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jem&view=cssmanager', false));
            return false;
        }

        $app->enqueueMessage(Text::sprintf('COM_JEM_CSSMANAGER_CUSTOM_FILE_DELETED', $file), 'message');
        $this->setRedirect(Route::_('index.php?option=com_jem&view=cssmanager', false));

        return true;
    }

}

<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

require_once JPATH_COMPONENT_SITE . '/classes/controller.form.class.php';

class JemControllerSpecialday extends JemControllerForm
{
    protected $view_item = 'specialday';
    protected $view_list = 'specialdays';

    protected function allowAdd($data = array())
    {
        return JemFactory::getUser()->authorise('core.manage', 'com_jem');
    }

    protected function allowEdit($data = array(), $key = 'id')
    {
        return JemFactory::getUser()->authorise('core.manage', 'com_jem');
    }

    public function add()
    {
        if (!JemFactory::getUser()->authorise('core.manage', 'com_jem')) {
            $this->setRedirect(Route::_('index.php?option=com_jem&view=specialdays', false));
            return false;
        }

        return parent::add();
    }

    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    public function cancel($key = 'id')
    {
        Session::checkToken() or jexit('Invalid Token');

        parent::cancel($key);
        $this->setRedirect($this->getReturnPage());
    }

    public function getModel($name = 'specialday', $prefix = '', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }

    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'id')
    {
        $append = '&layout=edit';

        if ($recordId) {
            $append .= '&' . $urlVar . '=' . (int) $recordId;
        }

        $return = $this->getReturnPage();

        if ($return) {
            $append .= '&return=' . base64_encode($return);
        }

        return $append;
    }

    protected function getRedirectToListAppend()
    {
        $itemId = Factory::getApplication()->input->getInt('Itemid', 0);

        return $itemId ? '&Itemid=' . $itemId : '';
    }

    protected function getReturnPage()
    {
        $return = Factory::getApplication()->input->get('return', '', 'base64');
        $decodedReturn = $return ? base64_decode($return, true) : false;

        if ($decodedReturn && Uri::isInternal($decodedReturn)) {
            return $decodedReturn;
        }

        return Route::_('index.php?option=com_jem&view=specialdays', false);
    }
}

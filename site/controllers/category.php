<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

require_once JPATH_COMPONENT_SITE . '/classes/controller.form.class.php';

/**
 * Frontend JEM category form controller.
 */
class JemControllerCategory extends JemControllerForm
{
    protected $view_item = 'editcategory';
    protected $view_list = 'categories';

    public function add()
    {
        if (!$this->requireFrontendUser()) {
            return false;
        }

        if (!JemFrontendCategoryAccess::canCreate(JemFactory::getUser(), $this->input->getInt('parent_id', 1))) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return parent::add();
    }

    protected function allowAdd($data = array())
    {
        return JemFrontendCategoryAccess::canCreate(
            JemFactory::getUser(),
            (int) ($data['parent_id'] ?? $this->input->getInt('parent_id', 1))
        );
    }

    protected function allowEdit($data = array(), $key = 'id')
    {
        $recordId = (int) ($data[$key] ?? 0);

        if ($recordId < 1) {
            return false;
        }

        $item = $this->getModel()->getItem($recordId);

        return JemFrontendCategoryAccess::canEdit(JemFactory::getUser(), $item);
    }

    public function edit($key = null, $urlVar = 'a_id')
    {
        if (!$this->requireFrontendUser()) {
            return false;
        }

        $recordId = $this->getFrontendRecordId(true);
        $item = $this->getFrontendItemOrFail($recordId, 'COM_JEM_CATEGORY_ERROR_NOT_FOUND');

        if (!JemFrontendCategoryAccess::canEdit(JemFactory::getUser(), $item)) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $result = parent::edit($key, $urlVar);

        if (!$result) {
            $this->setRedirect($this->getReturnPage());
        }

        return $result;
    }

    public function save($key = null, $urlVar = 'a_id')
    {
        $this->checkToken();

        if (!$this->requireFrontendUser()) {
            return false;
        }

        $recordId = $this->getFrontendRecordId();

        if ($recordId > 0) {
            $item = $this->getFrontendItemOrFail($recordId, 'COM_JEM_CATEGORY_ERROR_NOT_FOUND');

            if (!JemFrontendCategoryAccess::canEdit(JemFactory::getUser(), $item)) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
        } else {
            $formData = $this->input->post->get('jform', array(), 'array');
            $parentId = (int) ($formData['parent_id'] ?? 1);

            if (!JemFrontendCategoryAccess::canCreate(JemFactory::getUser(), $parentId)) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
        }

        $result = parent::save($key, $urlVar);

        if ($result) {
            $this->setRedirect($this->getReturnPage());
        }

        return $result;
    }

    public function cancel($key = 'a_id')
    {
        $this->checkToken();

        if (!$this->requireFrontendUser()) {
            return false;
        }

        $recordId = $this->getFrontendRecordId();

        if ($recordId > 0) {
            $item = $this->getFrontendItemOrFail($recordId, 'COM_JEM_CATEGORY_ERROR_NOT_FOUND');

            if (!JemFrontendCategoryAccess::canEdit(JemFactory::getUser(), $item)) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
        } elseif (!JemFrontendCategoryAccess::canCreate(
            JemFactory::getUser(),
            $this->input->getInt('parent_id', 1)
        )) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $result = parent::cancel($key);
        $this->setRedirect($this->getReturnPage());

        return $result;
    }

    public function getModel($name = 'editcategory', $prefix = '', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }

    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'a_id')
    {
        $append = '&layout=edit';
        $itemId = $this->input->getInt('Itemid', 0);
        $return = $this->getReturnPage();

        if ($recordId) {
            $append .= '&' . $urlVar . '=' . (int) $recordId;
        }

        if ($itemId) {
            $append .= '&Itemid=' . $itemId;
        }

        if ($return) {
            $append .= '&return=' . base64_encode($return);
        }

        return $append;
    }

    protected function getReturnPage()
    {
        $return = $this->input->get('return', '', 'base64');
        $decodedReturn = $return ? base64_decode($return, true) : false;

        return ($decodedReturn && Uri::isInternal($decodedReturn))
            ? $decodedReturn
            : Uri::base();
    }
}

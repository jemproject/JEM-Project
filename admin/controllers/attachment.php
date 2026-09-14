<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once JPATH_COMPONENT_SITE . '/classes/controller.form.class.php';

class JemControllerAttachment extends JemControllerForm
{
    protected $text_prefix = 'COM_JEM_ATTACHMENT';
    protected $view_list = 'attachments';

    protected function allowAdd($data = array())
    {
        return false;
    }

    protected function allowEdit($data = array(), $key = 'id')
    {
        $id = isset($data[$key]) ? (int) $data[$key] : Factory::getApplication()->input->getInt($key);
        $object = $this->getModel()->getAttachmentObject($id);

        return $object !== null && JemHelperBackend::canAccessAttachment($object, 'edit');
    }
}

<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Language\Text;

abstract class JemControllerForm extends FormController
{
    /**
     * Require an authenticated frontend identity.
     *
     * @return boolean True for an authenticated user, false when login redirect was issued.
     */
    protected function requireFrontendUser()
    {
        return !JemFrontendAccess::redirectGuestToLogin($this->app);
    }

    /**
     * Normalise canonical and legacy frontend record ids.
     */
    protected function getFrontendRecordId($required = false)
    {
        return JemFrontendAccess::normaliseRecordId($this->input, $required);
    }

    /**
     * Load an existing editor item or return a real 404.
     */
    protected function getFrontendItemOrFail($recordId, $notFoundKey)
    {
        $item = $this->getModel()->getItem((int) $recordId);

        if (!$item || ((int) $item->id !== (int) $recordId)) {
            throw new Exception(Text::_($notFoundKey), 404);
        }

        return $item;
    }

    /**
     * Require create permission for an event or venue.
     */
    protected function assertFrontendCanAdd($type, $categoryIds = false)
    {
        JemFrontendAccess::enforce(
            JemFrontendAccess::decideAdd(JemFactory::getUser(), $type, $categoryIds)
        );
    }

    /**
     * Require edit permission based only on the stored record values.
     */
    protected function assertFrontendCanEdit($type, $item)
    {
        JemFrontendAccess::enforce(
            JemFrontendAccess::decideEdit(JemFactory::getUser(), $type, $item)
        );
    }

    /**
     * Escape a dynamic value for an HTML attribute.
     */
    protected function escapeHtmlAttribute($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /**
     * Function that allows child controller access to model data
     * after the data has been saved.
     *
     * @see    JemControllerForm::postSaveHook()
     *
     * @since  JEM 2.1.5
     */
    protected function _postSaveHook($model, $validData = array())
    {
        // Derived class will provide its own implementation if required.
    }

    /**
     * Function that allows child controller access to model data
     * after the data has been saved. - wrapper
     *
     * @param   BaseDatabaseModel   $model      The data model object.
     * @param   array                 $validData  The validated data.
     *
     * @return  void
     *
     * @since   12.2
     */
    protected function postSaveHook(BaseDatabaseModel $model, $validData = array())
    {
        $this->_postSaveHook($model, $validData);
    }
}

?>

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
     * Preview the recurrence decision for an authorised editor; never save data.
     *
     * @return void JSON response containing counts, permissions and a fingerprint.
     */
    public function recurrencePreview()
    {
        $this->checkToken();
        $data = $this->input->post->get('jform', [], 'array');
        $model = $this->getModel();
        if (!method_exists($model, 'previewRecurrenceChange') || !$this->allowEdit($data, 'id')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        \JemHelper::setNoStoreHeaders();
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->sendHeaders();
        try {
            $form = $model->getForm($data, false);
            if (!$form) {
                throw new RuntimeException(Text::_('JLIB_APPLICATION_ERROR_FORM_LOAD'));
            }

            // New clients send explicit ISO dates; older clients retain the editor filters.
            $isoDates = $this->input->post->getString('jem_recurrence_date_format', '') === 'iso';
            foreach (['dates', 'enddates', 'recurrence_limit_date'] as $name) {
                if (!array_key_exists($name, $data)) {
                    continue;
                }
                $field = $form->getField($name);
                if (!$field) {
                    throw new RuntimeException(Text::_('JLIB_APPLICATION_ERROR_FORM_LOAD'));
                }
                if ($isoDates) {
                    $value = $data[$name];
                    if (
                        !is_string($value) || ($value !== '' && (
                            !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
                            || !JemHelper::isValidCalendarDate($value, 'Y-m-d')
                        ))
                    ) {
                        throw new RuntimeException(Text::sprintf('COM_JEM_EVENT_ERROR_INVALID_DATE', $field->title));
                    }
                } else {
                    // Filter directly: an empty date must not become an XML default.
                    $data[$name] = $field->filter($data[$name]);
                }
            }

            echo new \Joomla\CMS\Response\JsonResponse($model->previewRecurrenceChange($data));
        } catch (\Throwable $error) {
            echo new \Joomla\CMS\Response\JsonResponse($error);
        }
        $this->app->close();
    }


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

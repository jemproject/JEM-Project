<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

require_once JPATH_ADMINISTRATOR . '/components/com_jem/helpers/attendeeform.php';

/** Full existing-reservation editor for authorised frontend event managers. */
class JemViewEditattendee extends JemView
{
    public $row;
    public $formDraft;
    public $pricing;
    public $capacity;
    public $commerceReadOnly;
    /** @param string|null $tpl Optional template suffix. @return void Render after event-scoped authorisation. */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $app->getLanguage()->load('com_jem', JPATH_ADMINISTRATOR, 'en-GB', true);
        $app->getLanguage()->load('com_jem', JPATH_ADMINISTRATOR, null, true);
        $this->row = $this->getModel()->getData();
        JemHelper::setNoStoreHeaders();
        $this->formDraft = JemAttendeeFormHelper::getDraft((int) $this->row->event, (int) $this->row->id);
        unset($this->formDraft['uid']);
        $this->row = JemAttendeeFormHelper::restoreRow($this->row, $this->formDraft);
        $this->pricing = $this->getModel()->getPricingData((int) $this->row->event, (int) $this->row->uid, (int) $this->row->id);
        $this->capacity = $this->getModel()->getCapacityData((int) $this->row->event, (int) $this->row->id);
        $this->commerceReadOnly = $this->pricing->is_priced && !JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_PRICING);
        $this->jemsettings = JemHelper::config();
        if ($this->formDraft) {
            foreach ($this->pricing->options as $option) { $option->quantity = (int) ($this->formDraft['admissions'][$option->id] ?? 0); }
            foreach ($this->capacity->options as &$option) { $option['current_quantity'] = (int) ($this->formDraft['capacity_areas'][$option['key']] ?? 0); }
            unset($option);
        }
        $this->backlink = Route::_('index.php?option=com_jem&view=attendees&id=' . (int) $this->row->event);
        JemHelper::loadCss('jem');
        JemHelper::loadCustomCss();
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->useScript('keepalive');
        $wa->registerAndUseScript('jem.registration-relocation', 'com_jem/registration-relocation.js', [], ['defer'=>true], ['core']);
        $wa->registerAndUseScript('jem.frontend-attendee', 'com_jem/frontend-attendee.js', [], ['defer'=>true], ['core']);
        foreach (['AREA','BEFORE','AFTER','OLD','QUOTED','DIFFERENCE','ADJUSTMENT','TOTAL','PAID','DUE','REFUND','CONFIRM_NOTICE','FAILED'] as $key) { Text::script('COM_JEM_RELOCATION_' . $key); }
        Text::script('COM_JEM_PRICED_REGISTRATION_SELECTION_REQUIRED');
        Text::script('COM_JEM_FE_RESERVATION_CONFIRM');
        $this->setLayout('default');
        parent::display($tpl);
    }
}

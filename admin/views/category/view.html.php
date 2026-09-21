<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

require_once JPATH_SITE . '/components/com_jem/classes/imagepublicationpolicy.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/categorycustomfields.class.php';

/**
 * Category View
 */
class JemViewCategory extends JemAdminView
{
    public $form;
    public $item;
    public $state;

    /**
     * Display the view
     */
    public function display($tpl = null)
    {
        $this->form        = $this->get('Form');
        $this->item        = $this->get('Item');
        $this->state    = $this->get('State');
        $this->canDo    = JemHelperBackend::getActions($this->state->get('category.component'));
        $this->featurePolicy = JemFeaturePolicy::current();

        $allowed = !empty($this->item->id)
            ? JemHelperBackend::canCategory('edit', $this->item)
            : JemHelperBackend::canCategory('create', null, (int) ($this->item->parent_id ?? 1));

        if (!$allowed) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $app = Factory::getApplication();
        $this->document = $app->getDocument();
        $wa = $this->document->getWebAssetManager();
        $wa->useScript('jquery');
        $wa->registerScript('jem.other', 'com_jem/other.js')->useScript('jem.other');
        if ($this->featurePolicy->isAdvanced()) {
            $wa->registerAndUseScript(
                'com_jem.category-custom-fields',
                'media/com_jem/js/categorycustomfields.js',
                array(),
                array('defer' => true)
            );
        }

        // Check for errors.
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }
        // build grouplist
        // @todo: make a form-field for this one
        $groups = $this->get('Groups');

        $grouplist = array();
        if (!empty($this->item->groupid) && !array_key_exists($this->item->groupid, $groups)) {
            $grouplist[] = HTMLHelper::_('select.option', $this->item->groupid, Text::sprintf('COM_JEM_CATEGORY_UNKNOWN_GROUP', $this->item->groupid));
        }
        $grouplist[] = HTMLHelper::_('select.option', '0', Text::_('COM_JEM_CATEGORY_NO_GROUP'));
        $grouplist   = array_merge($grouplist, $groups);

        $Lists['groups'] = HTMLHelper::_('select.genericlist', $grouplist, 'groupid', array('size'=>'1','class'=>'inputbox form-select m-0'), 'value', 'text', $this->item->groupid);
        $this->Lists     = $Lists;
        $this->imageProfileSummary = JemImage::profileSummary(JemHelper::config(), JemImageProfilePolicy::CATEGORY);
        if ($this->featurePolicy->isAdvanced()) {
            $this->categoryCustomFields = JemCategoryCustomFields::normaliseConfiguration($this->item->custom_fields ?? '');
            $this->legacyEventCustomFields = array();

            foreach (JemCustomFields::getOrderedFields('event') as $fieldName) {
                $fieldId = (int) substr($fieldName, 6);
                $fieldConfig = JemCustomFields::getFieldConfig('event', $fieldName);

                if (empty($fieldConfig['enabled'])) {
                    continue;
                }

                $this->legacyEventCustomFields[] = (object) array(
                    'id'          => $fieldId,
                    'name'        => $fieldName,
                    'label'       => JemCustomFields::getLabel('event', $fieldName, Text::_('COM_JEM_EVENT_CUSTOM_FIELD' . $fieldId)),
                    'description' => JemCustomFields::getDescription('event', $fieldName, Text::_('COM_JEM_EVENT_CUSTOM_FIELD' . $fieldId . '_DESC')),
                );
            }

            $this->joomlaEventCustomFields = array_values(JemCategoryCustomFields::getJoomlaEventFieldsById());
            $this->joomlaEventFieldGroups = JemCategoryCustomFields::getJoomlaEventFieldGroupsById();
        }

        JemImagePublicationPolicy::configureEditingForm($this->form, 'category', JemHelper::config());

        parent::display($tpl);

        $app->input->set('hidemainmenu', true);
        $this->addToolbar();
    }

    /**
     * Add the page title and toolbar.
     */
    protected function addToolbar()
    {
        // Initialise variables.
        $user        = JemFactory::getUser();
        $userId      = $user->get('id');
        $isNew       = ($this->item->id == 0);
        $checkedOut  = !($this->item->checked_out == 0 || $this->item->checked_out == $userId);

        // Get the results for each action.
        $canDo = JemHelperBackend::getActions();
        $canCreateCategory = JemHelperBackend::canCategory('create', null, (int) ($this->item->parent_id ?? 1));
        $canEditCategory   = !$checkedOut && JemHelperBackend::canCategory('edit', $this->item);
        $canSave           = ($isNew && $canCreateCategory) || (!$isNew && $canEditCategory);
        $canSave2New       = ($isNew && $canCreateCategory) || (!$isNew && $canEditCategory && JemHelperBackend::canCategory('create', null, (int) ($this->item->parent_id ?? 1)));
        $canSave2Copy      = !$isNew && JemHelperBackend::canCategory('create', null, (int) ($this->item->parent_id ?? 1));
        $cancelText        = $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE';

        $title = Text::_($isNew ? 'COM_JEM_ADD_CATEGORY' : 'COM_JEM_EDIT_CATEGORY');
        // Prepare the toolbar.
        ToolbarHelper::title($title, 'category-'.($isNew?'add':'edit').' -category-'.($isNew?'add':'edit'));

        if ($canSave) {
            ToolbarHelper::apply('category.apply');

            $toolbar = Toolbar::getInstance('toolbar');
            $saveGroup = $toolbar->dropdownButton('save-group')
                ->toggleSplit(true)
                ->icon('icon-save')
                ->buttonClass('btn btn-success')
                ->listCheck(false);

            $childBar = $saveGroup->getChildToolbar();
            $childBar->save('category.save');

            if ($canSave2New) {
                $childBar->save2new('category.save2new');
            }

            if ($canSave2Copy) {
                $childBar->save2copy('category.save2copy');
            }
        }

        ToolbarHelper::cancel('category.cancel', $cancelText);

        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
        ToolBarHelper::help('editcategories', true, 'https://www.joomlaeventmanager.net/documentation/backend/categories/add-category');
    }
}

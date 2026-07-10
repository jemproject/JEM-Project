<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewType extends JemAdminView
{
    public $form;
    public $item;
    public $state;
    protected $typeLanguages;
    protected $typeTranslations;

    public function display($tpl = null)
    {
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');
        $this->prepareTypeTranslations();

        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

        if (version_compare(JVERSION, '5.0.0', '>=')) {
            $wa->registerAndUseStyle('com_jem.fontawesome', 'com_jem/vendor/fontawesome-free/css/all.min.css');
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    private function prepareTypeTranslations()
    {
        $defaultLanguage = (string) ComponentHelper::getParams('com_languages')->get('site', '');
        if ($defaultLanguage === '') {
            $defaultLanguage = Factory::getApplication()->getLanguage()->getTag();
        }

        if (empty($this->item->base_language)) {
            $this->item->base_language = $defaultLanguage;
        }

        $translations = json_decode((string) $this->item->translations, true);
        $this->typeTranslations = is_array($translations) ? $translations : array();

        $savedLanguages = array_filter(array_map('trim', explode(',', (string) $this->item->translation_languages)));
        if (!$savedLanguages && $this->typeTranslations) {
            $savedLanguages = array_keys($this->typeTranslations);
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('lang_code', 'title', 'title_native')))
            ->from($db->quoteName('#__languages'))
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('title') . ' ASC');

        $db->setQuery($query);
        $activeLanguages = $db->loadObjectList('lang_code');

        $query = $db->getQuery(true)
            ->select($db->quoteName(array('element', 'name', 'enabled')))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('language'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->order($db->quoteName('name') . ' ASC');

        $db->setQuery($query);
        $installedLanguages = $db->loadObjectList();

        foreach ($installedLanguages as $installedLanguage) {
            $code = (string) $installedLanguage->element;
            if ($code === '' || isset($activeLanguages[$code])) {
                continue;
            }

            $activeLanguages[$code] = (object) array(
                'lang_code' => $code,
                'title' => $installedLanguage->name ?: $code,
                'title_native' => $installedLanguage->name ?: $code,
                'published' => (int) $installedLanguage->enabled,
            );
        }

        $orderedCodes = array();
        foreach (array_merge(array($this->item->base_language), $savedLanguages, array_keys($activeLanguages)) as $code) {
            $code = trim((string) $code);
            if ($code !== '' && !in_array($code, $orderedCodes, true)) {
                $orderedCodes[] = $code;
            }
        }

        $languages = array();
        foreach ($orderedCodes as $code) {
            $language = isset($activeLanguages[$code]) ? $activeLanguages[$code] : null;
            $title = $language ? ($language->title_native ?: $language->title) : $code;
            $languages[] = (object) array(
                'code' => $code,
                'title' => $title,
                'is_default' => $code === $this->item->base_language,
                'is_active' => isset($activeLanguages[$code]),
            );
        }

        $this->typeLanguages = $languages;
    }

    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user         = JemFactory::getUser();
        $isNew        = ($this->item->id == 0);
        $checkedOut   = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
        $canDo        = JemHelperBackend::getActions();
        $canSave      = !$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'));
        $canSave2New  = !$checkedOut && $canDo->get('core.create');
        $canSave2Copy = !$isNew && $canDo->get('core.create');
        $cancelText   = $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE';

        ToolbarHelper::title($isNew ? Text::_('COM_JEM_ADD_TYPE') : Text::_('COM_JEM_TYPE_EDIT'), 'tag');

        if ($canSave) {
            ToolbarHelper::apply('type.apply');

            $toolbar = Toolbar::getInstance('toolbar');
            $saveGroup = $toolbar->dropdownButton('save-group')
                ->toggleSplit(true)
                ->icon('icon-save')
                ->buttonClass('btn btn-success')
                ->listCheck(false);

            $childBar = $saveGroup->getChildToolbar();
            $childBar->save('type.save');

            if ($canSave2New) {
                $childBar->save2new('type.save2new');
            }

            if ($canSave2Copy) {
                $childBar->save2copy('type.save2copy');
            }
        }

        ToolbarHelper::cancel('type.cancel', $cancelText);
        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
    }
}
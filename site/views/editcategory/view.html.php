<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once JPATH_COMPONENT_SITE . '/classes/imagepublicationpolicy.class.php';

/**
 * Frontend JEM category editor view.
 */
class JemViewEditcategory extends JemView
{
    public $form;
    public $item;
    public $state;
    protected $return_page;

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = JemFactory::getUser();

        if (JemFrontendAccess::redirectGuestToLogin($app)) {
            return false;
        }

        $this->state = $this->get('State');
        $this->item = $this->get('Item');
        $this->form = $this->get('Form');
        $this->return_page = $this->get('ReturnPage');

        if (!$this->item || !$this->form) {
            throw new Exception(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
        }

        $isNew = empty($this->item->id);
        $allowed = $isNew
            ? JemFrontendCategoryAccess::canCreate($user, (int) $this->item->parent_id)
            : JemFrontendCategoryAccess::canEdit($user, $this->item);

        if (!$allowed) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->jemsettings = JemHelper::config();
        $this->settings = JemHelper::globalattribs();
        $this->params = $this->state->get('params');
        $this->user = $user;
        $this->canEditState = JemFrontendCategoryAccess::canEditState(
            $user,
            $isNew ? null : $this->item,
            (int) $this->item->parent_id
        );

        if (!$this->canEditState) {
            $this->form->setFieldAttribute('published', 'readonly', 'true');
            $this->form->setValue('published', null, $isNew ? 0 : (int) $this->item->published);
        }

        $this->imageProfileSummary = JemImage::profileSummary(
            $this->jemsettings,
            JemImageProfilePolicy::CATEGORY
        );
        $this->imageProfileRequired = JemImageProfilePolicy::isRequired(
            $this->jemsettings,
            JemImageProfilePolicy::CATEGORY
        );
        $this->cimage = JemImage::flyercreator((string) $this->item->image, 'category');

        JemImagePublicationPolicy::configureEditingForm($this->form, 'category', $this->jemsettings);

        JemHelper::loadCss('jem');
        JemHelper::loadCustomCss();
        JemHelper::loadCustomTag();

        $wa = $app->getDocument()->getWebAssetManager();
        $wa->useScript('jquery');
        $wa->registerScript('jem.other', 'com_jem/other.js')->useScript('jem.other');

        $title = $isNew
            ? Text::_('COM_JEM_EDITCATEGORY_ADD_CATEGORY')
            : Text::sprintf('COM_JEM_EDITCATEGORY_EDIT_CATEGORY', $this->item->catname);
        $this->params->set('page_title', $title);
        $this->params->set('page_heading', $title);
        $this->pageclass_sfx = htmlspecialchars((string) $this->params->get('pageclass_sfx'), ENT_QUOTES, 'UTF-8');

        $document = $app->getDocument();
        $document->setTitle($title);
        $document->setMetaData('robots', 'noindex, nofollow');

        $errors = $this->get('Errors');

        if (is_array($errors) && $errors) {
            $app->enqueueMessage(implode("\n", $errors), 'warning');

            return false;
        }

        parent::display($tpl);
    }
}

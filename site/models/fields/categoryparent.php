<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Safe parent selector for the frontend JEM category editor.
 */
class JFormFieldCategoryparent extends ListField
{
    protected $type = 'Categoryparent';

    protected function getOptions()
    {
        $user = JemFactory::getUser();
        $levels = array_map('intval', (array) $user->getAuthorisedViewLevels());
        $currentId = $this->form ? (int) $this->form->getValue('id') : 0;
        $currentParentId = $this->form ? (int) $this->form->getValue('parent_id') : 0;
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'parent_id', 'lft', 'rgt', 'level', 'catname', 'access')))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('id') . ' > 0')
            ->where($db->quoteName('published') . ' IN (0, 1)')
            ->order($db->quoteName('lft') . ' ASC');

        if ($levels) {
            $query->where($db->quoteName('access') . ' IN (' . implode(',', $levels) . ')');
        } else {
            $query->where('1 = 0');
        }

        $db->setQuery($query);
        $categories = (array) $db->loadObjectList();
        $current = null;

        if ($currentId > 0) {
            foreach ($categories as $category) {
                if ((int) $category->id === $currentId) {
                    $current = $category;
                    break;
                }
            }
        }

        $options = array();

        foreach ($categories as $category) {
            $categoryId = (int) $category->id;

            if ($categoryId === $currentId) {
                continue;
            }

            if ($current && (int) $category->lft > (int) $current->lft
                && (int) $category->rgt < (int) $current->rgt) {
                continue;
            }

            $isCurrentParent = $categoryId === $currentParentId;

            if (!$isCurrentParent && !JemFrontendCategoryAccess::canCreate($user, $categoryId)) {
                continue;
            }

            $label = $categoryId === 1
                ? Text::_('COM_JEM_CATEGORY_ROOT')
                : str_repeat('- ', max(0, (int) $category->level - 1)) . (string) $category->catname;
            $options[] = HTMLHelper::_('select.option', $categoryId, $label);
        }

        return array_merge(parent::getOptions(), $options);
    }
}

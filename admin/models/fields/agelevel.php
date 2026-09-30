<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldAgelevel extends FormField
{
    protected $type = 'Agelevel';

    protected function getInput()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'title', 'min_age', 'max_age', 'published')))
            ->from($db->quoteName('#__jem_age_levels'))
            ->where('(' . $db->quoteName('published') . ' = 1 OR ' . $db->quoteName('id') . ' = ' . (int) $this->value . ')')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('min_age') . ' ASC');

        try {
            $db->setQuery($query);
            $levels = $db->loadObjectList() ?: array();
        } catch (RuntimeException $error) {
            $levels = array();
        }

        $options = array(
            HTMLHelper::_('select.option', '', Text::_('COM_JEM_AGE_LEVEL_NOT_SPECIFIED')),
        );

        foreach ($levels as $level) {
            $label = Text::sprintf(
                'COM_JEM_AGE_LEVEL_OPTION',
                $level->title,
                (int) $level->min_age,
                (int) $level->max_age
            );

            if (!(int) $level->published) {
                $label .= ' ' . Text::_('JUNPUBLISHED');
            }

            $options[] = HTMLHelper::_('select.option', (int) $level->id, $label);
        }

        $classes = preg_split('/\s+/', trim((string) $this->class)) ?: array();
        $classes[] = 'form-select';
        $classes[] = 'w-auto';
        $attributes = array(
            'id' => $this->id,
            'class' => implode(' ', array_unique(array_filter($classes))),
        );

        return HTMLHelper::_(
            'select.genericlist',
            $options,
            $this->name,
            $attributes,
            'value',
            'text',
            $this->value,
            $this->id
        );
    }
}

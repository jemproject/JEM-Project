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

class JFormFieldJemtype extends FormField
{
    protected $type = 'Jemtype';

    protected function getInput()
    {
        $entity = (int) $this->getAttribute('entity', 1);

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'name', 'icon', 'color')))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('entity') . ' = ' . $entity)
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');

        $db->setQuery($query);
        $types = $db->loadObjectList();

        $options   = array();
        $options[] = HTMLHelper::_('select.option', '', Text::_('COM_JEM_TYPE_SELECT_NONE'));

        foreach ($types as $t) {
            $label = htmlspecialchars($t->name, ENT_QUOTES, 'UTF-8');
            if ($t->icon) {
                $label = '<span class="' . htmlspecialchars($t->icon, ENT_QUOTES, 'UTF-8') . '"></span> ' . $label;
            }
            $options[] = HTMLHelper::_('select.option', $t->id, $t->name);
        }

        $attribs = array(
            'id'    => $this->id,
            'class' => 'form-select ' . $this->class,
        );

        return HTMLHelper::_('select.genericlist', $options, $this->name, $attribs, 'value', 'text', $this->value);
    }
}

<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_BASE') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Form\FormHelper;

// ensure JemFactory is loaded (because field maybe used by modules too)
require_once(JPATH_SITE.'/components/com_jem/factory.php');

FormHelper::loadFieldClass('list');

/**
 * Category formfield.
 *
 */
class JFormFieldCategoryParent extends ListField
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 */
	protected $type = 'CategoryParent';

	/**
	 * Method to get the field options.
	 *
	 * @return	array	The field option objects.
	 */
	protected function getOptions()
	{
		// Initialise variables.
		$options = array();
		$name = (string) $this->element['name'];

		// Let's get the id for the current item, either category or content item.
		$jinput = Factory::getApplication()->input;
		// For categories the old category is the category id 0 for new category.
		if ($this->element['parent'])
		{
			$oldCat = $jinput->get('id',0);
			$oldParent = $this->form->getValue($name);
		}
		else
		// For items the old category is the category they are in when opened or 0 if new.
		{
			$thisItem = $jinput->get('id',0);
			$oldCat = $this->form->getValue($name);
		}

        $db = Factory::getContainer()->get('DatabaseDriver');
		$query	= $db->getQuery(true);

		$query->select('a.id AS value, a.title AS text, a.level');
		$query->from('#__categories AS a');
		$query->join('LEFT', $db->quoteName('#__categories').' AS b ON a.lft > b.lft AND a.rgt < b.rgt');

		// Filter by the type
		if ($extension = $this->form->getValue('extension')) {
			$query->where('(a.extension = '.$db->quote($extension).' OR a.parent_id = 0)');
		}
		if ($this->element['parent'])
		{
		// Prevent parenting to children of this item.
			if ($id = $this->form->getValue('id')) {
				$query->join('LEFT', $db->quoteName('#__categories').' AS p ON p.id = '.(int) $id);
				$query->where('NOT(a.lft >= p.lft AND a.rgt <= p.rgt)');

				$rowQuery	= $db->getQuery(true);
				$rowQuery->select('a.id AS value, a.title AS text, a.level, a.parent_id');
				$rowQuery->from('#__categories AS a');
				$rowQuery->where('a.id = ' . (int) $id);
				$db->setQuery($rowQuery);
				$row = $db->loadObject();
			}
		}
		$query->where('a.published IN (0,1)');
		$query->group('a.id, a.title, a.level, a.lft, a.rgt, a.extension, a.parent_id');
		$query->order('a.lft ASC');

		// Get the options.
		$db->setQuery($query);

		

		// Check for a database error.
		// if ($db->getErrorNum()) {
		// 	Factory::getApplication()->enqueueMessage($db->getErrorMsg(), 'warning');
		// }
		try 
		{
			$options = $db->loadObjectList();
		} 
		catch (\InvalidArgumentException $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
		}

		// Pad the option text with spaces using depth level as a multiplier.
		for ($i = 0, $n = (is_array($options) ? count($options) : 0); $i < $n; $i++)
		{
			// Translate ROOT
			if ($options[$i]->level == 0) {
				$options[$i]->text = Text::_('JGLOBAL_ROOT_PARENT');
			}

			$options[$i]->text = str_repeat('- ', $options[$i]->level).$options[$i]->text;
		}

		// Initialise variables.

				// Get the current user object.
				$user = JemFactory::getUser();

				// For new items we want a list of categories you are allowed to create in.
				if ($oldCat == 0)
				{
					foreach ($options as $i => $option)
					{
						// To take save or create in a category you need to have create rights for that category
						// unless the item is already in that category.
						// Unset the option if the user isn't authorised for it. In this field assets are always categories.
						if ($user->authorise('core.create', $extension . '.category.' . $option->value) != true )
						{
							unset($options[$i]);
						}
					}
				}
				// If you have an existing category id things are more complex.
				else
				{
					//$categoryOld = $this->form->getValue($name);
					foreach ($options as $i => $option)
					{
						// If you are only allowed to edit in this category but not edit.state, you should not get any
						// option to change the category parent for a category or the category for a content item,
						// but you should be able to save in that category.
						if ($user->authorise('core.edit.state', $extension . '.category.' . $oldCat) != true)
						{
							if ($option->value != $oldCat)
							{//echo 'y';
								unset($options[$i]);
							}
						}
						// However, if you can edit.state you can also move this to another category for which you have
						// create permission and you should also still be able to save in the current category.
						elseif
							(($user->authorise('core.create', $extension . '.category.' . $option->value) != true)
							&& $option->value != $oldCat)
						{//echo 'x';
							unset($options[$i]);
						}
					}
				}


		if (isset($row) && !isset($options[0])) {
			if ($row->parent_id == '1') {
				$parent = new stdClass();
				$parent->text = Text::_('JGLOBAL_ROOT_PARENT');
				array_unshift($options, $parent);
			}
		}

		// Merge any additional options in the XML definition.
		$options = array_merge(parent::getOptions(), $options);

		return $options;
	}
}

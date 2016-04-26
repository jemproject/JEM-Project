<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('JPATH_BASE') or die;

JFormHelper::loadFieldClass('list');

/**
 * Field: Venueoptions
 */
class JFormFieldVenueoptions extends JFormFieldList
{
	/**
	 * A venue list
	 */
	public $type = 'Venueoptions';

	/**
	 * @return	array	The field option objects.
	 */
	protected function getOptions()
	{
		// Initialise variables.
		$options = array();
		$published = $this->element['published']? $this->element['published'] : array(0,1);
		$name = (string) $this->element['name'];

		// Let's get the id for the current item
		$jinput = JFactory::getApplication()->input;

		// Create SQL
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);

		$query->select('l.id AS value, l.venue AS text, l.published');
		$query->from('#__jem_venues AS l');

		// Filter on the published state
		if (is_numeric($published))
		{
			$query->where('l.published = ' . (int) $published);
		}
		elseif (is_array($published))
		{
			JArrayHelper::toInteger($published);
			$query->where('l.published IN (' . implode(',', $published) . ')');
		}

		$query->group('l.id');
		$query->order('l.venue');

		// Get the options.
		$db->setQuery($query);

		try
		{
			$options = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			JError::raiseWarning(500, $e->getMessage);
		}

		// Merge any additional options in the XML definition.
		$options = array_merge(parent::getOptions(), $options);

		return $options;
	}
}

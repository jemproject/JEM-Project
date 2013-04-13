<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// Check to ensure this file is included in Joomla!
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined('_JEXEC') or die();


jimport('joomla.form.formfield');
jimport('joomla.html.parameter.element');
/**
 * Renders an Category element
 *
 * @package JEM
 * @since 0.9
 */

class JFormFieldCategories extends JFormField
{
	
	
	
        var $type = 'categories';

        function getInput() {
        return JElementCategories::fetchElement($this->name, $this->value, $this->element, $this->options['control']);
        }

}
	
	
/*	 protected $type = 'Categories';
	
	
         * Method to get a list of options for a list input.
         *
         * @return      array           An array of JHtml options.
        
        protected function getOptions() 
        {
                $db = JFactory::getDBO();
                $query = $db->getQuery(true);
                $query->select('id,catname');
                $query->from('#__jem_categories');
                $db->setQuery((string)$query);
                $messages = $db->loadObjectList();
                $options = array();
                if ($messages)
                {
                        foreach($messages as $message) 
                        {
                                $options[] = JHtml::_('select.option', $message->id, $message->catname);
                        }
                }
                $options = array_merge(parent::getOptions(), $options);
                return $options;
        }
	*/
	

	
	
class JElementCategories extends JElement {	
	/**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$_name = 'categories';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$doc 		= JFactory::getDocument();
		$fieldName	= $control_name.'['.$name.']';

		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_jem'.DS.'tables');

		$category = JTable::getInstance('jem_categories', '');

		if ($value) {
			$category->load($value);
		} else {
			$category->catname = JText::_('SELECT CATEGORY');
		}

		$js = "
		function elSelectCategory(id, category) {
			document.getElementById('a_id').value = id;
			document.getElementById('a_name').value = category;
			window.parent.SqueezeBox.close();
		}
		
		function elCatReset() {
		  document.getElementById('a_id').value = 0;
      document.getElementById('a_name').value = '".htmlspecialchars(JText::_('SELECT CATEGORY'))."';
	  }
		";

		$link = 'index.php?option=com_jem&amp;view=categoryelement&amp;tmpl=component';
		$doc->addScriptDeclaration($js);

		JHTML::_('behavior.modal', 'a.modal');

		$html = "\n<div style=\"float: left;\"><input style=\"background: #ffffff;\" type=\"text\" id=\"a_name\" value=\"$category->catname\" disabled=\"disabled\" /></div>";
		$html .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('Select')."\"  href=\"$link\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('Select')."</a></div></div>\n";
    $html .= "<div class=\"button2-left\"><div class=\"blank\"><a title=\"".JText::_('Reset')."\" onClick=\"elCatReset();return false;\" >".JText::_('Reset')."</a></div></div>\n";
		$html .= "\n<input type=\"hidden\" id=\"a_id\" name=\"$fieldName\" value=\"$value\" />";

		return $html;
	}
	
	
	
	
}  // End of class
?>
<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die();

jimport('joomla.form.formfield');
jimport('joomla.html.parameter.element');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');

/**
 * Imageselect Field
 *
 */
class JFormFieldImageselect extends JFormFieldList
{
	protected $type = 'Imageselect';

	public function getLabel() {
		// code that returns HTML that will be shown as the label
	}

	/**
	 * Method to get the field input markup.
	 *
	 * @return	string	The field input markup.
	 *
	 */
	public function getInput()
	{
		// Load the modal behavior script.
		JHtml::_('behavior.modal', 'a.modal');

		// ImageType
		$imagetype = $this->element['imagetype'];
		
		// Build the script.
		$script = array();
		$script[] = '	function SelectImage(image, imagename) {';
		$script[] = '		document.getElementById(\'a_image\').value = image';
		$script[] = '		document.getElementById(\'a_imagename\').value = imagename';
		$script[] = '		document.getElementById(\'imagelib\').src = \'../images/jem/'.$imagetype.'/\' + image';
		$script[] = '		window.parent.SqueezeBox.close()';
		$script[] = '	}';
		
		switch ($imagetype)
		{
			case 'categories':
				$task 		= 'categoriesimg';
				$taskselect = 'selectcategoriesimg';
				break;
			case 'events':
				$task 		= 'eventimg';
				$taskselect = 'selecteventimg';
				break;	
			case 'venues':
				$task 		= 'venueimg';
				$taskselect = 'selectvenueimg';
				break;	
		}
		
		// Add the script to the document head.
		JFactory::getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display.
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=imagehandler&amp;layout=uploadimage&amp;task='.$task.'&amp;tmpl=component';
		$link2 = 'index.php?option=com_jem&amp;view=imagehandler&amp;task='.$taskselect.'&amp;tmpl=component';

		//
		$html[] = "<div class=\"fltlft\">";
		$html[] = "<input style=\"background: #ffffff;\" type=\"text\" id=\"a_imagename\" value=\"$this->value\" disabled=\"disabled\" onchange=\"javascript:if (document.forms[0].a_imagename.value!='') {document.imagelib.src='../images/jem/$imagetype/' + document.forms[0].a_imagename.value} else {document.imagelib.src='../media/system/images/blank.png'}\"; />";
		$html[] = "</div>";

		$html[] = "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_UPLOAD')."\" href=\"$link\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_UPLOAD')."</a></div></div>\n";
		$html[] = "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_SELECTIMAGE')."\" href=\"$link2\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_SELECTIMAGE')."</a></div></div>\n";
		$html[] = "\n&nbsp;<input class=\"inputbox\" type=\"button\" onclick=\"SelectImage('', '".JText::_('COM_JEM_SELECTIMAGE')."');\" value=\"".JText::_('COM_JEM_RESET')."\" />";

		$html[] = "\n<input type=\"hidden\" id=\"a_image\" name=\"$this->name\" value=\"$this->value\" />";

		$html [] = "<img src=\"../media/system/images/blank.png\" name=\"imagelib\" id=\"imagelib\" border=\"2\" alt=\"Preview\" />";
		$html [] = "<script type=\"text/javascript\">";
		$html [] = "if (document.forms[0].a_imagename.value!='') {";
		$html [] = "var imname = document.forms[0].a_imagename.value;";
		$html [] = "jsimg='../images/jem/$imagetype/' + imname;";
		$html [] = "document.getElementById('imagelib').src= jsimg;";
		$html [] = "}";
		$html [] = "</script>";

		return implode("\n", $html);
	}
}
?>
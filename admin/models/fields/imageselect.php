<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

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
		// JHtml::_('behavior.modal', 'a.modal');

		// ImageType
		$imagetype = $this->element['imagetype'];
		
		// Build the script.
		$script = array();
		$script[] = '	function SelectImage(image, imagename) {';
		$script[] = '		document.getElementById(\'a_image\').value = image';
		$script[] = '		document.getElementById(\'a_imagename\').value = imagename';
		$script[] = '		document.getElementById(\'imagelib\').src = \'../images/jem/'.$imagetype.'/\' + image';
		// $script[] = '		window.parent.SqueezeBox.close()';
		$script[] = '        $(".btn-close").trigger("click");';
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
		Factory::getApplication()->getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display.
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=imagehandler&amp;layout=uploadimage&amp;task='.$task.'&amp;tmpl=component';
		$link2 = 'index.php?option=com_jem&amp;view=imagehandler&amp;task='.$taskselect.'&amp;tmpl=component';

		//
		$html[] = "<div class=\"fltlft\">";
		$html[] = "<input class=\"form-control\" style=\"background: #fff;\" type=\"text\" id=\"a_imagename\" value=\"$this->value\" disabled=\"disabled\" onchange=\"javascript:if (document.forms[0].a_imagename.value!='') {document.imagelib.src='../images/jem/$imagetype/' + document.forms[0].a_imagename.value} else {document.imagelib.src='../media/com_jem/images/blank.png'}\"; />";
		$html[] = "</div>";
		$html[] = "<div class=\"button2-left\"><div class=\"blank\">";
			$html[] = JHtml::_(
				'bootstrap.renderModal',
				'imageupload-modal',
				array(		
					'url'    => $link,
					'title'  => Text::_('COM_JEM_UPLOAD'),
					'width'  => '650px',
					'height' => '500px',
					'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
				)
			);
			$html[] ='<button type="button" class="btn btn-primary btn-margin" data-bs-toggle="modal"  data-bs-target="#imageupload-modal">'.Text::_('COM_JEM_UPLOAD').'
			</button>';

		$html[] ='</div></div>';
		// $html[] = "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".Text::_('COM_JEM_SELECTIMAGE')."\" href=\"$link2\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".Text::_('COM_JEM_SELECTIMAGE')."</a></div></div>\n";
		$html[] = "<div class=\"button2-left\"><div class=\"blank\">";
		$html[] = JHtml::_(
			'bootstrap.renderModal',
			'imageselect-modal',
			array(		
				'url'    => $link2,
				'title'  => Text::_('COM_JEM_SELECTIMAGE'),
				'width'  => '650px',
				'height' => '500px',
				'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
			)
		);
		$html[] = "<button type=\"button\" class=\"btn btn-primary btn-margin\" data-bs-toggle=\"modal\" data-bs-target=\"#imageselect-modal\">".Text::_('COM_JEM_SELECTIMAGE')."
		</button>";
		$html[] = "</div></div>";
		$html[] = "\n&nbsp;<input class=\"btn btn-danger btn-margin\" type=\"button\" onclick=\"SelectImage('', '".Text::_('COM_JEM_SELECTIMAGE')."');\" value=\"".Text::_('COM_JEM_RESET')."\" />";
		$html[] = "\n<input type=\"hidden\" id=\"a_image\" name=\"$this->name\" value=\"$this->value\" />";
		$html[] = "<img src=\"../media/com_jem/images/blank.png\" name=\"imagelib\" id=\"imagelib\" class=\"venue-image\" alt=\"".Text::_('COM_JEM_SELECTIMAGE_PREVIEW')."\" />";
		$html[] = "<script type=\"text/javascript\">";
		$html[] = "if (document.forms[0].a_imagename.value!='') {";
		$html[] = "var imname = document.forms[0].a_imagename.value;";
		$html[] = "jsimg='../images/jem/$imagetype/' + imname;";
		$html[] = "document.getElementById('imagelib').src= jsimg;";
		$html[] = "}";
		$html[] = "</script>";

		return implode("\n", $html);
	}
}
?>

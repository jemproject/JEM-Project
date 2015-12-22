<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class for the JEM Help screen
 *
 * @package JEM
 *
 */
class JemViewHelp extends JemAdminView
{

	public function display($tpl = null) {
		//Load filesystem folder and pane behavior
		jimport('joomla.html.pane');
		jimport('joomla.filesystem.folder');

		//initialise variables
		$lang 			= JFactory::getLanguage();

		//get vars
		$helpsearch 	= JFactory::getApplication()->input->getString('filter_search', '');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Check for files in the actual language
		$langTag = $lang->getTag();

		if (!JFolder::exists(JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag)) {
			$langTag = 'en-GB';		// use english as fallback
		}

		//search the keyword in the files
		$toc 		= JEMViewHelp::getHelpToc($helpsearch);

		//assign data to template
		$this->langTag 		= $langTag;
		$this->helpsearch 	= $helpsearch;
		$this->toc 			= $toc;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Compiles the help table of contents
	 * Based on the Joomla admin component
	 *
	 * @param string A specific keyword on which to filter the resulting list
	 */
	function getHelpTOC($helpsearch)
	{
		$lang = JFactory::getLanguage();
		jimport('joomla.filesystem.folder');

		// Check for files in the actual language
		$langTag = $lang->getTag();

		if(!JFolder::exists(JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag)) {
			$langTag = 'en-GB';		// use english as fallback
		}
		$files = JFolder::files(JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag, '\.xml$|\.html$');

		$toc = array();
		foreach ($files as $file) {
			$buffer = file_get_contents(JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag.'/'.$file);
			if (preg_match('#<title>(.*?)</title>#', $buffer, $m)) {
				$title = trim($m[1]);
				if ($title) {
					if ($helpsearch) {
						if (JString::strpos(strip_tags($buffer), $helpsearch) !== false) {
							$toc[$file] = $title;
						}
					} else {
						$toc[$file] = $title;
					}
				}
			}
		}
		asort($toc);
		return $toc;
	}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		//create the toolbar
		JToolBarHelper::title(JText::_('COM_JEM_HELP'), 'help');
	}
}
?>
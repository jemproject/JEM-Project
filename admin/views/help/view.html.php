<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Filesystem\Folder;

jimport('joomla.html.pane');


/**
 * View class for the JEM Help screen
 *
 * @package JEM
 */
class JemViewHelp extends JemAdminView
{

	public function display($tpl = null)
	{
		//initialise variables
		$lang = Factory::getApplication()->getLanguage();
		$app = Factory::getApplication();
		$this->document = $app->getDocument();

		//get vars
		$helpsearch = Factory::getApplication()->input->getString('filter_search', '');

		// // Load css
		// JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = $app->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
		// Check for files in the actual language
		$langTag = $lang->getTag();

		if (!Folder::exists(JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag)) {
			$langTag = 'en-GB';		// use english as fallback
		}

		//search the keyword in the files
		$toc = JemViewHelp::getHelpToc($helpsearch);

		//assign data to template
		$this->langTag    = $langTag;
		$this->helpsearch = $helpsearch;
		$this->toc        = $toc;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Compiles the help table of contents
	 * Based on the Joomla admin component
	 *
	 * @param  string A specific keyword on which to filter the resulting list
	 */
	public function getHelpTOC($helpsearch)
	{
		$lang = Factory::getApplication()->getLanguage();

		// Check for files in the actual language
		$langTag = $lang->getTag();

		if (!Folder::exists(JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag)) {
			$langTag = 'en-GB';		// use english as fallback
		}
		$files = Folder::files(JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag, '\.xml$|\.html$');

		$toc = array();
		foreach ($files as $file) {
			$buffer = file_get_contents(JPATH_SITE .'/administrator/components/com_jem/help/'.$langTag.'/'.$file);
			if (preg_match('#<title>(.*?)</title>#', $buffer, $m)) {
				$title = trim($m[1]);
				if ($title) {
					if ($helpsearch) {
						if (\Joomla\String\StringHelper::strpos(strip_tags($buffer), $helpsearch) !== false) {
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
		ToolbarHelper::title(Text::_('COM_JEM_HELP'), 'help');
		ToolBarHelper::divider();
		ToolBarHelper::help('help', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/control-panel/help');
	}
}
?>

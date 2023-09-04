<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * JemView class with JEM specific extensions
 *
 * @package JEM
 */
class JemAdminView extends JViewLegacy
{
	/**
	 * Display the view
	 */
	public function display($tpl = null)
	{
		if (JemSidebarHelper::getEntries()) {
			$this->sidebar = JemSidebarHelper::render();
		}

		parent::display($tpl);
	}
}

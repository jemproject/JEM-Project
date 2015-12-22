<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
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
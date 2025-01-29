<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;

/**
 * JemView class with JEM specific extensions
 *
 * @package JEM
 */
class JemAdminView extends HtmlView
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

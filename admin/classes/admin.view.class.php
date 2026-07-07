<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
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

    protected function getToolbarInstance()
    {
        if (class_exists('\\Joomla\\CMS\\Toolbar\\Toolbar')) {
            return \Joomla\CMS\Toolbar\Toolbar::getInstance('toolbar');
        }

        if (class_exists('\\JToolbar')) {
            return \JToolbar::getInstance('toolbar');
        }

        return null;
    }

    protected function supportsToolbarDropdown($toolbar)
    {
        return is_object($toolbar) && method_exists($toolbar, 'dropdownButton');
    }
}

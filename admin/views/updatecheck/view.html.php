<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;

/**
 * View class for the JEM Updatecheck screen
 *
 * @package JEM
 *
 */
class JemViewUpdatecheck extends JemAdminView
{
    protected $app;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->app = Factory::getApplication();
    }

    public function display($tpl = null)
    {
        // Get data from the model
        $updatedata = $this->get('Updatedata');

        // Check if data was retrieved successfully
        if ($updatedata === false) {
            $this->app->enqueueMessage(Text::_('COM_JEM_ERROR_UPDATEDATA'), 'warning');
            $updatedata = new stdClass();
        }

        // Load CSS properly
        $this->loadCss();

        // Assign data to template
        $this->updatedata = $updatedata;

        // Add toolbar
        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Load CSS assets
     */
    protected function loadCss()
    {
        $wa = $this->app->getDocument()->getWebAssetManager();

        // Register style if not already registered
        if (!$wa->assetExists('style', 'jem.backend')) {
            $wa->registerStyle('jem.backend', 'com_jem/backend.css');
        }
        $wa->useStyle('jem.backend');
    }

    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_UPDATECHECK_TITLE'), 'settings');

        // Use cancel instead of deprecated back
        ToolbarHelper::cancel('settings.cancel', 'JTOOLBAR_CLOSE');
        ToolbarHelper::divider();
        ToolBarHelper::help('update', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/control-panel/check-update');
    }
}

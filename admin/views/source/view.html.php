<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Client\ClientHelper;

/**
 * Source view
 *
 */
class JemViewSource extends JemAdminView
{
    public $form;
    protected $ftp;
    protected $source;
    protected $details;
    public $state;
    protected $template;

    /**
     * Display the view
     */
    public function display($tpl = null)
    {
        if (!JemHelperBackend::canManage('jem.tools.manage')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        // Initialise variables.
        $this->form     = $this->get('Form');
        $this->ftp      = ClientHelper::setCredentialsFromRequest('ftp');
        $this->source   = $this->get('Source');
        $this->details  = $this->get('SourceDetails');
        $this->state    = $this->get('State');
        $this->template = $this->get('Template');

        // Check for errors.
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        ToolbarHelper::title(Text::_('COM_JEM_CSSMANAGER_EDIT_FILE'), 'thememanager');

        if (JemHelperBackend::canManage('jem.tools.manage')) {
            ToolbarHelper::apply('source.apply');
            ToolbarHelper::save('source.save');
        }

        ToolbarHelper::cancel('source.cancel', 'JTOOLBAR_CLOSE');
        ToolbarHelper::divider();
        ToolBarHelper::help('editcss', true, 'https://www.joomlaeventmanager.net/documentation/backend/control-panel/css-manager/edit-file');
    }
}

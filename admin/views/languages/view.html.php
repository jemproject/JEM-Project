<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View for the official JEM language package catalog.
 */
class JemViewLanguages extends JemAdminView
{
    public $items;
    public $catalogStatus;
    public $jemVersion;
    public $jemMajor;

    public function display($tpl = null)
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->items = $this->get('Items');
        $this->catalogStatus = $this->get('CatalogStatus');
        $this->jemVersion = $this->get('JemVersion');
        $this->jemMajor = $this->get('JemMajor');

        if ($errors = $this->get('Errors')) {
            Factory::getApplication()->enqueueMessage(implode('<br>', $errors), 'error');
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_LANGUAGES_TITLE'), 'comments-2');
        ToolbarHelper::link(Route::_('index.php?option=com_jem&view=main'), 'JTOOLBAR_CLOSE', 'cancel');
    }
}

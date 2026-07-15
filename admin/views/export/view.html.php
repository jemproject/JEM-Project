<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class Export
 *
 * @package JEM
 *
 */
class JemViewExport extends JemAdminView
{

    public function display($tpl = null) {
        //initialise variables
        $app = Factory::getApplication();
        $document = $app->getDocument();

        // Load css
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
        $wa->usePreset('choicesjs')->useScript('webcomponent.field-fancy-select');
        //Cause of group limits we can't use class here to build the categories tree
        $categories = $this->get('Categories');
        $catalogState = (array) $app->getUserState('com_jem.export.catalog', array());
        $this->catalogFilters = array_merge(array(
            'dates' => date('Y-m-d', strtotime('-12 months')),
            'enddates' => date('Y-m-d', strtotime('+12 months')),
            'cid' => array(),
            'venue_ids' => array(),
            'type_ids' => array(),
            'search' => '',
            'published' => '',
        ), (array) ($catalogState['filters'] ?? array()));
        $this->catalogIncludeCategories = isset($catalogState['include_categories'])
            ? (int) $catalogState['include_categories']
            : 1;
        $this->catalogSelectedFields = (array) ($catalogState['fields'] ?? $this->getModel()->getDefaultCatalogExportFields());
        $this->catalogPreview = $this->get('CatalogExportPreview');

        //build selectlists
        $categories = JEMCategories::buildcatselect($categories, 'cid[]', $this->catalogFilters['cid'], 0, 'multiple="multiple" size="8" class="form-select" id="cid"');
        $this->catalogVenues = HTMLHelper::_('select.genericlist', $this->get('CatalogExportVenues'), 'catalog_venue_ids[]', 'multiple="multiple" class="form-select" id="catalog_venue_ids"', 'value', 'text', $this->catalogFilters['venue_ids']);
        $this->catalogTypes = HTMLHelper::_('select.genericlist', $this->get('CatalogExportTypes'), 'catalog_type_ids[]', 'multiple="multiple" class="form-select" id="catalog_type_ids"', 'value', 'text', $this->catalogFilters['type_ids']);

        $this->categories = '<joomla-field-fancy-select class="jem-export-fancy-select" placeholder="' . htmlspecialchars(Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS'), ENT_QUOTES, 'UTF-8') . '">' . $categories . '</joomla-field-fancy-select>';
        $this->catalogVenues = '<joomla-field-fancy-select class="jem-export-fancy-select" placeholder="' . htmlspecialchars(Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS'), ENT_QUOTES, 'UTF-8') . '">' . $this->catalogVenues . '</joomla-field-fancy-select>';
        $this->catalogTypes = '<joomla-field-fancy-select class="jem-export-fancy-select" placeholder="' . htmlspecialchars(Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS'), ENT_QUOTES, 'UTF-8') . '">' . $this->catalogTypes . '</joomla-field-fancy-select>';
        $this->catalogFieldOptions = HTMLHelper::_('select.genericlist', $this->get('CatalogExportFieldOptions'), 'catalog_fields[]', 'multiple="multiple" size="12" class="form-select" id="catalog_fields"', 'value', 'text', $this->catalogSelectedFields);

        // add toolbar
        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_EXPORT'), 'tableexport');

        ToolbarHelper::back();
        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
        ToolBarHelper::help('export', true, 'https://www.joomlaeventmanager.net/documentation/backend/control-panel/export-data');
    }
}
?>

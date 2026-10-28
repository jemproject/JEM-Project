<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;

/** Render only the JEM module assigned to the active generated menu item. */
class JemViewModulepreview extends JemView
{
    public $module = null;
    public $pageTitle = '';

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $active = $app->getMenu()->getActive();

        if (!$active) {
            throw new \Exception(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 404);
        }

        $menuModuleId = (int) ($active->query['module_id'] ?? 0);
        $moduleId = $app->input->getInt('module_id', $menuModuleId);

        if (($active->query['option'] ?? '') !== 'com_jem'
            || ($active->query['view'] ?? '') !== 'modulepreview'
            || $moduleId < 1 || $moduleId !== $menuModuleId) {
            throw new \Exception(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 404);
        }

        $allowedModules = array(
            'mod_jem', 'mod_jem_banner', 'mod_jem_cal', 'mod_jem_jubilee',
            'mod_jem_map', 'mod_jem_teaser', 'mod_jem_types', 'mod_jem_wide',
        );
        foreach (ModuleHelper::getModules('jem-menu-preview') as $module) {
            if ((int) $module->id === $moduleId && in_array($module->module, $allowedModules, true)) {
                $this->module = $module;
                break;
            }
        }

        if (!$this->module) {
            throw new \Exception(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 404);
        }

        $this->params = $app->getParams();
        $this->pageTitle = (string) $active->title;
        $app->getDocument()->setTitle($active->title);
        parent::display($tpl);
    }
}

<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Categoryelement-View
 */
class JemViewCategoryelement extends JViewLegacy {

	public function display($tpl = null)
	{
		//initialise variables
		$app 		= Factory::getApplication();
		$document   = $app->getDocument();
		$db			= Factory::getContainer()->get('DatabaseDriver');
		$itemid 	= $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);

		// HTMLHelper::_('behavior.tooltip');
		// HTMLHelper::_('behavior.modal');

		//get vars
		$filter_order		= $app->getUserStateFromRequest('com_jem.categoryelement.filter_order', 'filter_order', 'c.lft', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.categoryelement.filter_order_Dir',	'filter_order_Dir',	'', 'word');
		$filter_state 		= $app->getUserStateFromRequest('com_jem.categoryelement.'.$itemid.'.filter_state', 'filter_state', '', 'string');
		$search 			= $app->getUserStateFromRequest('com_jem.categoryelement.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$search 			= $db->escape(trim(\Joomla\String\StringHelper::strtolower($search)));

		//prepare document
		$document->setTitle(Text::_('COM_JEM_SELECT_CATEGORY'));

		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

		// Get data from the model
		$rows = $this->get('Data');
		$pagination = $this->get('Pagination');

		//publish unpublished filter
		$lists['state'] = HTMLHelper::_('grid.state', $filter_state);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->lists 		= $lists;
		$this->filter_state = $filter_state;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;

		parent::display($tpl);
	}
}
?>

<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * View class for the JEM home screen
 *
 * @package JEM
 */
class JEMViewMain extends JViewLegacy {

	public function display($tpl = null)
	{
		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	= JFactory::getDocument();
		$user 		= JFactory::getUser();

		// Get data from the model
		$events 	= $this->get('EventsData');
		$venue 		= $this->get('VenuesData');
		$category 	= $this->get('CategoriesData');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		//assign vars to the template
		$this->events		= $events;
		$this->venue		= $venue;
		$this->category		= $category;
		$this->user			= $user;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add Toolbar
	*/
	protected function addToolbar()
	{
		// Load the category helper.
		require_once JPATH_COMPONENT.'/helpers/helper.php';

		JToolBarHelper::title(JText::_('COM_JEM_MAIN_TITLE'), 'home');

		if (JFactory::getUser()->authorise('core.manage')) {
			JToolBarHelper::preferences('com_jem');
			JToolbarHelper::divider();
		}

		JToolBarHelper::help('home', true);
	}

	/**
	 * Creates the buttons view
	 *
	 * @param string $link targeturl
	 * @param string $image path to image
	 * @param string $text image description
	 * @param boolean $modal 1 for loading in modal
	 */
	function quickiconButton($link, $image, $text, $modal = 0)
	{
		// Initialise variables
		$lang = JFactory::getLanguage();
		?>

		<div style="float:<?php echo ($lang->isRTL()) ? 'right' : 'left'; ?>;">
			<div class="icon">
				<?php if ($modal == 1) : ?>
					<?php JHtml::_('behavior.modal'); ?>
					<a href="<?php echo $link.'&amp;tmpl=component'; ?>" style="cursor:pointer" class="modal"
							rel="{handler: 'iframe', size: {x: 650, y: 400}}">
						<?php echo JHtml::_('image', 'com_jem/'.$image, $text, NULL, true); ?>
						<span><?php echo $text; ?></span>
					</a>
				<?php else : ?>
					<a href="<?php echo $link; ?>">
						<?php echo JHtml::_('image', 'com_jem/'.$image, $text, NULL, true); ?>
						<span><?php echo $text; ?></span>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php
	}
}
?>
<?php
/**
 * @version 1.9
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
 * @since 0.9
 */
class JEMViewJEM extends JViewLegacy {

	public function display($tpl = null)
	{
		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	=  JFactory::getDocument();
		$user 		=  JFactory::getUser();

		// Get data from the model
		$events      =  $this->get( 'Eventsdata');
		$venue       =  $this->get( 'Venuesdata');
		$category	 =  $this->get( 'Categoriesdata' );

		//add css and submenu to document
		$document->addStyleSheet(JURI::root(true).'/media/com_jem/css/backend.css');

	
		//assign vars to the template
		$this->events		= $events;
		$this->venue		= $venue;
		$this->category		= $category;
		$this->user			= $user;

		// add toolbar
		$this->addToolbar();
		
		parent::display($tpl);

	}
	
	
	
	/*
	 * Add Toolbar
	*/
	
	protected function addToolbar()
	{
		
		//build toolbar
		JToolBarHelper::title( JText::_( 'COM_JEM_JEM' ), 'home' );
		JToolBarHelper::help( 'intro', true );
		
		if (JFactory::getUser()->authorise('core.manage')) {
		JToolBarhelper::preferences('com_jem');
		}
		
		//Create Submenu
		require_once JPATH_COMPONENT . '/helpers/helper.php';

		
	}
	
	

	/**
	 * Creates the buttons view
	 *
	 * @param string $link targeturl
	 * @param string $image path to image
	 * @param string $text image description
	 * @param boolean $modal 1 for loading in modal
	 */
	function quickiconButton( $link, $image, $text, $modal = 0 )
	{
		//initialise variables
		$lang 		=  JFactory::getLanguage();
  		?>

		<div style="float:<?php echo ($lang->isRTL()) ? 'right' : 'left'; ?>;">
			<div class="icon">
				<?php
				if ($modal == 1) {
					JHTML::_('behavior.modal');
				?>
					<a href="<?php echo $link.'&amp;tmpl=component'; ?>" style="cursor:pointer" class="modal" rel="{handler: 'iframe', size: {x: 650, y: 400}}">
				<?php
				} else {
				?>
					<a href="<?php echo $link; ?>">
				<?php
				}

					echo JHTML::_('image', 'media/com_jem/images/'.$image, $text );
				?>
					<span><?php echo $text; ?></span>
				</a>
			</div>
		</div>
		<?php
	}


}  // end of class
?>
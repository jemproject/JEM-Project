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
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class for the JEM home screen
 *
 * @package JEM
 */
class JemViewMain extends JemAdminView
{

    public function display($tpl = null)
    {
        // Load pane behavior
        jimport('joomla.html.pane');

        //initialise variables
        $app      = Factory::getApplication();
        $document = $app->getDocument();
        $user     = JemFactory::getUser();

        // Get main model data
        $events   = $this->get('EventsData')   ?: new stdClass();
        $venue    = $this->get('VenuesData')   ?: new stdClass();
        $category = $this->get('CategoriesData') ?: new stdClass();

        // Load updatecheck model manually
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/updatecheck.php';
        $updateModel = new JemModelUpdatecheck(['ignore_request' => true]);
        $updatedata  = $updateModel->getUpdatedata();

        if ($updatedata === false) {
            $updatedata = new stdClass();
            $updatedata->failed  = 1;
            $updatedata->current = null;
        }

        // Load CSS
        $wa = $document->getWebAssetManager();
        if (!$wa->assetExists('style', 'jem.backend')) {
           $wa->registerStyle('jem.backend', 'com_jem/backend.css');
        }
        $wa->useStyle('jem.backend');

        // Assign variables to template
        $this->events     = $events;
        $this->venue      = $venue;
        $this->category   = $category;
        $this->user       = $user;
        $this->updatedata = $updatedata;

        // Add toolbar
        $this->addToolbar();

        // Render template
        parent::display($tpl);
    }

    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_MAIN_TITLE'), 'home');

        if (JemFactory::getUser()->authorise('core.manage', 'com_jem')) {
            ToolbarHelper::preferences('com_jem');
        }

        ToolBarHelper::divider();
        ToolBarHelper::help('listevents', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/control-panel');
    }

    /**
     * Creates the buttons view
     *
     * @param  string  $link  targeturl
     * @param  string  $image path to image
     * @param  string  $text  image description
     * @param  boolean $modal 1 for loading in modal
     */
    protected function quickiconButton($link, $image, $text, $modal = 0)
    {
        // Initialise variables
        $lang = Factory::getApplication()->getLanguage();
        ?>
        <div style="float:<?php echo ($lang->isRTL()) ? 'right' : 'left'; ?>;">
            <div class="icon">
                <?php if ($modal == 1) : ?>
                    <a href="<?php echo $link.'&amp;tmpl=component'; ?>" style="cursor:pointer" class="modal"
                            rel="{handler: 'iframe', size: {x: 650, y: 400}}">
                        <?php echo HTMLHelper::_('image', 'com_jem/'.$image, $text, NULL, true); ?>
                        <span><?php echo $text; ?></span>
                    </a>
                <?php else : ?>
                    <a href="<?php echo $link; ?>">
                        <?php echo HTMLHelper::_('image', 'com_jem/'.$image, $text, NULL, true); ?>
                        <span><?php echo $text; ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
?>

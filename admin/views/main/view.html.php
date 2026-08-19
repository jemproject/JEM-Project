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
        //initialise variables
        $app      = Factory::getApplication();
        $user     = JemFactory::getUser();

        // Load updatecheck model manually
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/updatecheck.php';
        $updateModel = new JemModelUpdatecheck(['ignore_request' => true]);
        $updatedata  = $updateModel->getUpdatedata();

        if ($updatedata === false) {
            $updatedata = new stdClass();
            $updatedata->failed  = 1;
            $updatedata->current = null;
        }

        $this->user       = $user;
        $this->updatedata = $updatedata;
        $this->featurePolicy = JemFeaturePolicy::current();
        $this->operatingProfileConfigured = (int) JemConfig::getInstance()
            ->toRegistry()
            ->get('operating_profile_configured', 0);

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

        if (JemHelperBackend::canManage('core.options')) {
            ToolbarHelper::preferences('com_jem');
        }

        ToolBarHelper::divider();
        ToolBarHelper::help('listevents', true, 'https://www.joomlaeventmanager.net/documentation/backend/control-panel');
    }

    /**
     * Creates the buttons view
     *
     * @param  string      $link     targeturl
     * @param  string      $image    path to image
     * @param  string      $text     image description
     * @param  boolean     $modal    1 for loading in modal
     * @param  string|null $addLink  optional "add new" target url, shown as a small overlay badge
     * @param  string|null $addText  tooltip text for the add badge (falls back to $text)
     */
    protected function quickiconButton($link, $image, $text, $modal = 0, $addLink = null, $addText = null)
    {
        // Initialise variables
        $lang = Factory::getApplication()->getLanguage();
        ?>
        <div style="float:<?php echo ($lang->isRTL()) ? 'right' : 'left'; ?>;">
            <div class="icon">
                <?php if ($addLink) : ?>
                    <a href="<?php echo $addLink; ?>" class="jem-wei-add" title="<?php echo $addText ?: $text; ?>">
                        <span aria-hidden="true">+</span>
                    </a>
                <?php endif; ?>
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

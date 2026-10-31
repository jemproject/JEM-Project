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
    protected $legacyModuleOverrideCount = 0;
    protected $jedReviewAvailable = false;
    protected $communityCardDismissed = ['donate' => false, 'jed' => false];

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

        require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/jedreview.php';
        if (JemJedReview::allowed($app, JemAclMigrationStatus::pending())) {
            try {
                foreach (array_keys(JemJedReview::cards()) as $card) {
                    $this->communityCardDismissed[$card] = JemJedReview::dismissed(
                        Factory::getContainer()->get('DatabaseDriver'),
                        (int) $app->getIdentity()->id,
                        $card
                    );
                }
                $this->jedReviewAvailable = true;
                HTMLHelper::_('stylesheet', 'com_jem/community-cards.css', ['version' => 'auto', 'relative' => true]);
                HTMLHelper::_('script', 'com_jem/jed-review.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);
            } catch (Throwable $exception) {
                // A community invitation must never prevent access to the dashboard.
                \Joomla\CMS\Log\Log::add('JED invitation preference could not be loaded.', \Joomla\CMS\Log\Log::WARNING, 'com_jem');
            }
        }

        if (JemHelperBackend::canManage('jem.tools.manage')) {
            require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/cssmanager.php';
            $cssManagerModel = new JemModelCssmanager(array('ignore_request' => true));
            $this->legacyModuleOverrideCount = count($cssManagerModel->getLegacyModuleCssOverrides());
        }

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

    /**
     * Render a POST action inside the control panel's token-protected form.
     *
     * The containing application-form owns the task button and CSRF token.
     * Confirm sample imports on activation, including keyboard activation,
     * without introducing an invalid nested form.
     *
     * @param  string  $task   Controller task.
     * @param  string  $image  Path to image.
     * @param  string  $text   Image description.
     *
     * @return void
     */
    protected function quickiconPostButton($task, $image, $text)
    {
        $lang = Factory::getApplication()->getLanguage();
        $confirmation = $task === 'sampledata.load'
            ? ' onclick="return window.confirm(' . htmlspecialchars(
                json_encode(Text::_('COM_JEM_SAMPLEDATA_CONFIRM'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                ENT_QUOTES,
                'UTF-8'
            ) . ');"'
            : '';
        ?>
        <div style="float:<?php echo ($lang->isRTL()) ? 'right' : 'left'; ?>;">
            <div class="icon">
                <button type="submit" name="task" value="<?php echo htmlspecialchars($task, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $confirmation; ?>>
                    <?php echo HTMLHelper::_('image', 'com_jem/'.$image, $text, NULL, true); ?>
                    <span><?php echo $text; ?></span>
                </button>
            </div>
        </div>
        <?php
    }
}
?>

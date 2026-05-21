<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;

/**
 * JemView class with JEM specific extensions
 *
 * @package JEM
 */
class JemAdminView extends HtmlView
{
    public $access = null;
    public $app = null;
    public $attachmentfields = null;
    public $attachmentsPossible = null;
    public $canDo = null;
    public $categories = null;
    public $category = null;
    public $cateventsfields = null;
    public $catfields = null;
    public $config = null;
    public $document = null;
    public $event = null;
    public $eventfields = null;
    public $eventlistTables = null;
    public $eventlistVersion = null;
    public $events = null;
    public $existingJemData = null;
    public $f_levels = null;
    public $helpsearch = null;
    public $jemsettings = null;
    public $jemTables = null;
    public $langTag = null;
    public $lists = null;
    public $pagination = null;
    public $prefixToShow = null;
    public $progress = null;
    public $row = null;
    public $rows = null;
    public $settings = null;
    public $sidebar = null;
    public $task = null;
    public $toc = null;
    public $totalcats = null;
    public $typefields = null;
    public $updatedata = null;
    public $user = null;
    public $venue = null;
    public $venuefields = null;

    /**
     * Display the view
     */
    public function display($tpl = null)
    {
        if (JemSidebarHelper::getEntries()) {
            $this->sidebar = JemSidebarHelper::render();
        }

        parent::display($tpl);
    }
}

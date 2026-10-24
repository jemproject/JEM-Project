<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;

require_once JPATH_SITE . '/components/com_jem/classes/feed.class.php';

/**
 * Category feed.
 */
class JemViewCategory extends HtmlView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        JemFeed::limitModel($this->getModel(), $app);
        JemFeed::addEvents($app->getDocument(), $this->get('Items'));
    }
}

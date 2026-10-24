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
 * Venue feed.
 */
class JemViewVenue extends HtmlView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $venue = $this->get('Venue');

        if (empty($venue) || empty($venue->user_has_access_venue)) {
            return;
        }

        JemFeed::limitModel($this->getModel(), $app);
        JemFeed::addEvents($app->getDocument(), $this->get('Items'));
    }
}

<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// Only expose the AJAX operations used by the event editor.
// Each method retains its existing permission, profile and CSRF checks.
if (!in_array(
    strtolower(Factory::getApplication()->input->getCmd('task', '')),
    ['venueconfigurations', 'repairimage'],
    true
)) {
    throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

require_once __DIR__ . '/event.php';

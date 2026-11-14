<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// Only expose the admission lookup through this legacy JSON controller route.
// The controller method retains its attendee permission and pricing profile checks.
if (strtolower(Factory::getApplication()->input->getCmd('task', '')) !== 'pricingoptions') {
    throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

require_once __DIR__ . '/attendee.php';

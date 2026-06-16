<?php
/**
 * @package    JEM
 * @subpackage mod_jem_types
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

require_once __DIR__ . '/helper.php';

require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
require_once JPATH_SITE . '/components/com_jem/helpers/route.php';
require_once JPATH_SITE . '/components/com_jem/classes/output.class.php';
require_once JPATH_SITE . '/components/com_jem/factory.php';

Factory::getApplication()->getLanguage()->load('com_jem', JPATH_SITE . '/components/com_jem');
Factory::getApplication()->getLanguage()->load('mod_jem_types', __DIR__);

JemHelper::loadCss('jem');
JemHelper::loadIconFont();

$mode = $params->get('display_mode', 'summary');

if ($mode === 'topn') {
    $data = ModJemTypesHelper::getTopNByType($params);
} else {
    $data = ModJemTypesHelper::getTypeSummary($params);
}

if (empty($data) && !$params->get('show_when_empty', 0)) {
    return;
}

require ModuleHelper::getLayoutPath('mod_jem_types', $params->get('layout', 'default'));
JemHelper::loadModuleUserCss();

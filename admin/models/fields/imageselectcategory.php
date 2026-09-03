<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

// Make the shared image field available without registering every frontend
// field in the administrator form loader.
require_once JPATH_SITE . '/components/com_jem/models/fields/imageselectcategory.php';

<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

include is_file(dirname(__DIR__) . '/timetable.php')
    ? dirname(__DIR__) . '/timetable.php'
    : JPATH_SITE . '/components/com_jem/views/day/tmpl/timetable.php';

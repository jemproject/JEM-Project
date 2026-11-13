<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require is_file(dirname(__DIR__) . '/choosearticle.php')
    ? dirname(__DIR__) . '/choosearticle.php'
    : JPATH_SITE . '/components/com_jem/views/editevent/tmpl/choosearticle.php';

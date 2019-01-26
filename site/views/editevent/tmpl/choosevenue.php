<?php
/**
 * @version 2.3.0-dev2
 * @package JEM
 * @copyright (C) 2013-2018 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

$jemsettings = JemHelper::config();

if ($jemsettings->layoutstyle == 1) {
  echo $this->loadTemplate('responsive');
} else {
  echo $this->loadTemplate('legacy');      
}
?>
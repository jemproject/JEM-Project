<?php
/**
 * @version 2.2.1
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

if ($this->jemsettings->layoutstyle == 1) {
  echo $this->loadTemplate('responsive');
} else {
  echo $this->loadTemplate('legacy');      
}
?>
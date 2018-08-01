<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2005-2009 Christoph Lukes
 * @copyright (C) 2013-2018 joomlaeventmanager.net
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

if ($this->jemsettings->layoutstyle == 1) {
  echo $this->loadTemplate('responsive');
} else {
  echo $this->loadTemplate('legacy');      
}
?>

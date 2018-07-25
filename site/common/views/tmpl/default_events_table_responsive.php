<?php
/**
 * @version 2.2.3-dev1
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<?php 
function jem_common_string_contains($masterstring, $string) {
  if (strpos($masterstring, $string) !== false) {
    return true;
  } else {
    return false;
  }
}

if (empty($this->jemsettings->tablewidth)) :
  echo $this->loadTemplate('jem_eventslist'); // The new layout
else :
  echo $this->loadTemplate('jem_eventslist_small'); // Similar to the old table-layout
endif;
?>

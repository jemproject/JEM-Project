<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

if (empty($this->jemsettings->tablewidth)) :
  echo $this->loadTemplate('jem_eventslist'); // The new layout
else :
  echo $this->loadTemplate('jem_eventslist_small'); // Similar to the old table-layout
endif;

echo JemOutput::lightbox();
?>

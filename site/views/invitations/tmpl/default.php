<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;
use Joomla\CMS\Factory;

$this->params = Factory::getApplication()->getParams();
if (!JemHelper::isActiveMenuView($this->getName())) {
    $this->params = clone $this->params;
    $this->params->set('showintrotext', 0);
    $this->params->set('showfootertext', 0);
}
if ($this->params->get('showintrotext')) {
    echo '<div class="description no_space floattext">' . $this->params->get('introtext') . '</div>';
}
require JPATH_SITE . '/components/com_jem/common/views/tmpl/invitations.php';
if ($this->params->get('showfootertext')) {
    echo '<div class="description no_space floattext">' . $this->params->get('footertext') . '</div>';
}

<?php
/** JSON dispatch restricted to reviewed frontend relocation operations. */
defined('_JEXEC') or die;
if (!in_array(strtolower(Joomla\CMS\Factory::getApplication()->input->getCmd('task')), ['relocationpreview', 'relocationconfirm'], true)) {
    throw new RuntimeException('JERROR_ALERTNOAUTHOR', 403);
}
require_once __DIR__ . '/editattendee.php';

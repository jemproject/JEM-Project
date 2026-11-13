<?php
defined('_JEXEC') or die;

require is_file(dirname(__DIR__) . '/default_capacityareas.php')
    ? dirname(__DIR__) . '/default_capacityareas.php'
    : JPATH_SITE . '/components/com_jem/views/event/tmpl/default_capacityareas.php';

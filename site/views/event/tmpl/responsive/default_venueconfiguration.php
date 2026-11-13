<?php
defined('_JEXEC') or die;

require is_file(dirname(__DIR__) . '/default_venueconfiguration.php')
    ? dirname(__DIR__) . '/default_venueconfiguration.php'
    : JPATH_SITE . '/components/com_jem/views/event/tmpl/default_venueconfiguration.php';

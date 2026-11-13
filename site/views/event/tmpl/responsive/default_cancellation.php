<?php
defined('_JEXEC') or die;
require is_file(dirname(__DIR__) . '/default_cancellation.php')
    ? dirname(__DIR__) . '/default_cancellation.php'
    : JPATH_SITE . '/components/com_jem/views/event/tmpl/default_cancellation.php';

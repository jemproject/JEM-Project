<?php
/** Shared area cancellation controls for the responsive event layout. */
defined('_JEXEC') or die;
require is_file(dirname(__DIR__) . '/default_cancellationareas.php')
    ? dirname(__DIR__) . '/default_cancellationareas.php'
    : JPATH_SITE . '/components/com_jem/views/event/tmpl/default_cancellationareas.php';

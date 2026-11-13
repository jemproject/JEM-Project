<?php
/** Shared priced-reservation cancellation form for the responsive layout. */
defined('_JEXEC') or die;
require is_file(dirname(__DIR__) . '/default_pricedregform.php')
    ? dirname(__DIR__) . '/default_pricedregform.php'
    : JPATH_SITE . '/components/com_jem/views/event/tmpl/default_pricedregform.php';

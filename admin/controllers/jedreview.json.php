<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

// Joomla's legacy dispatcher selects a format-specific controller for JSON.
// Reuse the same action so POST, CSRF and ACL checks remain identical.
require_once __DIR__ . '/jedreview.php';

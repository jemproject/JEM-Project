<?php
/**
 * @package    JEM My Attending for CB
 * @version    2.8.0 (for JEM 4 & CB v2.8)
 * @author     JEM Community
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 */

defined('_JEXEC') or die;

function plug_jemmyattending_cb_install()
{
	# There maybe an older version with different name installed which will conflict.
	# So search and eliminate it.
	global $_CB_framework, $_CB_database;

	$element = 'jemmyattending_cb';
	$old_folder_name = 'plug_cbjemmyattending';
	$new_folder_name = 'plug_jemmyattending';

	# Check to see if plugin already exists in db
	$_CB_database->setQuery( 'SELECT id FROM #__comprofiler_plugin WHERE type = "user" AND element = "'.$element.'" AND folder = "'.$old_folder_name.'"' );
	if (!$_CB_database->query()) {
		return 'Plugin custom installer error (1)';
	}
	if (!($oldid = $_CB_database->loadResult())) {
		return 'Plugin custom installer: Ok, no old plugin found.';
	}
	$oldrow = new CB\Database\Table\PluginTable();
	$oldrow->load((int)$oldid);

	$_CB_database->setQuery( 'SELECT id FROM #__comprofiler_plugin WHERE type = "user" AND element = "'.$element.'" AND folder = "'.$new_folder_name.'"' );
	if (!$_CB_database->query()) {
		return 'Plugin custom installer error (2)';
	}
	if (!($newid = $_CB_database->loadResult())) {
		return 'Plugin custom installer error (3)';
	}
	$newrow = new CB\Database\Table\PluginTable();
	$newrow->load((int)$newid);

	# copy some settings from old to new entry
	$fields = array('ordering', 'published', 'access', 'viewaccesslevel');
	foreach ($fields as $field) {
		if (isset($oldrow->$field)) {
			$newrow->$field = $oldrow->$field;
		}
	}

	if (!$newrow->store()) {
		return 'Plugin custom installer error (4)';
	}

	# remove old entry from plugin table
	$oldrow->delete();

	# remove the old files
	$old_path = $_CB_framework->getCfg('absolute_path') . '/components/com_comprofiler/plugin/user/' . $old_folder_name;
	if (file_exists($old_path)) {
		# remove old plugin files
		$adminFS = cbAdminFileSystem::getInstance();
		$result  = $adminFS->deldir($old_path.'/');
		if (!$result) {
			return 'Plugin custom installer: Can\'t delete folder ' . $old_folder_name;
		}
	}

	return '';
}
?>
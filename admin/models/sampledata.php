<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Archive\Archive;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

// TODO: Improve error handling

/**
 * Sampledata Model
 */
class JemModelSampledata extends BaseDatabaseModel
{

    /**
     * Sample data directory
     *
     * @var string
     */
    private $sampleDataDir = null;

    /**
     * Files data array
     *
     * @var array
     */
    private $filelist = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        if ($this->checkForJemData()) {
            return false;
        }

        $this->sampleDataDir = JPATH_COMPONENT_ADMINISTRATOR . '/assets/';
        $this->filelist = $this->unpack();
    }

    /**
     * Process sampledata
     *
     * @return boolean True on success
     */
    public function loadData()
    {
        if ($this->checkForJemData()) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_SAMPLEDATA_DATA_ALREADY_INSTALLED'), 'warning');
            return false;
        }

        $this->ensureSampleDataSchema();

        $scriptfile = $this->sampleDataDir . 'sampledata.sql';
        // load sql file
        if (!($buffer = file_get_contents($scriptfile))) {
            return false;
        }

        // extract queries out of sql file
        $queries = $this->splitSql($buffer);

        // Process queries
        foreach ($queries as $query) {
            $query = trim($query);
            if ($query != '' && $query[0] != '#') {
                $this->_db->setQuery($query);
                $this->_db->execute();
            }
        }

        // Assign the current manager as creator for all sample records.
        $this->assignCurrentUserId();

        // move images in proper directory
        $this->moveImages();

        // move attachments in proper directory
        $this->moveAttachments();

        // delete temporary extraction folder
        if (!$this->deleteTmpFolder()) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_SAMPLEDATA_UNABLE_TO_DELETE_TMP_FOLDER'), 'warning');
        }

        return true;
    }

    /**
     * Ensure tables touched by the demo SQL have the current 4.5 sample-data columns.
     *
     * This keeps sample data loading resilient on upgraded sites where Joomla may
     * already have the old 4.4.x types or attachments schema.
     *
     * @return void
     */
    private function ensureSampleDataSchema()
    {
        $this->ensureTypesSchema();
        $this->ensureAttachmentsSchema();
        $this->ensureLinksSchema();
    }

    /**
     * @return void
     */
    private function ensureTypesSchema()
    {
        $columns = $this->getTableColumns('#__jem_types');

        if (empty($columns)) {
            return;
        }

        if (isset($columns['type']) && !isset($columns['entity'])) {
            $this->executeSchemaQuery("ALTER TABLE `#__jem_types` CHANGE `type` `entity` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Event, 2=Category, 3=Venue'");
            unset($columns['type']);
            $columns['entity'] = true;
        }

        $definitions = array(
            'alias'                 => "`alias` VARCHAR(100) NOT NULL DEFAULT '' AFTER `name`",
            'description'           => "`description` TEXT DEFAULT NULL AFTER `alias`",
            'base_language'         => "`base_language` CHAR(7) NOT NULL DEFAULT '' AFTER `description`",
            'translation_languages' => "`translation_languages` VARCHAR(255) DEFAULT NULL AFTER `base_language`",
            'translations'          => "`translations` MEDIUMTEXT DEFAULT NULL AFTER `translation_languages`",
            'entity'                => "`entity` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Event, 2=Category, 3=Venue' AFTER `translations`",
            'color'                 => "`color` VARCHAR(7) DEFAULT NULL AFTER `icon`",
            'published'             => "`published` TINYINT(1) NOT NULL DEFAULT 1 AFTER `color`",
            'ordering'              => "`ordering` INT(11) NOT NULL DEFAULT 0 AFTER `published`",
            'access'                => "`access` INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `ordering`",
            'language'              => "`language` CHAR(7) NOT NULL DEFAULT '*' AFTER `access`",
            'checked_out'           => "`checked_out` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `language`",
            'checked_out_time'      => "`checked_out_time` DATETIME NULL DEFAULT NULL AFTER `checked_out`",
            'created'               => "`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `checked_out_time`",
            'created_by'            => "`created_by` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `created`",
            'modified'              => "`modified` DATETIME NULL DEFAULT NULL AFTER `created_by`",
            'modified_by'           => "`modified_by` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `modified`",
            'attribs'               => "`attribs` TEXT DEFAULT NULL AFTER `modified_by`",
        );

        foreach ($definitions as $name => $definition) {
            if (!isset($columns[$name])) {
                $this->executeSchemaQuery("ALTER TABLE `#__jem_types` ADD COLUMN " . $definition);
                $columns[$name] = true;
            }
        }
    }

    /**
     * @return void
     */
    private function ensureAttachmentsSchema()
    {
        $columns = $this->getTableColumns('#__jem_attachments');

        if (empty($columns)) {
            return;
        }

        if (isset($columns['added']) && !isset($columns['created'])) {
            $this->executeSchemaQuery("ALTER TABLE `#__jem_attachments` CHANGE `added` `created` DATETIME NULL DEFAULT NULL");
            unset($columns['added']);
            $columns['created'] = true;
        }

        if (isset($columns['added_by']) && !isset($columns['created_by'])) {
            $this->executeSchemaQuery("ALTER TABLE `#__jem_attachments` CHANGE `added_by` `created_by` INT(11) NOT NULL DEFAULT 0");
            unset($columns['added_by']);
            $columns['created_by'] = true;
        }

        if (!isset($columns['created'])) {
            $this->executeSchemaQuery("ALTER TABLE `#__jem_attachments` ADD COLUMN `created` DATETIME NULL DEFAULT NULL AFTER `ordering`");
        }

        if (!isset($columns['created_by'])) {
            $this->executeSchemaQuery("ALTER TABLE `#__jem_attachments` ADD COLUMN `created_by` INT(11) NOT NULL DEFAULT 0 AFTER `created`");
        }
    }

    /**
     * @return void
     */
    private function ensureLinksSchema()
    {
        $columns = $this->getTableColumns('#__jem_links');

        if (empty($columns)) {
            return;
        }

        if (!isset($columns['created'])) {
            $this->executeSchemaQuery("ALTER TABLE `#__jem_links` ADD COLUMN `created` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `state`");
        }

        if (!isset($columns['created_by'])) {
            $this->executeSchemaQuery("ALTER TABLE `#__jem_links` ADD COLUMN `created_by` INT(11) NOT NULL DEFAULT 0 AFTER `created`");
        }
    }

    /**
     * @param  string  $table
     * @return array
     */
    private function getTableColumns($table)
    {
        try {
            return $this->_db->getTableColumns($table, false);
        } catch (\Exception $e) {
            return array();
        }
    }

    /**
     * @param  string  $query
     * @return void
     */
    private function executeSchemaQuery($query)
    {
        $this->_db->setQuery($query);
        $this->_db->execute();
    }

    /**
     * Unpack archive and build array of files
     *
     * @return boolean|array
     */
    private function unpack()
    {
        $archive = $this->sampleDataDir . 'sampledata.zip';

        // Temporary folder to extract the archive into
        $tmpdir = uniqid('sample_');

        // Clean the paths to use for archive extraction
        $extractdir = Path::clean(JPATH_ROOT . '/tmp/' . $tmpdir);
        $archive = Path::clean($archive);

        // extract archive

        try {
            $archiveObj = new Archive(array('tmp_path' => Factory::getApplication()->get('tmp_path')));
            $result = $archiveObj->extract($archive, $extractdir);
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_SAMPLEDATA_UNABLE_TO_EXTRACT_ARCHIVE'), 'warning');
            return false;
        }

        if ($result === false) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_SAMPLEDATA_UNABLE_TO_EXTRACT_ARCHIVE'), 'warning');
            return false;
        }

        // return the files found in the extract folder and also folder name
        $files = array();

        if ($handle = opendir($extractdir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    $files[] = $file;
                    continue;
                }
            }
            closedir($handle);
        }
        $filelist['files'] = $files;
        $filelist['folder'] = $extractdir;

        return $filelist;
    }

    /**
     * Split sql to single queries
     *
     * @return array
     */
    private function splitSql($sql)
    {
        $sql = trim($sql);
        $sql = preg_replace("/\n\#[^\n]*/", '', "\n" . $sql);
        $buffer = array();
        $ret = array();
        $in_string = false;

        for ($i = 0; $i < strlen($sql) - 1; $i++) {
            if ($sql[$i] == ";" && !$in_string) {
                $ret[] = substr($sql, 0, $i);
                $sql = substr($sql, $i + 1);
                $i = 0;
            }

            if ($in_string && ($sql[$i] == $in_string) && $buffer[1] != "\\") {
                $in_string = false;
            }
            elseif (!$in_string && ($sql[$i] == '"' || $sql[$i] == "'") && (!isset($buffer[0]) || $buffer[0] != "\\")) {
                $in_string = $sql[$i];
            }
            if (isset($buffer[1])) {
                $buffer[0] = $buffer[1];
            }
            $buffer[1] = $sql[$i];
        }

        if (!empty($sql)) {
            $ret[] = $sql;
        }
        return ($ret);
    }

    /**
     * Copy images into the venues/events folder
     *
     * @return boolean True on success
     */
    private function moveImages()
    {
        $imagebase = JPATH_ROOT . '/images/jem';

        foreach ($this->filelist['files'] as $file) {
            if (strpos($file, 'attachment-') === 0) {
                continue;
            }

            $subDirectory = "/";
            if (strpos($file, "event") !== false) {
                $subDirectory .= "events/";
            }
            elseif (strpos($file, "venue") !== false) {
                $subDirectory .= "venues/";
            }
            elseif (strpos($file, "category") !== false) {
                $subDirectory .= "categories/";
            }
            else {
                // Nothing matched. Skip this file
                continue;
            }
            if (strpos($file, "thumb") !== false) {
                $subDirectory .= "small/";
            }

            // Use native PHP copy function instead of File::copy
            copy($this->filelist['folder'] . '/' . $file, $imagebase . $subDirectory . $file);
        }
        return true;
    }

    /**
     * Copy sample attachments into the configured attachments folder.
     *
     * Files in sampledata.zip must be named attachment-event1-filename.ext or
     * attachment-venue1-filename.ext. The prefix defines the attachment object,
     * and only filename.ext is stored/copied inside that object folder.
     *
     * @return boolean True on success
     */
    private function moveAttachments()
    {
        $jemsettings = JemHelper::config();
        $attachmentBase = Path::clean(JPATH_ROOT . '/' . $jemsettings->attachments_path);

        foreach ($this->filelist['files'] as $file) {
            if (!preg_match('/^attachment-((?:event|venue)\d+)-(.+)$/', $file, $matches)) {
                continue;
            }

            $object = $matches[1];
            $filename = File::makeSafe($matches[2]);

            if ($filename === '') {
                continue;
            }

            $destination = Path::clean($attachmentBase . '/' . $object);

            if (!Folder::exists($destination)) {
                Folder::create($destination);
            }

            File::copy($this->filelist['folder'] . '/' . $file, $destination . '/' . $filename);
        }

        return true;
    }

    /**
     * Delete temporary folder
     *
     * @return boolean True on success
     */
    private function deleteTmpFolder()
    {
        if ($this->filelist['folder']) {
            // Use native PHP function to recursively delete directory
            if (!$this->removeDirectory($this->filelist['folder'])) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Recursively remove directory using native PHP functions
     *
     * @param string $dir Directory path
     * @return boolean True on success
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dir);
    }

    /**
     * Checks if JEM data already exists
     *
     * @return boolean True if data exists
     */
    private function checkForJemData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select("id, catname");
        $query->from('#__jem_categories');
        $query->where('alias NOT LIKE "root"');
        $db->setQuery($query);
        $result = $db->loadObjectList();

        if ($result == null) {
            return false;
        }

        // Detect if JEM is installed by default (only Uncategorised category without events)
        if(count($result) == 1 && $result[0]->catname == 'Uncategorised') {
            // Check if category has any events
            $query = $db->getQuery(true);
            $query->select("id");
            $query->from('#__jem_cats_event_relations');
            $query->where('catid=' . $db->quote($result[0]->id));
            $db->setQuery($query);
            $events = $db->loadObjectList();

            if(empty($events)){
                //Delete Uncategorised category before load demo data
                $query = $db->getQuery(true);
                $query->delete('#__jem_categories');
                $query->where('catname=' . $db->quote($result[0]->catname));
                $db->setQuery($query);
                $db->execute();

                return false;
            }

            return true;
        }

        return true;
    }

    /**
     * Assign current user id to sample records.
     *
     * @return boolean True if data exists
     */
    private function assignCurrentUserId()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $user = JemFactory::getUser();
        $userId = (int) $user->get('id');

        if (!$userId || !$user->authorise('core.manage', 'com_jem')) {
            return false;
        }

        foreach (array('#__jem_events', '#__jem_venues', '#__jem_types', '#__jem_links', '#__jem_attachments') as $table) {
            $query = $db->getQuery(true);
            $query->update($table);
            $query->set('created_by = ' . $db->quote($userId));
            $query->where(array('created_by = 62'));
            $db->setQuery($query);
            $db->execute();
        }

        return true;
    }
}
?>
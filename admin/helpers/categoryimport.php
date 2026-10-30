<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Table\Asset;
use Joomla\String\StringHelper;

/** Finish ID-preserving CSV imports without leaving categories without ACL assets. */
final class JemCategoryImportHelper
{
    public static function begin($db): void
    {
        $tables = array($db->replacePrefix('#__jem_categories'), $db->replacePrefix('#__assets'));
        $engines = $db->setQuery(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('
            . implode(',', array_map(array($db, 'quote'), $tables)) . ')'
        )->loadColumn();

        if (count($engines) !== 2 || array_filter($engines, static fn ($engine) => strcasecmp($engine, 'InnoDB') !== 0)) {
            throw new RuntimeException('Category import requires InnoDB category and asset tables so failures can be rolled back.');
        }

        // Protect coordinate ranges and insert gaps as well as existing rows.
        $db->setQuery('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE')->execute();
        $db->transactionStart();
        try {
            $db->setQuery('SELECT id FROM #__assets ORDER BY id FOR UPDATE')->loadColumn();
            $db->setQuery('SELECT id FROM #__jem_categories ORDER BY id FOR UPDATE')->loadColumn();
        } catch (Throwable $error) {
            $db->transactionRollback();
            throw $error;
        }
    }

    /** Called only inside begin()'s transaction, after every CSV row has been stored. */
    public static function complete($table, $db, array $ids): void
    {
        if (!$ids) {
            return;
        }

        $rootId = (int) $table->getRootId();
        $rows = $db->setQuery('SELECT id, parent_id FROM #__jem_categories')->loadAssocList('id');
        $resolved = array($rootId => true);

        // Reject orphan/cyclic imports before calling Joomla's recursive rebuild.
        foreach ($rows as $id => $row) {
            $path = array();
            while (!isset($resolved[$id])) {
                if (!isset($rows[$id]) || isset($path[$id])) {
                    throw new RuntimeException('Invalid category parent chain at category #' . (int) $id . '.');
                }
                $path[$id] = true;
                $id = (int) $rows[$id]['parent_id'];
            }
            $resolved += $path;
        }

        if ($table->rebuild() === false) {
            throw new RuntimeException($table->getError() ?: 'The imported category tree could not be rebuilt.');
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $categories = $db->setQuery('SELECT id, parent_id, catname FROM #__jem_categories WHERE id IN ('
            . implode(',', $ids) . ') ORDER BY lft')->loadObjectList();

        foreach ($categories as $category) {
            $parentName = (int) $category->parent_id === $rootId
                ? 'com_jem'
                : 'com_jem.category.' . (int) $category->parent_id;
            $parent = new Asset($db);
            if (!$parent->loadByName($parentName)) {
                throw new RuntimeException('Missing parent asset ' . $parentName . ' for category #' . (int) $category->id . '.');
            }

            $asset = new JemCategoryImportAsset($db);
            $name = 'com_jem.category.' . (int) $category->id;
            $asset->loadByName($name);
            if ($asset->getError()) {
                throw new RuntimeException((string) $asset->getError());
            }
            if (!$asset->id || (int) $asset->parent_id !== (int) $parent->id) {
                $asset->setLocation((int) $parent->id, 'last-child');
            }
            $asset->parent_id = (int) $parent->id;
            $asset->name = $name;
            $asset->title = StringHelper::substr((string) $category->catname, 0, 100);
            // Loaded rules are deliberately retained; new assets inherit their parent.
            if (!$asset->check() || !$asset->store()) {
                throw new RuntimeException($asset->getError() ?: 'The imported category asset could not be saved.');
            }
            $db->setQuery('UPDATE #__jem_categories SET asset_id = ' . (int) $asset->id
                . ' WHERE id = ' . (int) $category->id)->execute();
        }
    }
}

/**
 * Joomla's normal asset insertion/move algorithms, under the caller's row locks.
 * LOCK TABLES / UNLOCK TABLES would implicitly commit the MySQL transaction.
 */
final class JemCategoryImportAsset extends Asset
{
    protected function _lock()
    {
        return true;
    }

    protected function _unlock()
    {
        return true;
    }
}


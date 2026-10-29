<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Table\Asset;

/**
 * Differential, bounded ACL migration. Never rebuilds Joomla's global tree.
 * New forests use transactional DML; existing assets retain their IDs and rules.
 */
class JemAclAssetMigration
{
    private $db;
    private float $deadline;
    private int $batchSize;

    public function __construct($db, float $seconds = 8.0, int $batchSize = 100)
    {
        $this->db = $db;
        $this->deadline = microtime(true) + max(0.0, $seconds);
        $this->batchSize = max(1, min(250, $batchSize));
    }

    /** Return pending IDs, including rows that could not be repaired. */
    public function repair(string $type): array
    {
        $table = $this->table($type);
        $transactional = $this->isTransactional($table);
        do {
            $rows = $this->rows($type);
            $assets = $this->assets();
            self::validateForJemMigration($assets);
            $names = array_column($assets, null, 'name');
            $component = $names['com_jem'] ?? null;
            if (!$component) {
                throw new RuntimeException('The com_jem ACL asset is missing.');
            }
            $pending = $this->pending($rows, $names, $type);
            if (!$pending || microtime(true) >= $this->deadline) {
                Access::clearStatics();
                return $pending;
            }

            $row = $rows[$pending[0]];
            $parentName = $this->parentName($row, $type);
            if (!isset($names[$parentName])) {
                throw new RuntimeException('A category parent has not been migrated; its children cannot inherit from the component instead.');
            }
            $name = 'com_jem.' . $type . '.' . $row['id'];
            if (!isset($names[$name]) && $transactional) {
                $this->insertForest($type, (int) $names[$parentName]['id']);
            } else {
                // Only changed/missing records reach the Joomla writer.
                $asset = new Asset($this->db);
                $exists = $asset->loadByName($name);
                $parentId = (int) $names[$parentName]['id'];
                if (!$exists) {
                    $asset->name = $name;
                    $asset->rules = '{}';
                }
                if (!$exists || (int) $asset->parent_id !== $parentId || (string) $asset->title !== $row['title']) {
                    $asset->title = $row['title'];
                    if (!$exists || (int) $asset->parent_id !== $parentId) {
                        $asset->setLocation($parentId, 'last-child');
                    }
                    if (!$asset->check() || !$asset->store()) {
                        throw new RuntimeException('Unable to repair ACL asset ' . $name . ': ' . $asset->getError());
                    }
                }
                $this->link($table, [$row['id'] => (int) $asset->id]);
            }
        } while (true);
    }

    private function table(string $type): string
    {
        if (!in_array($type, ['category', 'venue'], true)) {
            throw new InvalidArgumentException('Unsupported ACL resource.');
        }
        return $type === 'category' ? '#__jem_categories' : '#__jem_venues';
    }

    private function rows(string $type, bool $lock = false): array
    {
        $fields = $type === 'category' ? 'id, parent_id, catname AS title, asset_id' : 'id, venue AS title, asset_id';
        $sql = 'SELECT ' . $fields . ' FROM ' . $this->db->quoteName($this->table($type));
        $sql .= $type === 'category' ? ' WHERE id > 1 ORDER BY lft, id' : ' ORDER BY id';
        $sql .= $lock ? ' FOR UPDATE' : '';
        $rows = (array) $this->db->setQuery($sql)->loadAssocList('id');
        // Normalise exactly as Joomla's asset title column requires.
        foreach ($rows as &$row) {
            $row['title'] = mb_substr((string) $row['title'], 0, 100);
        }
        unset($row);
        if ($type === 'category') {
            $seen = [1 => true];
            foreach ($rows as $row) {
                if (!isset($seen[(int) $row['parent_id']])) {
                    throw new RuntimeException('The JEM category tree contains a missing parent or invalid traversal order.');
                }
                $seen[(int) $row['id']] = true;
            }
        }
        return $rows;
    }

    private function assets(bool $lock = false): array
    {
        // Lock rows and insertion gaps on the PRIMARY index before changing coordinates.
        return (array) $this->db->setQuery(
            'SELECT id, parent_id, lft, rgt, level, name, title, rules FROM #__assets ORDER BY id'
            . ($lock ? ' FOR UPDATE' : '')
        )->loadAssocList('id');
    }

    private function parentName(array $row, string $type): string
    {
        return $type === 'category' && (int) $row['parent_id'] > 1
            ? 'com_jem.category.' . (int) $row['parent_id'] : 'com_jem';
    }

    private function pending(array $rows, array $names, string $type): array
    {
        $pending = [];
        foreach ($rows as $row) {
            $asset = $names['com_jem.' . $type . '.' . $row['id']] ?? null;
            $parent = $names[$this->parentName($row, $type)] ?? null;
            if (!$asset || !$parent || (int) $asset['parent_id'] !== (int) $parent['id']
                || (int) $row['asset_id'] !== (int) $asset['id'] || $asset['title'] !== $row['title']) {
                $pending[] = (int) $row['id'];
            }
        }
        return $pending;
    }

    private function isTransactional(string $table): bool
    {
        $db = $this->db;
        $names = [$db->replacePrefix('#__assets'), $db->replacePrefix($table)];
        $engines = (array) $db->setQuery(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('
            . implode(',', array_map([$db, 'quote'], $names)) . ')'
        )->loadColumn();
        return count($engines) === 2 && count(array_filter($engines, static fn($e) => strtoupper($e) === 'INNODB')) === 2;
    }

    /** Insert at most batchSize missing nodes below one existing parent. */
    private function insertForest(string $type, int $parentId): void
    {
        $db = $this->db;
        // Applies to this transaction only; protects gaps even on READ COMMITTED hosts.
        // MySQL/MariaDB reject this statement if the caller already has an active transaction.
        $db->setQuery('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')->execute();
        $db->transactionStart();
        try {
            $assets = $this->assets(true);
            self::validateForJemMigration($assets);
            if (!isset($assets[$parentId])) {
                throw new RuntimeException('The ACL parent changed during migration. Please retry.');
            }
            $rows = $this->rows($type, true);
            $names = array_column($assets, null, 'name');
            $children = [];
            foreach ($rows as $row) {
                if (isset($names['com_jem.' . $type . '.' . $row['id']])) {
                    continue;
                }
                $parentName = $this->parentName($row, $type);
                $children[$parentName][] = $row;
            }
            $parent = $assets[$parentId];
            $position = (int) $parent['rgt'];
            $cursor = $position;
            $forest = [];
            $visit = function (string $parentName, int $level) use (&$visit, &$cursor, &$forest, $children, $type): void {
                foreach ($children[$parentName] ?? [] as $row) {
                    if (count($forest) >= $this->batchSize) {
                        break;
                    }
                    $name = 'com_jem.' . $type . '.' . $row['id'];
                    $forest[$name] = ['row' => $row, 'parent' => $parentName, 'lft' => $cursor++, 'level' => $level];
                    $visit($name, $level + 1);
                    $forest[$name]['rgt'] = $cursor++;
                }
            };
            $visit($parent['name'], (int) $parent['level'] + 1);
            if (!$forest) {
                throw new RuntimeException('No eligible new ACL assets remain in this batch. Please retry.');
            }
            $width = count($forest) * 2;
            $db->setQuery('UPDATE #__assets SET lft = lft + ' . $width . ' WHERE lft >= ' . $position)->execute();
            $db->setQuery('UPDATE #__assets SET rgt = rgt + ' . $width . ' WHERE rgt >= ' . $position)->execute();
            $values = [];
            foreach ($forest as $name => $node) {
                // The temporary parent is invisible outside this transaction.
                $values[] = '(' . $parentId . ',' . $node['lft'] . ',' . $node['rgt'] . ',' . $node['level'] . ','
                    . $db->quote($name) . ',' . $db->quote($node['row']['title']) . ',' . $db->quote('{}') . ')';
            }
            $db->setQuery('INSERT INTO #__assets (parent_id,lft,rgt,level,name,title,rules) VALUES ' . implode(',', $values))->execute();
            $inserted = (array) $db->setQuery('SELECT id,name FROM #__assets WHERE name IN ('
                . implode(',', array_map([$db, 'quote'], array_keys($forest))) . ')')->loadAssocList('name', 'id');
            if (count($inserted) !== count($forest)) {
                throw new RuntimeException('The ACL insertion could not be verified.');
            }
            $parents = [];
            $links = [];
            foreach ($forest as $name => $node) {
                $id = (int) $inserted[$name];
                $expectedParent = (int) ($inserted[$node['parent']] ?? $parentId);
                $parents[] = 'WHEN ' . $id . ' THEN ' . $expectedParent;
                $links[(int) $node['row']['id']] = $id;
            }
            $db->setQuery('UPDATE #__assets SET parent_id = CASE id ' . implode(' ', $parents)
                . ' END WHERE id IN (' . implode(',', array_map('intval', $inserted)) . ')')->execute();
            $this->link($this->table($type), $links);
            self::validateForJemMigration($this->assets());
            $db->transactionCommit();
        } catch (Throwable $error) {
            $db->transactionRollback();
            throw $error;
        }
        Access::clearStatics();
    }

    private function link(string $table, array $links): void
    {
        $cases = [];
        foreach ($links as $id => $assetId) {
            $cases[] = 'WHEN ' . (int) $id . ' THEN ' . (int) $assetId;
        }
        $this->db->setQuery('UPDATE ' . $this->db->quoteName($table) . ' SET asset_id = CASE id '
            . implode(' ', $cases) . ' END WHERE id IN (' . implode(',', array_map('intval', array_keys($links))) . ')')->execute();
    }

    /** Keep foreign ACL metadata errors from blocking JEM while checking shared coordinates. */
    public static function validateForJemMigration(array $assets): void
    {
        self::validateTree($assets, true);
    }

    /** Validate nesting without renumbering, deleting or rewriting any assets. */
    public static function validateTree(array $assets, bool $jemMigration = false): void
    {
        $jem = null;
        foreach ($assets as $asset) {
            if ($asset['name'] === 'com_jem') {
                $jem = $asset;
                break;
            }
        }
        uasort($assets, static fn($a, $b) => (int) $a['lft'] <=> (int) $b['lft']);
        $stack = [];
        $coordinates = [];
        $roots = 0;
        foreach ($assets as $asset) {
            $left = (int) $asset['lft'];
            $right = (int) $asset['rgt'];
            if ($left < 0 || $left >= $right || isset($coordinates[$left]) || isset($coordinates[$right])) {
                $reason = $left < 0 || $left >= $right ? 'invalid lft/rgt interval' : 'duplicate lft/rgt coordinate';
                throw new RuntimeException('The Joomla ACL tree has invalid or duplicate coordinates. Restore or repair it before continuing.'
                    . self::describeAsset($asset, $reason));
            }
            $coordinates[$left] = $coordinates[$right] = true;
            while ($stack && $left > (int) end($stack)['rgt']) {
                array_pop($stack);
            }
            $parent = $stack ? end($stack) : null;
            $isJem = $asset['name'] === 'com_jem' || str_starts_with((string) $asset['name'], 'com_jem.');
            $insideJem = $jem !== null && $left > (int) $jem['lft'] && $right < (int) $jem['rgt'];
            $checkLineage = !$jemMigration || $isJem || $insideJem;
            if ($parent ? ($right >= (int) $parent['rgt']
                    || ($checkLineage && ((int) $asset['parent_id'] !== (int) $parent['id']
                        || (int) $asset['level'] !== (int) $parent['level'] + 1))
                    || ($jemMigration && $isJem && ($asset['name'] === 'com_jem'
                        ? $parent['name'] !== 'root.1' : !$insideJem)))
                : ((int) $asset['parent_id'] !== 0 || (int) $asset['level'] !== 0 || ++$roots > 1 || $asset['name'] !== 'root.1')) {
                if (!$parent) {
                    $reason = 'outside the root asset or an additional root';
                } elseif ($right >= (int) $parent['rgt']) {
                    $reason = 'overlaps asset #' . (int) $parent['id'];
                } elseif ($jemMigration && $isJem && ($asset['name'] === 'com_jem'
                    ? $parent['name'] !== 'root.1' : !$insideJem)) {
                    $reason = 'JEM asset is outside its component hierarchy';
                } elseif ((int) $asset['parent_id'] !== (int) $parent['id']) {
                    $reason = 'parent_id differs from nesting below asset #' . (int) $parent['id'];
                } else {
                    $reason = 'level differs from nesting below asset #' . (int) $parent['id'];
                }
                throw new RuntimeException('The Joomla ACL hierarchy is inconsistent. No automatic global rebuild was attempted.'
                    . self::describeAsset($asset, $reason));
            }
            $stack[] = $asset;
        }
        if ($roots !== 1) {
            throw new RuntimeException('The Joomla ACL root is missing.');
        }
    }

    private static function describeAsset(array $asset, string $reason): string
    {
        return sprintf(
            ' First problem: asset #%d "%s" (parent_id %d, level %d, lft %d, rgt %d): %s.',
            (int) $asset['id'],
            substr((string) $asset['name'], 0, 100),
            (int) $asset['parent_id'],
            (int) $asset['level'],
            (int) $asset['lft'],
            (int) $asset['rgt'],
            $reason
        );
    }
}

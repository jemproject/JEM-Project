<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Native Joomla menu deletion and real SQL on disposable databases, never installed site data. */
final class MenuLifecycleDatabaseTest extends TestCase
{
    public static function modes(): array
    {
        return [['keep'], ['full']];
    }

    #[DataProvider('modes')]
    public function testUninstallAndReinstallRespectOwnershipAndPublication(string $mode): void
    {
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$root || getenv('JEM_TEST_MENU_DATABASES') !== '1') {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_MENU_DATABASES=1; requires CREATE DATABASE.');
        }
        $command = [PHP_BINARY, JEM_TEST_ROOT . '/tests/Support/menu_lifecycle_probe.php', $root, JEM_TEST_ROOT . '/script.php', $mode];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $before = array_column($result['before'], null, 'id');
        $after = array_column($result['after_uninstall'], null, 'id');
        $reinstalled = array_column($result['after_reinstall_repair'], null, 'id');
        $owned = [11,12,13,14,15,16,21,22,23,24,26,27,28,31,40,41,42,43,44,45,46];
        foreach ($owned as $id) {
            if ($mode === 'full') {
                self::assertArrayNotHasKey($id, $after);
            } else {
                self::assertArrayHasKey($id, $after);
                self::assertSame($id === 27 ? -2 : 0, (int) $after[$id]['published'], 'Owned entry ' . $id);
            }
        }
        foreach ([1,10,17,20,25,29,30,32,33,47,48] as $id) {
            self::assertArrayHasKey($id, $after, 'Foreign or mixed entry ' . $id);
            self::assertSame((int) $before[$id]['published'], (int) $after[$id]['published']);
            self::assertSame((int) $before[$id]['component_id'], (int) $reinstalled[$id]['component_id']);
        }
        foreach ($after as $id => $item) {
            self::assertSame($item['published'], $reinstalled[$id]['published'], 'Reinstall must not publish ' . $id);
            if ($mode === 'keep' && in_array($id, $owned, true)) {
                self::assertSame($item['lft'], $before[$id]['lft']);
                self::assertSame($item['rgt'], $before[$id]['rgt']);
                self::assertSame($item['access'], $before[$id]['access']);
                self::assertSame($item['link'], $before[$id]['link']);
                self::assertSame($item['type'] === 'component' ? 900 : (int) $item['component_id'], (int) $reinstalled[$id]['component_id']);
            }
        }
        self::assertSame([], $result['orphan_menu_ids']);
        $coordinates = [];
        foreach ($after as $id => $item) {
            $coordinates[] = (int) $item['lft'];
            $coordinates[] = (int) $item['rgt'];
            if ((int) $item['parent_id'] !== 0) {
                $parent = $after[(int) $item['parent_id']];
                self::assertGreaterThan((int) $parent['lft'], (int) $item['lft']);
                self::assertLessThan((int) $parent['rgt'], (int) $item['rgt']);
            }
        }
        sort($coordinates);
        self::assertSame(range(0, count($coordinates) - 1), $coordinates, 'No duplicate coordinates or gaps after cleanup.');
        self::assertSame($mode === 'full' ? 20 : 21, (int) $after[33]['parent_id']);
        if ($mode === 'full') {
            self::assertSame('case-20/case-33', $after[33]['path']);
            self::assertSame([0,25], array_map('intval', array_column($result['module_assignments'], 'menuid')));
            self::assertCount(2, $result['associations']);
        } else {
            self::assertCount(4, $result['module_assignments']);
            self::assertCount(3, $result['associations']);
        }
    }
}

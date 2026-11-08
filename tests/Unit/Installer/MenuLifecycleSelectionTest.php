<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/menulifecycle.php';

final class MenuLifecycleSelectionTest extends TestCase
{
    /** Build one menu fixture with explicit routing and ownership metadata. */
    private function item(int $id, string $link = '#', array $changes = []): object
    {
        return (object) array_replace([
            'id' => $id, 'link' => $link, 'type' => 'component', 'component_id' => 200,
            'parent_id' => 1, 'level' => 1, 'published' => 1, 'access' => 1,
            'client_id' => 0, 'params' => '{}', 'menutype' => 'mainmenu', 'alias' => 'entry-' . $id,
        ], $changes);
    }

    public static function links(): array
    {
        return [
            ['index.php?option=com_jem&view=eventslist', true],
            ['index.php?view=eventslist&option=com_jem', true],
            ['index.php?view=eventslist&amp;option=com_jem', true],
            ['./index.php?option=com_jem', true],
            ['?option=com_jem', true],
            ['/joomla/index.php?option=com_jem', true],
            ['https://example.test/joomla/index.php?option=com_jem', true],
            ['http://example.test/joomla/index.php?option=com_jem', true],
            ['//example.test/joomla/index.php?option=com_jem', true],
            ['/joomla/administrator/index.php?option=com_jem', true],
            ['index.php?option=com%5Fjem', true],
            ['index.php?option=com_jem_other', false],
            ['index.php?option=comXjem', false],
            ['index.php?option=com_content&Itemid=8', false],
            ['index.php?option[]=com_jem', false],
            ['https://external.test/joomla/index.php?option=com_jem', false],
            ['https://example.test/another-site/index.php?option=com_jem', false],
            ['https://example.test/joomla-other/index.php?option=com_jem', false],
            ['https://example.test:8080/joomla/index.php?option=com_jem', false],
            ['../another-site/index.php?option=com_jem', false],
            ['https://example.test/joomla/../another-site/index.php?option=com_jem', false],
            ['javascript:alert(1)?option=com_jem', false],
        ];
    }

    #[DataProvider('links')]
    public function testExactComponentAndSiteBoundaries(string $link, bool $owned): void
    {
        $result = JemMenuLifecycle::select([$this->item(2, $link)], 'https://example.test/joomla/', 200);
        self::assertSame($owned ? [2] : [], array_keys($result['items']));
        self::assertSame($owned ? [2] : [], $result['components']);
    }

    public function testAliasesItemidChainsAndCycles(): void
    {
        $items = [
            $this->item(2, 'index.php?option=com_jem'),
            $this->item(3, 'index.php?Itemid=2', ['type' => 'alias', 'params' => '{"aliasoptions":2}']),
            $this->item(4, 'index.php?Itemid=3', ['type' => 'alias', 'params' => '{"aliasoptions":"3"}']),
            $this->item(5, 'index.php?Itemid=4', ['type' => 'url']),
            $this->item(6, '#', ['type' => 'alias', 'params' => '{"aliasoptions":7}']),
            $this->item(7, '#', ['type' => 'alias', 'params' => '{"aliasoptions":6}']),
            $this->item(8, 'index.php?Itemid=2', ['type' => 'alias', 'params' => '{"aliasoptions":[2]}']),
            $this->item(9, 'index.php?option=com_content&Itemid=2'),
            $this->item(10, '#', ['type' => 'alias', 'params' => '{"aliasoptions":999}']),
        ];
        $result = JemMenuLifecycle::select($items, 'https://example.test/joomla/', 200);
        self::assertEqualsCanonicalizing([2, 3, 4, 5], array_keys($result['items']));
        self::assertSame([2], $result['components']);
    }

    public function testGeneratedLegacyGroupsAndMarkersPreserveMixedContainers(): void
    {
        $group = ['type' => 'heading', 'component_id' => 0, 'menutype' => 'jem-frontend-menu'];
        $items = [
            $this->item(2, '#', $group + ['alias' => 'jem-frontend']),
            $this->item(3, '#', $group + ['alias' => 'events', 'parent_id' => 2]),
            $this->item(4, 'index.php?option=com_jem', ['parent_id' => 3]),
            $this->item(5, '#', ['type' => 'heading', 'params' => '{"jem_generated_menu":1}', 'parent_id' => 2]),
            $this->item(6, 'index.php?option=com_jem', ['parent_id' => 5]),
            $this->item(7, 'index.php?option=com_content', ['parent_id' => 5, 'published' => 0]),
            $this->item(8, '#', ['type' => 'heading', 'alias' => 'JEM']),
            $this->item(9, 'index.php?option=com_content', ['params' => '{"jem_generated_menu":1}']),
        ];
        $result = JemMenuLifecycle::select($items, 'https://example.test/joomla/', 200);
        self::assertEqualsCanonicalizing([3, 4, 6], array_keys($result['items']));
        self::assertEqualsCanonicalizing([4, 6], $result['components']);
        $items = array_filter($items, static fn ($item) => $item->id !== 7);
        $result = JemMenuLifecycle::select($items, 'https://example.test/joomla/', 200);
        self::assertEqualsCanonicalizing([2, 3, 4, 5, 6], array_keys($result['items']));
    }

    public function testAccessPublicationAndLanguageDoNotExcludeOwnedLinks(): void
    {
        $items = [];
        foreach ([1, 2, 3, 99] as $index => $access) {
            $items[] = $this->item($index + 2, 'index.php?option=com_jem', [
                'published' => [1, 0, -2, 1][$index], 'access' => $access, 'language' => 'es-ES',
            ]);
        }
        $items[] = $this->item(10, 'index.php?option=com_jem', ['client_id' => 1]);
        self::assertSame([2, 3, 4, 5], array_keys(JemMenuLifecycle::select($items, 'https://example.test/joomla/', 200)['items']));
    }

    public function testComponentReferenceFallbackDoesNotOverrideAnExplicitDifferentDestination(): void
    {
        $items = [
            $this->item(2, 'index.php'),
            $this->item(3, 'index.php?option=com_content'),
            $this->item(4, 'https://external.test/index.php?option=com_jem'),
            $this->item(5, 'index.php?option=com_jem', ['type' => 'url', 'component_id' => 0]),
        ];
        $selection = JemMenuLifecycle::select($items, 'https://example.test/joomla/', 200);
        self::assertSame([2, 5], array_keys($selection['items']));
        self::assertSame([2], $selection['components']);
    }

    public function testMarkedGroupWithForeignGrandchildIsNotTreatedAsAnEmptyJemContainer(): void
    {
        $items = [
            $this->item(2, '#', ['type' => 'heading', 'params' => '{"jem_generated_menu":1}']),
            $this->item(3, 'index.php?option=com_jem', ['parent_id' => 2]),
            $this->item(4, 'index.php?option=com_content', ['parent_id' => 3]),
        ];
        self::assertSame([3], array_keys(JemMenuLifecycle::select($items, 'https://example.test/joomla/', 200)['items']));
    }
}

<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

require_once JEM_TEST_ROOT . '/site/classes/resourceacl.class.php';

final class ResourceAclPolicyTest extends TestCase
{
    #[DataProvider('decisions')]
    public function testThreeStateResourceDecisions(
        string $type,
        string $operation,
        array $categories,
        array $rules,
        bool|null $expected
    ): void {
        $decision = JemResourceAclPolicy::decide(
            $type,
            $operation,
            $categories,
            static fn(string $action, string $asset): bool|null => $rules[$action . '@' . $asset] ?? null
        );

        self::assertSame($expected, $decision);
    }

    public static function decisions(): iterable
    {
        yield 'venue component allow' => array(
            'venue', 'edit', array(4), array('jem.venues.edit@com_jem' => true), true,
        );
        yield 'venue not set' => array('venue', 'edit', array(), array(), null);
        yield 'event all categories allow' => array(
            'event',
            'publish',
            array(4, 7, 4),
            array(
                'jem.events.edit.state@com_jem.category.4' => true,
                'jem.events.edit.state@com_jem.category.7' => true,
            ),
            true,
        );
        yield 'event mixed allow and deny is denied' => array(
            'event',
            'delete',
            array(4, 7),
            array(
                'jem.events.delete@com_jem.category.4' => true,
                'jem.events.delete@com_jem.category.7' => false,
            ),
            false,
        );
        yield 'event allow and not set returns not set' => array(
            'event',
            'add',
            array(4, 7),
            array('jem.events.create@com_jem.category.4' => true),
            null,
        );
        yield 'categoryless event uses component' => array(
            'event', 'edit', array(), array('jem.events.edit@com_jem' => false), false,
        );
        yield 'unsupported operation is denied' => array('event', 'unknown', array(), array(), false);
    }

    public function testActionMapSeparatesEventsAndVenues(): void
    {
        self::assertSame('jem.events.edit.own', JemResourceAclPolicy::getAction('event', 'edit.own'));
        self::assertSame('jem.venues.edit.state', JemResourceAclPolicy::getAction('venue', 'publish'));
        self::assertNull(JemResourceAclPolicy::getAction('type', 'edit'));
    }

    #[DataProvider('legacyCategoryDecisions')]
    public function testLegacyGroupNeedsEveryCategoryAndRespectsCategoryAcl(
        string $operation,
        array $categories,
        array $groupIds,
        array $aclDecisions,
        array $managedCategories,
        bool $expected
    ): void {
        $categories = array_map(static fn(array $category): object => (object) $category, $categories);

        self::assertSame($expected, JemResourceAclPolicy::allowsLegacyEventCategories(
            $operation,
            $categories,
            $groupIds,
            array(1, 2),
            static fn(string $action, int $categoryId): ?bool => $aclDecisions[$categoryId] ?? null,
            static fn(int $categoryId): bool => in_array($categoryId, $managedCategories, true)
        ));
    }

    public static function legacyCategoryDecisions(): iterable
    {
        $music = array('id' => 1, 'groupid' => 7, 'access' => 1, 'published' => 1);
        $sport = array('id' => 2, 'groupid' => 0, 'access' => 1, 'published' => 1);
        $other = array('id' => 3, 'groupid' => 8, 'access' => 1, 'published' => 1);

        yield 'matching group' => array('edit', array($music), array(7), array(), array(), true);
        yield 'unmanaged unrestricted category' => array('edit', array($sport), array(7), array(), array(), true);
        yield 'ACL-delegated category blocks unrelated group' => array('edit', array($sport), array(7), array(), array(2), false);
        yield 'ACL allow on managed category' => array('edit', array($sport), array(7), array(2 => true), array(2), true);
        yield 'ACL deny takes precedence' => array('edit', array($sport), array(7), array(2 => false), array(2), false);
        yield 'one group match is not enough' => array('edit', array($music, $other), array(7), array(), array(), false);
        yield 'all categories permit edit' => array('edit', array($music, $sport), array(7), array(), array(), true);
        yield 'mixed ACL and group permission' => array('edit', array($music, $sport), array(7), array(2 => true), array(2), true);
        yield 'unrestricted category does not grant publish' => array('publish', array($sport), array(7), array(), array(), false);
        yield 'unpublished category denies fallback' => array('edit', array($music, array_replace($sport, array('published' => 0))), array(7), array(), array(), false);
        yield 'inaccessible category denies fallback' => array('edit', array($music, array_replace($sport, array('access' => 5))), array(7), array(), array(), false);
    }
}

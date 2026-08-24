<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventslistMenuPolicyContractsTest extends TestCase
{
    public function testEventsListModelUsesOnePolicyForBothDateFilterPaths(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');

        self::assertStringContainsString("require_once(JPATH_SITE.'/components/com_jem/classes/eventslistmenupolicy.class.php');", $model);
        self::assertSame(2, substr_count($model, 'JemEventslistMenuPolicy::dateWindow('));
        self::assertStringContainsString("setState('filter.tablefiltereventfrom'", $model);
        self::assertStringContainsString("setState('filter.tablefiltereventuntil'", $model);
        self::assertStringNotContainsString('$openDatesCondition', $model);
    }

    public function testEventsListModelOwnsValidatedOrderingState(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/eventslist/view.html.php');

        self::assertStringContainsString('JemEventslistMenuPolicy::orderField(', $model);
        self::assertStringContainsString('JemEventslistMenuPolicy::orderContext(', $model);
        self::assertStringContainsString('JemEventslistMenuPolicy::buildOrderBy(', $model);
        self::assertStringContainsString("setState('list.ordering'", $model);
        self::assertStringContainsString("setState('list.direction'", $model);
        self::assertStringNotContainsString(
            "if (empty(\$app->input->get('filter_type')) && \$tableInitialorderby)",
            $model
        );

        self::assertStringContainsString("getState('list.direction', 'ASC')", $view);
        self::assertStringContainsString("getState('list.ordering', 'a.dates')", $view);
        self::assertStringNotContainsString("'.filter_order'", $view);
        self::assertStringNotContainsString("'.filter_order_Dir'", $view);
    }

    public function testUpgradeSqlDoesNotRewriteLegitimateZeroMenuValues(): void
    {
        $legacyMigration = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/4.5.0.sql');
        $currentMigration = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.1.sql');

        self::assertStringContainsString('"tablefiltereventuntil":"0"', $legacyMigration);
        self::assertStringContainsString('"tablefiltereventuntil":""', $legacyMigration);
        self::assertStringNotContainsString('tablefiltereventfrom', $currentMigration);
        self::assertStringNotContainsString('tablefiltereventuntil', $currentMigration);
    }

    public function testPackageValidationRequiresTheRuntimePolicy(): void
    {
        $builder = (string) file_get_contents(JEM_TEST_ROOT . '/scripts/build-packages.php');

        self::assertStringContainsString("'site/classes/eventslistmenupolicy.class.php'", $builder);
    }
}

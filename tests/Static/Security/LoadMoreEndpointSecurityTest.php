<?php

use PHPUnit\Framework\TestCase;

final class LoadMoreEndpointSecurityTest extends TestCase
{
    public function testEndpointUsesAnExplicitBoundedReadTask(): void
    {
        $controller = $this->read('site/controller.php');
        $policy = $this->read('site/classes/loadmorerequestpolicy.class.php');

        self::assertStringContainsString('public function loadmore()', $controller);
        self::assertStringNotContainsString('HTTP_X_REQUESTED_WITH', $controller);
        self::assertStringContainsString('JemLoadMoreRequestPolicy::isGetRequest($method)', $controller);
        self::assertStringContainsString('JemLoadMoreRequestPolicy::normaliseParameters($input->getArray())', $controller);
        self::assertStringContainsString("\$this->getModel('eventslist')", $controller);
        self::assertStringContainsString('public const MAX_OFFSET = 10000;', $policy);
        self::assertStringContainsString('public const MAX_LIMIT = 100;', $policy);
        self::assertStringContainsString('public const MAX_QUERY_LENGTH = 4096;', $policy);
        self::assertStringContainsString('private const ALLOWED_PARAMETERS', $policy);
        self::assertStringContainsString('flock($handle, LOCK_EX)', $policy);
    }

    public function testResponseIsPrivateTypedAndRateLimited(): void
    {
        $controller = $this->read('site/controller.php');

        self::assertStringContainsString('JemLoadMoreRequestPolicy::consumeRateLimit(', $controller);
        self::assertStringContainsString("http_response_code(\$status);", $controller);
        self::assertStringContainsString("'Content-Type', 'application/json; charset=utf-8'", $controller);
        self::assertStringContainsString("'X-Content-Type-Options', 'nosniff'", $controller);
        self::assertStringContainsString("'Cache-Control', 'no-store, private'", $controller);
        self::assertStringContainsString('new JsonResponse(', $controller);
        self::assertStringContainsString("Log::add(", $controller);
        self::assertStringNotContainsString("Log::add(\$exception->getMessage()", $controller);
    }

    public function testInitialAndIncrementalResultsUseTheSameEscapedItemPartial(): void
    {
        $controller = $this->read('site/controller.php');
        $template = $this->read('site/common/views/tmpl/responsive/default_jem_eventslist.php');
        $partial = $this->read('site/common/views/tmpl/responsive/default_jem_eventslist_item.php');

        self::assertStringContainsString('/default_jem_eventslist_item.php', $controller);
        self::assertStringContainsString("require __DIR__ . '/default_jem_eventslist_item.php';", $template);
        self::assertStringNotContainsString('private function renderEventItems', $controller);
        self::assertStringNotContainsString('onclick=', $partial);
        self::assertStringContainsString('data-jem-event-url=', $partial);
        self::assertStringContainsString('empty($row->user_has_access_event)', $partial);
        self::assertStringContainsString('!empty($row->user_has_access_venue)', $partial);
        self::assertStringContainsString('JemOutput::eventStateBadges(', $partial);
        self::assertStringContainsString('ENT_QUOTES | ENT_SUBSTITUTE', $partial);
        self::assertStringContainsString('empty($row->user_has_access_category)', $controller);
        self::assertStringContainsString('empty($row->user_has_access_category)', $template);
    }

    public function testClientUsesTheGeneratedEndpointAndOneBoundedMonthValue(): void
    {
        $javascript = $this->read('media/js/load-more.js');
        $template = $this->read('site/common/views/tmpl/responsive/default_jem_eventslist.php');

        self::assertStringContainsString("data-endpoint=", $template);
        self::assertStringContainsString("task=loadmore&format=json", $template);
        self::assertStringContainsString("data-next-offset=", $template);
        self::assertStringContainsString("requestUrl.searchParams.set('lastDisplayedMonth'", $javascript);
        self::assertStringContainsString("requestUrl.searchParams.set('offset'", $javascript);
        self::assertStringNotContainsString('window.location.search', $javascript);
        self::assertStringNotContainsString('displayedMonths[', $javascript);
        self::assertStringContainsString('target.origin === window.location.origin', $javascript);
    }

    public function testPaginationAvoidsASecondCountQueryAndPreservesArchiveContext(): void
    {
        $model = $this->read('site/models/eventslist.php');

        self::assertStringContainsString("\$this->setState('list.limit', \$limit + 1);", $model);
        self::assertStringContainsString('$hasMore = count($items) > $limit;', $model);
        self::assertStringNotContainsString('$this->getTotal()', $model);
        self::assertStringContainsString("if (\$task === 'loadmore')", $model);
        self::assertStringContainsString("getCmd('loadmore_context', '') === 'archive'", $model);
    }

    public function testPackageBuilderRequiresThePolicyAndSharedPartial(): void
    {
        $builder = $this->read('scripts/build-packages.php');

        self::assertStringContainsString("'site/classes/loadmorerequestpolicy.class.php'", $builder);
        self::assertStringContainsString("'site/common/views/tmpl/responsive/default_jem_eventslist_item.php'", $builder);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}

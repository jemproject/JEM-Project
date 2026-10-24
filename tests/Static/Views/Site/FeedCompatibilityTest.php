<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FeedCompatibilityTest extends TestCase
{
    public function testFeedsUseNamespacedItemOnJoomlaSix(): void
    {
        foreach (array('eventslist', 'category', 'day', 'venue') as $view) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.feed.php');

            self::assertStringContainsString('use Joomla\\CMS\\Document\\Feed\\FeedItem;', $source, $view);
            self::assertStringContainsString('new FeedItem()', $source, $view);
            self::assertStringNotContainsString('new JFeedItem()', $source, $view);
        }
    }

    public function testVenueFeedUsesTheModelAccessFlag(): void
    {
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venue/view.feed.php');

        self::assertStringContainsString('empty($venue->user_has_access_venue)', $source);
        self::assertStringNotContainsString('locauthorised', $source);
    }
}

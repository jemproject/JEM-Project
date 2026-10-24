<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FeedCompatibilityTest extends TestCase
{
    public function testFeedsUseNamespacedItemOnJoomlaSix(): void
    {
        $builder = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/feed.class.php');

        self::assertStringContainsString('use Joomla\\CMS\\Document\\Feed\\FeedItem;', $builder);
        self::assertStringContainsString('new FeedItem()', $builder);
        self::assertStringContainsString('new FeedEnclosure()', $builder);

        foreach (array('eventslist', 'category', 'day', 'venue') as $view) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.feed.php');

            self::assertStringContainsString('JemFeed::limitModel(', $source, $view);
            self::assertStringContainsString('JemFeed::addEvents(', $source, $view);
            self::assertStringNotContainsString('new JFeedItem()', $source, $view);
        }
    }

    public function testVenueFeedUsesTheModelAccessFlag(): void
    {
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/venue/view.feed.php');

        self::assertStringContainsString('empty($venue->user_has_access_venue)', $source);
        self::assertStringNotContainsString('locauthorised', $source);
    }

    public function testFeedLimitOverridesThePopulatedListState(): void
    {
        require_once JEM_TEST_ROOT . '/site/classes/feed.class.php';

        $model = new class {
            public array $state = array();

            public function getState(): array
            {
                $this->state = array('list.start' => 20, 'list.limit' => 100);

                return $this->state;
            }

            public function setState(string $key, int $value): void
            {
                $this->state[$key] = $value;
            }
        };
        $app = new class {
            public function get(string $key, int $default): int
            {
                return $key === 'feed_limit' ? 10 : $default;
            }
        };

        JemFeed::limitModel($model, $app);

        self::assertSame(0, $model->state['list.start']);
        self::assertSame(10, $model->state['list.limit']);
    }
}

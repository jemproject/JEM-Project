<?php

declare(strict_types=1);

use JemProject\Tests\ArticleSource\Database;
use JemProject\Tests\ArticleSource\Content;
use JemProject\Tests\ArticleSource\Factory;
use JemProject\Tests\ArticleSource\JemHelper;
use JemProject\Tests\ArticleSource\Model;
use JemProject\Tests\ArticleSource\Registry;
use JemProject\Tests\ArticleSource\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssociatedArticleSourceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/article_source_environment.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $methods = '';
        foreach (['normaliseAssociatedArticleSyncMode', 'eventAllowsAssociatedArticleSync',
            'eventUsesAssociatedArticleAsContent', 'setAssociatedArticleSyncState',
            'getAssociatedArticleSyncChanges', 'normaliseAssociatedArticleSyncValue',
            'updateAssociatedArticleFromEvent', 'createAssociatedArticleIfRequested',
            'getAssociatedArticleInitialText', 'applyRecurringAssociatedArticleMode'] as $name) {
            self::assertSame(1, preg_match('/((?:protected|public) function ' . $name
                . '\(.*?)(?=\r?\n    \/\*\*)/s', $source, $match), $name);
            $methods .= $match[1];
        }
        eval('namespace JemProject\\Tests\\ArticleSource; use Throwable; '
            . 'final class Model extends ModelDependencies {' . $methods . '}');
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        self::assertSame(1, preg_match('/(static public function applyAssociatedArticleEventContent\(.*?)(?=\r?\n    \/\*\*)/s', $helper, $match));
        eval('namespace JemProject\\Tests\\ArticleSource; final class Content extends ContentDependencies {' . $match[1] . '}');
    }

    protected function setUp(): void
    {
        JemHelper::$settings = new Registry(['event_article_content_change_action' => 'ask']);
        Factory::$db = new Database();
        Factory::$user = new User();
        Factory::$messages = [];
    }

    private function event(?string $mode = 'none', int $article = 40): array
    {
        $attribs = ['article_usage' => 'content', 'unrelated_setting' => 123];
        if ($mode !== null) {
            $attribs['article_sync_mode'] = $mode;
        }
        return ['id' => 15, 'article_id' => $article, 'attribs' => $attribs,
            'title' => 'Event title', 'introtext' => 'Event intro', 'fulltext' => 'Event body',
            'datimage' => 'event.jpg'];
    }

    public static function sourceChanges(): iterable
    {
        yield 'first association' => [0, 40, 'content', 'settings', 'none'];
        yield 'change article' => [40, 41, 'content', 'settings', 'none'];
        yield 'remove article' => [40, 0, 'content', 'settings', 'none'];
        yield 'disable content' => [40, 40, 'information', 'settings', 'none'];
        yield 'explicit opt-in' => [40, 40, 'content', 'settings', 'settings'];
        yield 'read-only' => [40, 40, 'content', 'none', 'none'];
        yield 'legacy association' => [40, 40, 'content', null, 'none'];
        yield 'invalid mode' => [40, 40, 'content', 'auto', 'none'];
    }

    #[DataProvider('sourceChanges')]
    public function testConsentIsBoundToAnUnchangedSource(int $oldId, int $newId, string $usage, ?string $mode, string $expected): void
    {
        $old = $oldId ? $this->event('none', $oldId) : [];
        $event = $this->event($mode, $newId);
        $event['attribs']['article_usage'] = $usage;
        foreach (['array', 'json', 'registry'] as $format) {
            $input = $event;
            if ($format === 'json') {
                $input['attribs'] = json_encode($event['attribs']);
            } elseif ($format === 'registry') {
                $input['attribs'] = new Registry($event['attribs']);
            }
            $result = (new Model())->normalise($input, $old);
            self::assertSame($expected, $result['attribs']['article_sync_mode']);
            self::assertSame(123, $result['attribs']['unrelated_setting']);
        }
    }

    public static function policies(): iterable
    {
        foreach ([null, 'none', 'settings'] as $mode) {
            foreach (['none', 'ask', 'auto'] as $policy) {
                yield ($mode ?? 'legacy') . '-' . $policy => [$mode, $policy];
            }
        }
    }

    #[DataProvider('policies')]
    public function testTextAndImagesFollowTheSameExplicitPolicy(?string $mode, string $policy): void
    {
        JemHelper::$settings->set('event_article_content_change_action', $policy);
        $model = new Model();
        $old = $this->event($mode);
        $new = $old;
        $new['title'] = 'New event title';
        $new['introtext'] = 'New event description';
        $new['datimage'] = 'new.jpg';
        $before = Factory::$db->article;
        $model->sync($old, $new);

        $writes = $mode === 'settings' && $policy === 'auto';
        self::assertCount($writes ? 1 : 0, Factory::$db->writes);
        if ($writes) {
            self::assertSame('New event title', Factory::$db->article['title']);
            self::assertSame('New event description', Factory::$db->article['introtext']);
            self::assertNotSame($before['images'], Factory::$db->article['images']);
            self::assertSame(1, Factory::$db->article['state']);
        } else {
            self::assertSame($before, Factory::$db->article);
        }
        $asks = $mode === 'settings' && $policy === 'ask';
        self::assertSame($asks, isset($model->state['event.article_sync_fields']));
        if ($asks) {
            self::assertSame('title,introtext,datimage', $model->state['event.article_sync_fields']);
        }
    }

    public function testChangedOrClearedSourceNeverSynchronisesEvenWithStaleConsent(): void
    {
        JemHelper::$settings->set('event_article_content_change_action', 'auto');
        foreach ([0, 41] as $articleId) {
            $model = new Model();
            $new = $this->event('settings', $articleId);
            $new['title'] = 'Do not copy';
            $model->sync($this->event('settings'), $new);
            self::assertSame([], Factory::$db->writes);
            self::assertSame([], $model->state);
        }
    }

    public function testDirectUpdateCannotBypassReadOnlyOrDisabledGlobalPolicy(): void
    {
        $model = new Model();
        $model->stored = $this->event();
        self::assertFalse($model->updateAssociatedArticleFromEvent(15, 'title,datimage'));
        $model->stored = $this->event('settings');
        JemHelper::$settings->set('event_article_content_change_action', 'none');
        self::assertFalse($model->updateAssociatedArticleFromEvent(15, 'title'));
        JemHelper::$settings->set('event_article_content_change_action', 'auto');
        JemHelper::$settings->set('event_use_associated_article', 0);
        self::assertFalse($model->updateAssociatedArticleFromEvent(15, 'title'));
        self::assertSame([], Factory::$db->writes);
    }

    public function testExplicitUpdateStillRequiresAclAndCheckout(): void
    {
        $model = new Model();
        $model->stored = $this->event('settings');
        Factory::$user->edit = false;
        self::assertFalse($model->updateAssociatedArticleFromEvent(15, 'title'));
        Factory::$user->edit = true;
        Factory::$db->article['checked_out'] = 99;
        self::assertFalse($model->updateAssociatedArticleFromEvent(15, 'title'));
        self::assertSame([], Factory::$db->writes);
        Factory::$db->article['checked_out'] = 0;
        self::assertTrue($model->updateAssociatedArticleFromEvent(15, 'title,state'));
        self::assertSame('Event title', Factory::$db->article['title']);
        self::assertSame(1, Factory::$db->article['state']);
    }

    public function testNewArticleStillCopiesInitialTextAndEnablesPolicy(): void
    {
        $model = new Model();
        self::assertTrue($model->create($this->event('none', 0)));
        self::assertSame(['introtext' => 'Event intro', 'fulltext' => 'Event body'], $model->createdText);
        self::assertCount(1, Factory::$db->writes);
        [$table, $record] = Factory::$db->writes[0];
        self::assertSame('#__jem_events', $table);
        self::assertSame(41, $record->article_id);
        self::assertSame('settings', json_decode($record->attribs, true)['article_sync_mode']);
    }

    public function testReusedArticleDoesNotBecomeManagedOrGetOverwritten(): void
    {
        $model = new Model();
        $model->existingArticleId = 40;
        self::assertTrue($model->create($this->event('none', 0)));
        self::assertSame([], $model->createdText);
        self::assertCount(1, Factory::$db->writes);
        [$table, $record] = Factory::$db->writes[0];
        self::assertSame('#__jem_events', $table);
        self::assertFalse(isset($record->attribs));
        self::assertSame('Article body', Factory::$db->article['fulltext']);
    }

    public function testCreatingAnEmptyArticleDoesNotCopyDescription(): void
    {
        JemHelper::$settings->set('event_article_content_create_action', 'edit_article');
        $model = new Model();
        self::assertTrue($model->create($this->event('none', 0)));
        self::assertSame(['introtext' => '', 'fulltext' => ''], $model->createdText);
    }

    public function testArticleEditsAreReadOnTheNextRenderWithoutChangingTheEventData(): void
    {
        JemHelper::$settings->set('event_article_content_use_title', 0);
        JemHelper::$settings->set('event_article_content_use_alias', 0);
        $stored = $this->event();
        $stored['attribs'] = json_encode($stored['attribs']);
        Content::$article = (object) ['id' => 40, 'language' => '*', 'introtext' => 'Original intro',
            'fulltext' => 'Original article body'];
        $rendered = Content::applyAssociatedArticleEventContent((object) $stored, [1]);
        self::assertSame('Original article body', $rendered->fulltext);
        Content::$article->fulltext = 'Updated programme';
        foreach ([15, 16] as $eventId) {
            $other = $stored;
            $other['id'] = $eventId;
            $rendered = Content::applyAssociatedArticleEventContent((object) $other, [1]);
            self::assertSame('Updated programme', $rendered->fulltext);
            self::assertSame($eventId, $rendered->id);
            self::assertSame('Event title', $rendered->title);
            self::assertSame('Event body', $other['fulltext']);
        }
        self::assertSame([], Factory::$db->writes);
    }

    public function testUnavailableArticleUsesConfiguredFallbackWithoutWriting(): void
    {
        Content::$article = null;
        foreach (['event', 'blank'] as $fallback) {
            JemHelper::$settings->set('event_article_content_language_fallback', $fallback);
            $event = $this->event();
            $event['attribs'] = json_encode($event['attribs']);
            $rendered = Content::applyAssociatedArticleEventContent((object) $event, [1]);
            self::assertSame($fallback === 'blank' ? '' : 'Event body', $rendered->fulltext);
        }
        self::assertSame([], Factory::$db->writes);
    }

    public function testFrontendAndBackendExposeTheSameSafeDefault(): void
    {
        foreach (['admin', 'site'] as $area) {
            $form = simplexml_load_file(JEM_TEST_ROOT . '/' . $area . '/models/forms/event.xml');
            $fields = $form->xpath('//fields[@name="attribs"]//field[@name="article_sync_mode"]');
            self::assertCount(1, $fields);
            self::assertSame('none', (string) $fields[0]['default']);
        }
        foreach (['admin/views/event/tmpl/edit.php', 'site/views/editevent/tmpl/edit.php',
            'site/views/editevent/tmpl/responsive/edit.php'] as $file) {
            $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
            self::assertStringContainsString("getInput('article_sync_mode', 'attribs')", $source);
            self::assertStringContainsString('COM_JEM_EVENT_ARTICLE_CONTENT_SOURCE_NOTE', $source);
        }
    }

    public function testGeneratedRecurrenceArticlesHaveAnExplicitPolicyButSharedSourcesDoNot(): void
    {
        foreach ([1, 2] as $recurrenceMode) {
            Factory::$db = new Database();
            JemHelper::$settings->set('event_associated_article_recurrence_mode', $recurrenceMode);
            $model = new Model();
            $child = $this->event('none', 0);
            $child['id'] = 16;
            $child['attribs'] = json_encode($child['attribs']);
            $model->children = [(object) $child];
            $event = $this->event();
            $event['recurrence_type'] = 2;
            $model->recur($event);
            self::assertCount($recurrenceMode === 1 ? 1 : 2, Factory::$db->writes);
            foreach (Factory::$db->writes as [$table, $record]) {
                self::assertSame('#__jem_events', $table);
                self::assertSame(16, $record->id);
            }
            if ($recurrenceMode === 2) {
                $attribs = json_decode(Factory::$db->writes[1][1]->attribs, true);
                self::assertSame('settings', $attribs['article_sync_mode']);
                self::assertSame(123, $attribs['unrelated_setting']);
            }
            self::assertSame('Article body', Factory::$db->article['fulltext']);
        }
    }

    public function testBothSavePathsUsePolicyInsteadOfAnInitialOverwrite(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $save = substr($source, strpos($source, 'public function save('));
        $save = substr($save, 0, strpos($save, '\n    /**') ?: strlen($save));
        // Scope to the save method before the helpers below it.
        $save = substr($save, 0, strpos($save, 'protected function') ?: strlen($save));
        self::assertSame(2, substr_count($save, '$this->setAssociatedArticleSyncState('));
        self::assertStringNotContainsString('$this->updateAssociatedArticleFromEvent(', $save);
        self::assertStringContainsString('$this->normaliseAssociatedArticleSyncMode(', $save);
    }
}

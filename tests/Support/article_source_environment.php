<?php

declare(strict_types=1);

namespace JemProject\Tests\ArticleSource;

// Isolated dependencies: execute the production methods without installing Joomla.
final class Registry
{
    private array $data;

    public function __construct($data = [])
    {
        $this->data = $data instanceof self ? $data->toArray()
            : (is_string($data) ? (json_decode($data, true) ?: []) : (array) $data);
    }

    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }
    public function toArray()
    {
        return $this->data;
    }
    public function toString()
    {
        return json_encode($this->data);
    }
    public function loadString($value)
    {
        $this->data = json_decode($value, true) ?: [];
    }
}

final class JemHelper
{
    public static Registry $settings;
    public static function globalattribs()
    {
        return self::$settings;
    }
}

final class Text
{
    public static function _($key)
    {
        return $key;
    }
    public static function sprintf($key, ...$values)
    {
        return $key . implode(',', $values);
    }
}

final class Factory
{
    public static Database $db;
    public static User $user;
    public static array $messages = [];
    public static function getContainer()
    {
        return new self();
    }
    public function get($key)
    {
        return self::$db;
    }
    public static function getApplication()
    {
        return new self();
    }
    public function getIdentity()
    {
        return self::$user;
    }
    public function enqueueMessage($message, $type)
    {
        self::$messages[] = [$message, $type];
    }
    public static function getDate()
    {
        return new self();
    }
    public function toSql()
    {
        return '2026-10-16 12:00:00';
    }
}

final class User
{
    public int $id = 7;
    public bool $guest = false;
    public bool $edit = true;
    public function authorise($action, $asset)
    {
        return $this->edit;
    }
    public function get($key, $default = null)
    {
        return $this->$key ?? $default;
    }
}

final class Database
{
    public array $article = ['id' => 40, 'title' => 'Article title', 'introtext' => 'Article intro',
        'fulltext' => 'Article body', 'images' => '{"image_intro":"original.jpg"}',
        'created_by' => 7, 'checked_out' => 0, 'state' => 1];
    public array $writes = [];
    public function getQuery($new)
    {
        return $this;
    }
    public function quoteName($name)
    {
        return $name;
    }
    public function select($columns)
    {
        return $this;
    }
    public function from($table)
    {
        return $this;
    }
    public function where($clause)
    {
        return $this;
    }
    public function setQuery($query)
    {
        return $this;
    }
    public function loadAssoc()
    {
        return $this->article;
    }
    public function updateObject($table, $record, $key)
    {
        $this->writes[] = [$table, clone $record];
        if ($table === '#__content') {
            $this->article = array_replace($this->article, (array) $record);
        }
    }
}

class ModelDependencies
{
    public array $stored = [];
    public array $state = [];
    public string $error = '';
    public int $existingArticleId = 0;
    public array $createdText = [];
    public array $children = [];
    protected function getAssociatedArticleSyncEventData($id)
    {
        return $this->stored;
    }
    protected function prepareAssociatedArticleEventData($data, $id)
    {
        return $data;
    }
    protected function setState($key, $value)
    {
        $this->state[$key] = $value;
    }
    protected function setError($error)
    {
        $this->error = $error;
    }
    protected function buildAssociatedArticleTitle($event, $id)
    {
        return $event['title'];
    }
    protected function buildAssociatedArticleAlias($title, $id)
    {
        return 'event-' . $id;
    }
    protected function mergeAssociatedArticleImagesFromEvent($images, $event)
    {
        return '{"image_intro":"event.jpg"}';
    }
    protected function resolveAssociatedArticleCategory(...$args)
    {
        return 2;
    }
    protected function findAssociatedContentArticle(...$args)
    {
        return $this->existingArticleId;
    }
    protected function createAssociatedContentArticle($data, ...$args)
    {
        $this->createdText = $this->getAssociatedArticleInitialText($data);
        return 41;
    }
    protected function getRecurringEventChildrenWithoutArticle($id)
    {
        return $this->children;
    }
    protected function setAssociatedArticleForEvents($ids, $articleId)
    {
        foreach ($ids as $id) {
            Factory::$db->updateObject('#__jem_events', (object) ['id' => $id, 'article_id' => $articleId], 'id');
        }
    }
    protected function setAssociatedArticleCreationState(...$args) {}
    public function normalise(array $event, array $old): array
    {
        $this->normaliseAssociatedArticleSyncMode($event, $old);
        return $event;
    }
    public function sync(array $old, array $event): void
    {
        $this->stored = $event;
        $this->setAssociatedArticleSyncState($old, $event, 15);
    }
    public function create(array $event): bool
    {
        return $this->createAssociatedArticleIfRequested(15, $event, [2], 1, true);
    }
    public function recur(array $event): void
    {
        $this->applyRecurringAssociatedArticleMode(15, 40, $event, 2);
    }
}

class ContentDependencies
{
    public static ?object $article = null;
    public static function globalattribs()
    {
        return JemHelper::$settings;
    }
    public static function getAssociatedArticleForEventContent(...$args)
    {
        return self::$article;
    }
    public static function getAssociatedArticleImage($article)
    {
        return 'article.jpg';
    }
}

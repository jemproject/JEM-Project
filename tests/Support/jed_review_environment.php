<?php
declare(strict_types=1);

namespace Joomla\CMS {
    class Factory
    {
        public static object $app;
        public static object $db;
        public static function getApplication(): object
        {
            return self::$app;
        }
        public static function getContainer(): object
        {
            return new class {
                public function get(string $name): object
                {
                    return Factory::$db;
                }
            };
        }
    }
}
namespace Joomla\CMS\Language {
    class Text
    {
        public static function _(string $key): string
        {
            return $key;
        }
    }
}
namespace Joomla\CMS\Log {
    class Log
    {
        public const WARNING = 1;
        public static function add(...$arguments): void
        {
        }
    }
}
namespace Joomla\CMS\MVC\Controller {
    class BaseController
    {
        public array $redirect = [];
        public function setRedirect(...$arguments): void
        {
            $this->redirect = $arguments;
        }
    }
}
namespace Joomla\CMS\Response {
    class JsonResponse implements \Stringable
    {
        private array $response;
        public function __construct($data, $message, $error, $ignoreMessages)
        {
            $this->response = ['success' => !$error, 'data' => $data, 'message' => $message];
        }
        public function __toString(): string
        {
            return json_encode($this->response);
        }
    }
}
namespace {
    class JemHelper
    {
        public static bool $validToken = true;
        public static string $method = 'POST';
        public static function requirePostToken(): void
        {
            if (self::$method !== 'POST') {
                throw new \RuntimeException('Invalid method', 405);
            }
            if (!self::$validToken) {
                throw new \RuntimeException('Invalid request', 403);
            }
        }
    }
    class JemAclMigrationStatus
    {
        public static bool $isPending = false;
        public static function pending(): bool
        {
            return self::$isPending;
        }
    }
    class JedReviewQuery implements \Stringable
    {
        public string $sql = '';
        public function select(string $value): self
        {
            $this->sql = 'SELECT ' . $value;
            return $this;
        }
        public function from(string $value): self
        {
            $this->sql .= ' FROM ' . $value;
            return $this;
        }
        public function delete(string $value): self
        {
            $this->sql = 'DELETE FROM ' . $value;
            return $this;
        }
        public function where(string $value): self
        {
            $this->sql .= str_contains($this->sql, ' WHERE ') ? ' AND ' : ' WHERE ';
            $this->sql .= $value;
            return $this;
        }
        public function __toString(): string
        {
            return $this->sql;
        }
    }
    /** In-memory SQL persistence. FOR UPDATE is recorded, not simulated as a concurrency test. */
    class JedReviewDatabase
    {
        public \PDO $pdo;
        public array $queries = [];
        public bool $failInsert = false;
        private string $query;
        public function __construct()
        {
            $this->pdo = new \PDO('sqlite::memory:');
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
            $this->pdo->exec('INSERT INTO users VALUES (7), (8)');
            $this->pdo->exec('CREATE TABLE user_profiles (user_id INTEGER, profile_key TEXT, profile_value TEXT, ordering INTEGER, PRIMARY KEY (user_id, profile_key))');
            $this->pdo->exec("INSERT INTO user_profiles VALUES (7, 'profile.dob', '1990-01-01', 1)");
        }
        public function getQuery(bool $new): JedReviewQuery
        {
            return new JedReviewQuery();
        }
        public function quoteName(string $name): string
        {
            return '"' . str_replace('#__', '', $name) . '"';
        }
        public function quote(string $value): string
        {
            return $this->pdo->quote($value);
        }
        public function setQuery($query): self
        {
            $this->queries[] = (string) $query;
            $this->query = str_replace(' FOR UPDATE', '', (string) $query);
            return $this;
        }
        public function loadResult()
        {
            return $this->pdo->query($this->query)->fetchColumn();
        }
        public function execute(): void
        {
            $this->pdo->exec($this->query);
        }
        public function insertObject(string $table, object $row): void
        {
            if ($this->failInsert) {
                throw new \RuntimeException('Private database detail');
            }
            $statement = $this->pdo->prepare('INSERT INTO ' . $this->quoteName($table) . ' VALUES (?, ?, ?, ?)');
            $statement->execute(array_values((array) $row));
        }
        public function transactionStart(): void
        {
            $this->pdo->beginTransaction();
        }
        public function transactionCommit(): void
        {
            $this->pdo->commit();
        }
        public function transactionRollback(): void
        {
            $this->pdo->rollBack();
        }
    }
    class JedReviewApplication
    {
        public object $user;
        public object $input;
        public string $client = 'administrator';
        public bool $closed = false;
        public array $headers = [];
        public function __construct(string $action = 'dismiss', string $format = 'json', string $card = 'jed')
        {
            $this->user = new class {
                public int $id = 7;
                public bool $manage = true;
                public function authorise(string $action, string $asset): bool
                {
                    return $this->manage && $action === 'core.manage' && $asset === 'com_jem';
                }
            };
            $this->input = new class ($action, $format, $card) {
                public object $post;
                public function __construct(private string $action, private string $format, private string $card)
                {
                    $this->post = $this;
                }
                public function getString(string $key, string $default = ''): string
                {
                    return match ($key) {
                        'review_action' => $this->action,
                        'community_card' => $this->card,
                        default => $default,
                    };
                }
                public function getCmd(string $key): string
                {
                    return $this->format;
                }
            };
        }
        public function getIdentity(): object
        {
            return $this->user;
        }
        public function isClient(string $name): bool
        {
            return $this->client === $name;
        }
        public function setHeader(string $name, string $value, bool $replace): void
        {
            $this->headers[$name] = $value;
        }
        public function close(): void
        {
            $this->closed = true;
        }
    }
}

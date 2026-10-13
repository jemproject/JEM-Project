<?php

declare(strict_types=1);

namespace Joomla\CMS {
    class Factory
    {
        public static $database;
        public static $application;

        public static function getContainer(): object
        {
            return new class {
                public function get(string $name): object
                {
                    return Factory::$database;
                }
            };
        }

        public static function getApplication(...$arguments): object
        {
            return self::$application;
        }
    }
}

namespace Joomla\CMS\MVC\Model {
    class ItemModel
    {
        protected $_db;
        private string $error = '';

        public function setError($error): void
        {
            $this->error = (string) $error;
        }

        public function getError(): string
        {
            return $this->error;
        }

        public function getState($key, $default = null)
        {
            return $default;
        }
    }
}

namespace Joomla\CMS\Language {
    class Text
    {
        public static function _($key): string
        {
            return $key;
        }
    }
}

namespace Joomla\CMS\Filter {
    class OutputFilter
    {
        public static function cleanText($text): string
        {
            return strip_tags((string) $text);
        }
    }
}

namespace {
    class CancellationValues
    {
        public $post;

        public function __construct(public array $values = [])
        {
            $this->post = $this;
        }

        public function get($name, $default = null, ...$arguments)
        {
            return $this->values[$name] ?? $default;
        }

        public function getInt($name, $default = 0): int
        {
            return (int) $this->get($name, $default);
        }

        public function getString($name, $default = ''): string
        {
            return (string) $this->get($name, $default);
        }

        public function getAuthorisedViewLevels(): array
        {
            return [1, 2];
        }
    }

    class CancellationApplication
    {
        public array $messages = [];

        public function __construct(public CancellationValues $input)
        {
        }

        public function getIdentity(): object
        {
            return (object) JemFactory::$user->values;
        }

        public function enqueueMessage($message, $type): void
        {
            $this->messages[] = [$message, $type];
        }

        public function getLanguage(): object
        {
            return new class {
                public function getTag(): string
                {
                    return 'en-GB';
                }
            };
        }
    }

    class JemFactory
    {
        public static $user;

        public static function getUser(): object
        {
            return self::$user;
        }
    }

    class JemHelper
    {
        public static bool $cancellationOpen = true;

        public static function config(): object
        {
            return (object) ['showfroregistra' => 1, 'regallowinvitation' => 0];
        }

        public static function globalattribs(): object
        {
            return new CancellationValues();
        }

        public static function isContactComponentEnabled(): bool
        {
            return false;
        }

        public static function isEventPublishedNow($event): bool
        {
            return false;
        }

        public static function isEventRegistrationOpen($event): bool
        {
            return false;
        }

        public static function isEventUnregistrationOpen($event): bool
        {
            return self::$cancellationOpen;
        }

        public static function getStoredIP(): string
        {
            return '';
        }
    }

    class JemAgeAccess
    {
        public const UNRESTRICTED = 'unrestricted';
    }

    class JemLog
    {
        public static array $entries = [];
        public static bool $fail = false;

        public static function error($code, $message, $context): void
        {
            if (self::$fail) {
                throw new RuntimeException('Log unavailable');
            }
            self::$entries[] = [$code, $message, $context];
        }
    }

    class CancellationQuery
    {
        public array $parts = [];

        public function __call($name, $arguments): self
        {
            $this->parts[] = [$name, $arguments];

            return $this;
        }

        public function __toString(): string
        {
            return json_encode($this->parts);
        }
    }

    class CancellationDatabase
    {
        public array $events = [];
        public array $registrations = [];
        public string $failureStage = '';
        public int $failureEvent = 0;
        public int $registrationFailureEvent = 0;
        public $query;

        public function getQuery($new = false): object
        {
            if ($this->failureStage === 'query') {
                throw new RuntimeException('Sensitive SQL query details', 1146);
            }

            return new CancellationQuery();
        }

        public function quoteName($name, $alias = null): string
        {
            return $name . ($alias ? ' AS ' . $alias : '');
        }

        public function quote($value): string
        {
            return "'" . $value . "'";
        }

        public function setQuery($query, ...$arguments): self
        {
            $this->query = $query;
            if (is_string($query) && $this->failureStage === 'registration_prepare') {
                throw new RuntimeException('Sensitive registration preparation details', 1054);
            }
            if ($this->failureStage === 'prepare') {
                throw new RuntimeException('Sensitive SQL preparation details', 1054);
            }

            return $this;
        }

        public function loadResult(): int
        {
            return 0;
        }

        public function loadObjectList(): array
        {
            throw new RuntimeException('Sensitive series query details', 1054);
        }

        public function loadObject()
        {
            if (is_string($this->query)) {
                preg_match("/event = '(\d+)'/", $this->query, $match);
                $eventId = (int) ($match[1] ?? 0);
                if ($this->failureStage === 'registration_execute' || $eventId === $this->registrationFailureEvent) {
                    throw new RuntimeException('Sensitive registration execution details', 1146);
                }

                return isset($this->registrations[$eventId]) ? clone $this->registrations[$eventId] : null;
            }
            preg_match('/e.id = (\d+)/', (string) $this->query, $eventMatch);
            preg_match('/r.uid = (\d+)/', (string) $this->query, $userMatch);
            $eventId = (int) ($eventMatch[1] ?? 0);
            $userId = (int) ($userMatch[1] ?? 0);
            if ($this->failureStage === 'execute' || $eventId === $this->failureEvent) {
                throw new RuntimeException('Sensitive SQL execution details', 1146);
            }
            if ($userId !== 42 || !isset($this->registrations[$eventId], $this->events[$eventId])) {
                return null;
            }

            return clone $this->events[$eventId];
        }
    }

    class JemRegistrationService
    {
        public static array $writes = [];

        public function __construct($database)
        {
        }

        public function saveMany(array $rows, array $options): array
        {
            self::$writes[] = $rows;

            return array_map(static fn ($row) => (object) ['after' => $row], $rows);
        }
    }

    require_once JEM_TEST_ROOT . '/site/classes/registrationaccesspolicy.class.php';
    require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';
    require_once JEM_TEST_ROOT . '/site/classes/registrationquantity.class.php';
    require_once JEM_TEST_ROOT . '/site/models/event.php';

    class CancellationEventModel extends JemModelEvent
    {
        public bool $visible = true;
        public bool $realSeriesLoader = false;
        public bool $realRegistrationLoader = false;
        public array $series = [];

        public function __construct()
        {
            $this->_db = \Joomla\CMS\Factory::$database;
        }

        public function getItem($pk = null)
        {
            return $this->visible && isset($this->_db->events[$pk])
                ? clone $this->_db->events[$pk]
                : false;
        }

        public function getUserRegistration($eventId = null, $userid = 0, $failOnQueryError = false)
        {
            if ($this->realRegistrationLoader) {
                return parent::getUserRegistration($eventId, $userid, $failOnQueryError);
            }
            $userid = $userid ?: JemFactory::$user->get('id');

            return $userid === 42 ? ($this->_db->registrations[$eventId] ?? false) : false;
        }

        public function getListRecurrenceEventsbyId($id, $pk, $datetimeFrom, $iduser = null, $status = null, $failOnQueryError = false)
        {
            if ($this->realSeriesLoader) {
                return parent::getListRecurrenceEventsbyId($id, $pk, $datetimeFrom, $iduser, $status, $failOnQueryError);
            }

            return $this->series;
        }

        public function lookup(int $eventId, int $userId)
        {
            return $this->getOwnedCancellationEvent($eventId, $userId);
        }

        public function storeHiddenCancellation(&$error)
        {
            return $this->_doRegister(15, 42, '', -1, 0, '', $error, 91);
        }
    }
}

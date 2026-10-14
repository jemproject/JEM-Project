<?php

declare(strict_types=1);

// Only external UI/user/configuration services are replaced. The sample model,
// SQL, archive extraction, GD thumbnail generator and media journal are real.
namespace Joomla\CMS\Language {
    final class Text
    {
        public static function _($key, ...$args)
        {
            return $key;
        }

        public static function sprintf($key, ...$args)
        {
            return $key . ': ' . implode(', ', $args);
        }
    }
}

namespace {
    final class JemHelper
    {
        public static object $settings;
        public static array $logs = [];
        public static function config()
        {
            return self::$settings;
        }

        public static function getJoomlaDate()
        {
            return '2026-10-14';
        }

        public static function addLogEntry($message, ...$args)
        {
            self::$logs[] = $message;
        }

        public static function setEventUtcDates(&$event, $venueTimeZone = null)
        {
            $zone = $event->timezone_mode === 'custom' ? $event->timezone
                : ($event->timezone_mode === 'venue' && $venueTimeZone ? $venueTimeZone : 'Europe/Amsterdam');
            $event->start_utc = $event->dates ? (new DateTimeImmutable($event->dates . ' ' . ($event->times ?: '00:00:00'), new DateTimeZone($zone)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
            $event->end_utc = $event->dates ? (new DateTimeImmutable(($event->enddates ?: $event->dates) . ' ' . ($event->endtimes ?: '23:59:59'), new DateTimeZone($zone)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
        }
    }

    final class JemFactory
    {
        public static function getUser()
        {
            return new class {
                public function get($key)
                {
                    return 7;
                }
            };
        }
    }

    final class JemHelperBackend
    {
        public static function canManage($permission)
        {
            return true;
        }
    }

    final class JemConfig
    {
        public static function getInstance()
        {
            return new self();
        }

        public function toRegistry()
        {
            return new \Joomla\Registry\Registry(['defaultCurrency' => 'EUR']);
        }
    }

    final class SampleDataTestApplication
    {
        public array $messages = [];
        public function getConfig()
        {
            return new \Joomla\Registry\Registry(['tmp_path' => JPATH_ROOT . '/tmp']);
        }

        public function getLanguage()
        {
            return new class {
                public function getTag()
                {
                    return 'en-GB';
                }

                public function transliterate($text)
                {
                    return $text;
                }
            };
        }

        public function get($key)
        {
            return $key === 'tmp_path' ? JPATH_ROOT . '/tmp' : null;
        }

        public function enqueueMessage($message, $type = 'message')
        {
            $this->messages[] = [$type, $message];
        }

        public function getIdentity()
        {
            return new class {
                public function authorise($permission)
                {
                    return true;
                }
            };
        }
    }
}

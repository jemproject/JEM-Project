<?php

declare(strict_types=1);

namespace Joomla\CMS {
    final class Factory
    {
        public static function getApplication(): never
        {
            // Stop after the real view's access check, before rendering Joomla UI.
            throw new \VenuePickerAccessGranted();
        }

        public static function getContainer(): object
        {
            return new class {
                public function get(string $service): \VenuePickerDatabase
                {
                    return new \VenuePickerDatabase();
                }
            };
        }
    }
}

namespace Joomla\CMS\MVC\View {
    class HtmlView
    {
    }
}

namespace Joomla\CMS\Language {
    final class Text
    {
        public static function _(string $key): string
        {
            return $key;
        }
    }
}

namespace {
    final class VenuePickerAccessGranted extends \RuntimeException
    {
    }

    class JemAdminView extends \Joomla\CMS\MVC\View\HtmlView
    {
    }

    final class JemAclMigrationStatus
    {
        public static bool $isPending = false;

        public static function pending(): bool
        {
            return self::$isPending;
        }
    }

    final class JemFactory
    {
        public static object $user;

        public static function getUser(): object
        {
            return self::$user;
        }
    }

    final class JemHelperBackend
    {
        public static function can(string $type, string $operation): bool
        {
            $user = JemFactory::getUser();

            return JemBackendAclPolicy::allows($type, $operation, null, $user->id, $user->authorise(...));
        }
    }

    final class VenuePickerDatabase
    {
        public static array $publishedIds = [];
        public static bool $publishedOnly = false;

        public function getQuery(bool $new): self
        {
            return $this;
        }

        public function quoteName(string $name): string
        {
            return $name;
        }

        public function select(string $columns): self
        {
            return $this;
        }

        public function from(string $table): self
        {
            return $this;
        }

        public function where(string $condition): self
        {
            self::$publishedOnly = $condition === 'published = 1';

            return $this;
        }

        public function setQuery(self $query): void
        {
        }

        public function loadColumn(): array
        {
            return self::$publishedIds;
        }
    }
}

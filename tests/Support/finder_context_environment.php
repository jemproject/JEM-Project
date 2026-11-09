<?php

declare(strict_types=1);

namespace Joomla\CMS\Component {
    /** Replace component configuration reads without booting a database connection. */
    final class ComponentHelper
    {
        public static bool $enabled = true;

        /**
         * Supply the enabled state without querying extension records.
         * @param string $extension Requested component name.
         * @return bool Whether the synthetic JEM component is enabled.
         */
        public static function isEnabled($extension): bool
        {
            return self::$enabled;
        }

        /**
         * Supply language defaults for the native Finder result.
         * @param string $extension Requested component name.
         * @return \Joomla\Registry\Registry Component parameters.
         */
        public static function getParams($extension): \Joomla\Registry\Registry
        {
            return new \Joomla\Registry\Registry(['site' => 'en-GB']);
        }
    }
}

namespace Joomla\Component\Finder\Administrator\Indexer {
    /** Prevent third-party content callbacks and persistent index changes. */
    final class Helper
    {
        /**
         * Preserve the supplied text without dispatching content plugins.
         * @param mixed $text Text being indexed.
         * @param \Joomla\Registry\Registry $params Event parameters.
         * @return string Unmodified text.
         */
        public static function prepareContent($text, $params): string
        {
            return (string) $text;
        }

        /**
         * Suppress unrelated Finder enrichment callbacks.
         * @param Result $item Event being indexed.
         * @return void
         */
        public static function getContentExtras($item): void
        {
        }
    }
}

namespace {
    /** Capture article access context without looking up an associated article. */
    final class JemHelper
    {
        public static array $levels = [];

        /**
         * Record the access levels passed by the actual JEM index method.
         * @param \Joomla\Component\Finder\Administrator\Indexer\Result $item Event being indexed.
         * @param array<int> $levels Authorised article view levels.
         * @return void
         */
        public static function applyAssociatedArticleEventContent($item, $levels): void
        {
            self::$levels = $levels;
        }
    }

    /** Supply explicit view levels without loading a real Joomla user. */
    final class FinderContextUser extends \Joomla\CMS\User\User
    {
        /** @param array<int> $levels View levels to expose to the plugin. */
        public function __construct(private array $levels)
        {
        }

        /** @return array<int> The identity's existing authorised view levels. */
        public function getAuthorisedViewLevels(): array
        {
            return $this->levels;
        }
    }

    /** Expose the web capabilities that the plugin may use when a menu exists. */
    final class FinderContextWebApplication
    {
        public array $menuCalls = [];

        /** @param FinderContextUser|null $identity Web user, or an uninitialised identity. */
        public function __construct(private ?FinderContextUser $identity)
        {
        }

        /** @return FinderContextUser|null The current web identity. */
        public function getIdentity(): ?FinderContextUser
        {
            return $this->identity;
        }

        /**
         * Record menu access at the route-helper boundary.
         * @param string $client Requested menu client.
         * @return object Synthetic site menu.
         */
        public function getMenu($client): object
        {
            $this->menuCalls[] = $client;

            return (object) ['id' => 99];
        }
    }

    /** Observe menu routing; the native ConsoleApplication deliberately has no getMenu(). */
    final class JEMHelperRoute
    {
        public static array $calls = [];

        /**
         * Build a representative route using the web application's menu capability.
         * @param string $slug Event identifier and alias.
         * @param string $category Category identifier and alias.
         * @return string Route retaining the supplied slugs and site menu.
         */
        public static function getEventRoute($slug, $category): string
        {
            self::$calls[] = [$slug, $category];
            $menu = \Joomla\CMS\Factory::getApplication()->getMenu('site');

            return 'index.php?option=com_jem&view=event&id=' . $slug
                . '&catid=' . $category . '&Itemid=' . $menu->id;
        }
    }

    /** Record the final result without persisting or clearing the installed Finder index. */
    final class FinderContextRecorder
    {
        public array $items = [];

        /**
         * Capture the result submitted by the actual JEM plugin.
         * @param \Joomla\Component\Finder\Administrator\Indexer\Result $item Prepared result.
         * @return void
         */
        public function index($item): void
        {
            $this->items[] = $item;
        }
    }

    require_once JEM_TEST_ROOT . '/plugins/plg_finder_jem/jem.php';
}

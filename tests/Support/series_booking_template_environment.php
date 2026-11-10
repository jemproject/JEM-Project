<?php

declare(strict_types=1);

namespace Joomla\CMS\Router {
    class Route
    {
        /** @return string Keep the local form route observable in rendered markup. */
        public static function _($url): string
        {
            return $url;
        }
    }
}

namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        /** @return string Token generation belongs to Joomla, outside this render test. */
        public static function _($key): string
        {
            return '';
        }
    }
}

namespace {
    /** Render either real event registration layout for a first-time participant. */
    final class SeriesBookingTemplateView
    {
        public bool $showRegForm = true;
        public bool $print = false;
        public bool $allowRegistration = true;
        public bool $allowAnnulation = true;
        public bool $isregistered = false;
        public $registereduser = null;
        public $registration = null;
        public array $registers = [];
        public object $item;
        public object $jemsettings;
        public object $user;
        public object $capacityRegistration;

        /** @param object $item Event configuration. */
        public function __construct(object $item)
        {
            $this->item = $item;
            $this->jemsettings = (object) ['regallowcomments' => 0];
            $this->user = (object) ['id' => 42];
            $this->capacityRegistration = (object) ['enabled' => false];
        }

        /** @return object The participant has no existing series reservations. */
        public function getModel($name): object
        {
            return new class {
                /** @return array No owned reservations on any occurrence yet. */
                public function getListRecurrenceEventsbyId(...$args): array
                {
                    return [];
                }
            };
        }

        /** @return string Separate cancellation and area templates are outside this check. */
        public function loadTemplate($name): string
        {
            return '';
        }

        /** @param string $layout Relative registration template. @return string Rendered HTML. */
        public function render(string $layout): string
        {
            ob_start();
            try {
                include JEM_TEST_ROOT . '/site/views/event/tmpl/' . $layout;
                return ob_get_contents();
            } finally {
                ob_end_clean();
            }
        }
    }
}

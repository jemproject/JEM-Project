<?php

declare(strict_types=1);

namespace Joomla\CMS {
    final class Factory
    {
        public static object $application;

        public static function getApplication(): object
        {
            return self::$application;
        }
    }
}

namespace Joomla\CMS\Helper {
    final class ModuleHelper
    {
        public static array $modules = [];

        public static function getModules(string $position): array
        {
            return $position === 'jem-menu-preview' ? self::$modules : [];
        }
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
    class JemView
    {
        public $params;
        public bool $displayed = false;

        public function display($tpl = null)
        {
            $this->displayed = true;
        }
    }

    final class ModulePreviewApplication
    {
        public object $input;
        public object $active;
        public object $document;

        public function __construct(int $requestedId, int $menuId)
        {
            $this->input = new class ($requestedId) {
                public function __construct(private int $id)
                {
                }

                public function getInt(string $key, int $default = 0): int
                {
                    return $key === 'module_id' ? $this->id : $default;
                }
            };
            $this->active = (object) array(
                'query' => array('option' => 'com_jem', 'view' => 'modulepreview', 'module_id' => $menuId),
                'title' => 'Module layout',
            );
            $this->document = new class {
                public string $title = '';

                public function setTitle(string $title): void
                {
                    $this->title = $title;
                }
            };
        }

        public function getMenu(): object
        {
            return new class ($this->active) {
                public function __construct(private object $active)
                {
                }

                public function getActive(): object
                {
                    return $this->active;
                }
            };
        }

        public function getParams(): object
        {
            return (object) array();
        }

        public function getDocument(): object
        {
            return $this->document;
        }
    }
}

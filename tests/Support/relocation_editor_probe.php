<?php
/** Render the real editor partial for isolated browser interaction tests. */
namespace Joomla\CMS\Language {
    final class Text {
        public static function _($key) { static $labels; $labels ??= parse_ini_file($GLOBALS['root'] . '/admin/language/en-GB/com_jem.ini'); return $labels[$key] ?? $key; }
    }
}
namespace Joomla\CMS {
    final class Factory {
        public static function getContainer() { return new class { public function get($id) { return new class { public function setQuery($sql) { return $this; } public function loadResult() { return 1; } }; } }; }
        public static function getApplication() { return new class { public function getIdentity() { return new class { public function authorise($permission, $asset) { return true; } }; } }; }
    }
}
namespace {
    define('_JEXEC', 1);
    $GLOBALS['root'] = $argv[1];
    final class JemHelperBackend { public static function canManage($permission) { return true; } }
    $view = new class {
        public object $row;
        public object $capacity;
        public bool $commerceReadOnly = false;
        public function render() {
            $this->row = (object) ['id' => 41, 'status' => 1, 'waiting' => 0, 'revision' => 1];
            $this->capacity = (object) ['enabled' => true];
            require $GLOBALS['root'] . '/admin/views/attendee/tmpl/relocation.php';
        }
    };
    $view->render();
}

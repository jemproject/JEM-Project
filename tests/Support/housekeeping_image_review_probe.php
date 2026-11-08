<?php
/** Exercise real controller methods and templates with isolated Joomla request boundaries. */
declare(strict_types=1);

namespace Joomla\CMS {
    class Factory { public static object $application; public static function getApplication(): object { return self::$application; } }
}
namespace Joomla\CMS\Language {
    class Text {
        public static array $strings = [];
        public static function _($key): string { return self::$strings[$key] ?? $key; }
        public static function sprintf($key, ...$args): string { return sprintf(self::_($key), ...$args); }
    }
}
namespace Joomla\CMS\Router { class Route { public static function _($url): string { return $url; } } }
namespace Joomla\CMS\Uri { class Uri { public static function root($pathOnly = false): string { return ''; } } }
namespace Joomla\CMS\HTML {
    class HTMLHelper {
        public static function _($name, ...$args): string {
            return $name === 'form.token' ? '<input type="hidden" name="csrf" value="test">' : '';
        }
    }
}
namespace {
    define('_JEXEC', 1);
    $source = $argv[1];
    $modern = is_file($source . '/admin/classes/housekeepingfiles.class.php');
    \Joomla\CMS\Language\Text::$strings = parse_ini_file($source . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
    class JemHousekeepingImageReferenceException extends \RuntimeException {}
    class JemHelperBackend {
        public static bool $allowed = true;
        public static function canManage($permission): bool { return self::$allowed && $permission === 'jem.tools.manage'; }
    }
    class JemHelper {
        public static bool $token = true;
        public static bool $nonce = true;
        public static array $purposes = [];
        public static function requirePostToken(): void { if (!self::$token) { throw new \RuntimeException('Token rejected', 403); } }
        public static function consumeActionNonce($purpose, $nonce): bool {
            self::$purposes[] = $purpose;
            $valid = self::$nonce && $nonce === 'review-token';
            self::$nonce = false;
            return $valid;
        }
    }
    class JemHousekeepingFiles {
        public function __construct($db, private object $model) {}
        public static function areas(): array { return ['events', 'venues', 'categories', 'attachments']; }
        public function delete($area, $selected): array { return $this->model->deleteSelectedImages(1, $selected); }
    }
    $input = new class {
        public array $data = [];
        public function getCmd($key, $default = '') { return $this->data[$key] ?? $default; }
        public function getInt($key, $default = 0): int { return (int) ($this->data[$key] ?? $default); }
        public function getString($key, $default = ''): string { return (string) ($this->data[$key] ?? $default); }
        public function get($key, $default, $filter) { return $this->data[$key] ?? $default; }
    };
    \Joomla\CMS\Factory::$application = new class ($input) {
        public object $input;
        public function __construct(object $post) { $this->input = (object) ['post' => $post]; }
        public function setUserState($name, $value): void {}
    };
    $text = file_get_contents($source . '/admin/controllers/housekeeping.php');
    $text = str_replace("\r\n", "\n", $text);
    $methods = '';
    foreach (['allowHousekeeping', 'delete', $modern ? 'deleteUnusedFiles' : 'deleteReviewedImages'] as $name) {
        if (!preg_match('/^    (?:public|protected) function ' . $name . '\([^\n]*\n.*?^    }/ms', $text, $match)) {
            throw new \RuntimeException('Controller method missing: ' . $name);
        }
        $methods .= $match[0];
    }
    eval('use Joomla\\CMS\\Factory; use Joomla\\CMS\\Language\\Text; class ReviewController2478 {'
        . 'public object $model; public array $redirects = [];'
        . 'public function getModel($name) { return $this->model; }'
        . 'public function setRedirect(...$args) { $this->redirects[] = $args; }' . $methods . '}');
    $results = [];
    foreach (['open_events', 'open_venues', 'open_categories', 'unknown_task', 'success', 'empty', 'bad_nonce', 'permission',
        'csrf', 'references', 'invalid_selection', 'replay'] as $case) {
        JemHelper::$token = $case !== 'csrf';
        JemHelper::$nonce = true;
        JemHelper::$purposes = [];
        JemHelperBackend::$allowed = $case !== 'permission';
        $task = ['open_events' => 'cleaneventimg', 'open_venues' => 'cleanvenueimg', 'open_categories' => 'cleancategoryimg'][$case] ?? 'invalid';
        $input->data = ['task' => 'housekeeping.' . $task, 'type' => 1, 'area' => 'events', 'images_nonce' => 'review-token',
            'files_nonce' => 'review-token', 'images' => ['selection'], 'files' => ['selection']];
        if ($case === 'bad_nonce') { $input->data['images_nonce'] = $input->data['files_nonce'] = 'wrong'; }
        if ($case === 'empty') { $input->data['images'] = $input->data['files'] = []; }
        $controller = new ReviewController2478();
        $controller->model = new class ($case) {
            public int $calls = 0;
            public function __construct(private string $case) {}
            public function deleteSelectedImages($type, $selected): array {
                $this->calls++;
                if ($this->case === 'references') { throw new JemHousekeepingImageReferenceException('Unverified <image> references'); }
                if (!$selected || $this->case === 'invalid_selection') { throw new \InvalidArgumentException('Selection rejected'); }
                return ['deleted' => 1, 'skipped' => 0, 'failed' => 0, 'folders' => 0, 'lines' => []];
            }
        };
        $code = 0;
        try {
            if (str_starts_with($case, 'open_') || $case === 'unknown_task') {
                $controller->delete();
            } else {
                $method = $modern ? 'deleteUnusedFiles' : 'deleteReviewedImages';
                $controller->$method();
                if ($case === 'replay') { $controller->$method(); }
            }
        } catch (\Throwable $error) {
            $code = $error->getCode();
        }
        $results[$case] = ['calls' => $controller->model->calls, 'redirects' => $controller->redirects,
            'code' => $code, 'purposes' => JemHelper::$purposes];
    }
    $html = [];
    foreach (['normal', 'error', 'empty'] as $case) {
        $candidates = $case === 'normal' ? [['id' => str_repeat('a', 64), 'relative' => 'orphan <photo>.png', 'size' => 1, 'modified' => 0,
            'url' => 'images/jem/events/orphan.png', 'elsewhere' => [], 'kind' => 'original', 'thumbnail' => '']] : [];
        $view = new class {
            public int $imagesType = 1;
            public string $imagesError = '';
            public array $imagesCandidates = [];
            public string $imagesNonce = 'review-token';
            public int $imagesBatchLimit = 200;
            public string $filesArea = 'events';
            public array $filesCounts = ['events' => 1];
            public array $filesCandidates = [];
            public array $filesErrors = [];
            public array $filesRun = [];
            public string $filesNonce = 'review-token';
            public int $filesBatchLimit = 200;
            public function render(string $path): string { ob_start(); require $path; return ob_get_clean(); }
        };
        $view->imagesCandidates = $view->filesCandidates = $candidates;
        if ($case === 'error') {
            $view->imagesError = 'Unverified <image> references';
            $view->filesErrors = ['events' => $view->imagesError];
        }
        $html[$case] = $view->render($source . '/admin/views/housekeeping/tmpl/' . ($modern ? 'files' : 'images') . '.php');
    }
    echo json_encode(['modern' => $modern, 'cases' => $results, 'html' => $html], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
}

<?php
/** Render the actual card template with isolated language/router/token adapters; no Joomla writes. */
declare(strict_types=1);

namespace Joomla\CMS\Language {
    class Text
    {
        public static array $strings;
        public static function _(string $key): string
        {
            return self::$strings[$key] ?? $key;
        }
    }
}
namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        public static function _(string $name, ...$arguments): string
        {
            if ($name === 'image') {
                $class = htmlspecialchars((string) ($arguments[2]['class'] ?? ''), ENT_QUOTES, 'UTF-8');
                $alt = htmlspecialchars((string) $arguments[1], ENT_QUOTES, 'UTF-8');
                return '<img src="/images/' . basename($arguments[0]) . '" alt="' . $alt . '" class="' . $class . '">';
            }
            return '<input type="hidden" name="fixture_token" value="1">';
        }
    }
}
namespace Joomla\CMS\Router {
    class Route
    {
        public static function _(string $url): string
        {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }
    }
}
namespace {
    define('_JEXEC', 1);
    $root = dirname(__DIR__, 3);
    require $root . '/admin/helpers/jedreview.php';
    \Joomla\CMS\Language\Text::$strings = parse_ini_file($root . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
    $view = new class {
        protected bool $jedReviewAvailable = true;
        protected array $communityCardDismissed = [];
        public function render(bool $donate, bool $jed): string
        {
            $this->communityCardDismissed = ['donate' => $donate, 'jed' => $jed];
            ob_start();
            echo '<div class="cpanel jem-wei-menus"><h3>Miscellaneous</h3><div class="jem-wei-group">';
            echo '<div><div class="icon"><a href="#help" style="display:flex;flex-direction:column;align-items:center"><img src="/images/icon-48-help.svg" alt="" style="width:65px"><span>Help</span></a></div></div>';
            include dirname(__DIR__, 3) . '/admin/views/main/tmpl/default_communitybuttons.php';
            echo '</div></div>';
            include dirname(__DIR__, 3) . '/admin/views/main/tmpl/default_review.php';
            return ob_get_clean();
        }
    };
    $cases = [];
    foreach ([false, true] as $donate) {
        foreach ([false, true] as $jed) {
            $cases[(int) $donate . ':' . (int) $jed] = $view->render($donate, $jed);
        }
    }
    echo json_encode($cases, JSON_THROW_ON_ERROR);
}

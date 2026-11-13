<?php

declare(strict_types=1);

namespace JemProject\Tests\ReservationStatus;

/** Translation boundary; templates and JEM status helpers remain production code. */
final class Text
{
    public static array $strings = [];

    /** @param string $key Language key. @return string Loaded translation or key. */
    public static function _(string $key): string
    {
        return self::$strings[$key] ?? $key;
    }
}

final class ArrayHelper
{
    /** @param array $values Lookup. @param mixed $key Key. @param mixed $default Fallback. @return mixed */
    public static function getValue(array $values, $key, $default = null): mixed
    {
        return $values[$key] ?? $default;
    }
}

/** Only unrelated date, header and CSRF rendering is replaced. */
final class HTMLHelper
{
    /** @param string $key Rendering operation. @param mixed ...$args Inputs. @return string Markup. */
    public static function _(string $key, ...$args): string
    {
        return match ($key) {
            'date' => '2026-11-13',
            'grid.sort' => Text::_($args[0]),
            'form.token' => '',
            default => throw new \RuntimeException('Unexpected helper: ' . $key),
        };
    }
}

final class Route
{
    /** @param string $url Fixture link. @return string Escaped link without an installed router. */
    public static function _(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

final class Uri
{
    /** @param bool $pathOnly Whether only the path is requested. @return string Fixture site base. */
    public static function base(bool $pathOnly = false): string
    {
        return '';
    }
}

/** The event-status notice is independent of reservation/notification labels. */
final class JemOutput
{
    /** @param object $event Event fixture. @return string No unrelated notice markup. */
    public static function eventStatusNotice(object $event): string
    {
        return '';
    }
}

final class JemHelperBackend
{
    /** @param string $action Navigation permission. @return bool Hide unrelated navigation links. */
    public static function canManage(string $action): bool
    {
        return false;
    }
}

/** Real helpers loaded into a test namespace to avoid global test-suite class collisions. */
foreach (['admin/helpers/html/jemhtml.php', 'site/classes/registrationtransition.class.php'] as $file) {
    $code = file_get_contents(JEM_TEST_ROOT . '/' . $file);
    $code = str_replace('use Joomla\\CMS\\Language\\Text;', '', $code);
    $code = str_replace('use Joomla\\Utilities\\ArrayHelper;', '', $code);
    eval('namespace ' . __NAMESPACE__ . ';' . substr($code, 5));
}

/** Render the actual templates with supplied records and isolated Joomla boundaries. */
final class View
{
    public object $item;
    public array $notifications = [];
    public array $items = [];
    public array $states = [];
    public array $types = ['registration.confirmed'];
    public array $languages = ['en-GB'];
    public $latestSent = null;
    public $resendPolicy = null;
    public bool $canResend = true;
    public bool $canNotificationResend = true;
    public object $pagination;
    public object $state;

    /** @param string $selected Selected filter code. */
    public function __construct(string $selected = '')
    {
        $this->pagination = new class {
            /** @return string No pagination required for fixture rows. */
            public function getListFooter(): string
            {
                return '';
            }
        };
        $this->state = new class ($selected) {
            /** @param string $selected Selected filter code. */
            public function __construct(private string $selected)
            {
            }

            /** @param string $key Filter name. @return string Fixture filter value. */
            public function get(string $key): string
            {
                return $key === 'filter_state' ? $this->selected : '';
            }
        };
    }

    /** @param mixed $value Output value. @return string HTML-escaped text. */
    public function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** @param bool $backend Select notification history or reservation details. @return \DOMXPath Rendered document. */
    public function render(bool $backend = false): \DOMXPath
    {
        $path = $backend ? '/admin/views/notificationhistory/tmpl/default.php' : '/site/views/registration/tmpl/default.php';
        $code = file_get_contents(JEM_TEST_ROOT . $path);
        foreach (['HTML\\HTMLHelper', 'Language\\Text', 'Router\\Route'] as $class) {
            $code = str_replace('use Joomla\\CMS\\' . $class . ';', '', $code);
        }
        $code = str_replace('\\Joomla\\CMS\\Uri\\Uri', 'Uri', $code);
        return $this->renderCode($code);
    }

    /** @return \DOMXPath Actual attendee notification table, excluding unrelated edit-form controls. */
    public function renderAttendee(): \DOMXPath
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/views/attendee/tmpl/default.php');
        $start = strpos($source, '<h4 class="mt-4"><?php echo Text::_(\'COM_JEM_NOTIFICATION_HISTORY\');');
        $closing = '</tbody></table></div>';
        $end = $start === false ? false : strpos($source, $closing, $start);
        if ($start === false || $end === false) {
            throw new \RuntimeException('Attendee notification table boundaries changed.');
        }

        return $this->renderCode('<?php ?>' . substr($source, $start, $end + strlen($closing) - $start));
    }

    /** @param string $code Native template source. @return \DOMXPath Rendered markup for assertions. */
    private function renderCode(string $code): \DOMXPath
    {
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . ';' . substr($code, 5));
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        return new \DOMXPath($document);
    }
}

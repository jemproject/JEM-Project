<?php
/** Isolated services for production attendee guards and native Joomla routing. */
declare(strict_types=1);

namespace JemProject\Tests\AttendeeDenial;

use Joomla\CMS\Factory;
use Joomla\Input\Input;

final class ReachedBoundary extends \Exception
{
}

class Boundary
{
    /** @param mixed ...$args Model arguments. @return never Stop before data access. */
    public function getModel(...$args): never
    {
        throw new ReachedBoundary('model');
    }
}

final class Application
{
    public array $messages = [];
    public ?string $redirect = null;
    public Input $input;

    /** Initialize a synthetic action request; no installed session or database is used. */
    public function __construct()
    {
        $this->input = new Input(['layout' => 'pdf', 'registration_id' => 99,
            'registration_ids' => [99], 'batch_action' => 'promote']);
    }

    /** @return string Frontend routing client. */
    public function getName(): string
    {
        return 'site';
    }

    /** @param string $text Message. @param string $type Severity. @return void */
    public function enqueueMessage($text, $type): void
    {
        $this->messages[] = [$text, $type];
    }

    /** @param string $url Destination. @return void Capture without emitting HTTP headers. */
    public function redirect($url): void
    {
        $this->redirect = $url;
    }

    /** @return never Simulate application termination. */
    public function close(): never
    {
        throw new ReachedBoundary('closed');
    }

    /** @return never Stop the authorized HTML view before rendering. */
    public function getDocument(): never
    {
        throw new ReachedBoundary('document');
    }

    /** @return never Stop the authorized PDF view before rendering. */
    public function getParams(): never
    {
        throw new ReachedBoundary('params');
    }
}

final class User
{
    public int $id = 12;
    public int $mask = 0;

    /** @param string $key User property. @return int Synthetic identity. */
    public function get($key): int
    {
        return $this->id;
    }

    /** @param string $permission Permission. @param string $asset Asset. @return bool Explicit fixture grant. */
    public function authorise($permission, $asset): bool
    {
        if ($asset !== 'com_jem') {
            throw new \RuntimeException('Unexpected permission asset');
        }
        return (bool) ($this->mask & ['core.admin' => 1, 'core.manage' => 2,
            'jem.events.access' => 4, 'jem.attendees.manage' => 8][$permission]);
    }
}

final class JemFactory
{
    public static User $user;

    /** @return User Synthetic user; production permission expressions are not replaced. */
    public static function getUser(): User
    {
        return self::$user;
    }
}

final class Text
{
    /** @param string $key Translation key. @return string Key for precise message assertions. */
    public static function _($key): string
    {
        return $key;
    }
}

final class Session
{
    public static int $checks = 0;

    /** @return bool Simulate a valid CSRF token and record that the production task checks it. */
    public static function checkToken(): bool
    {
        ++self::$checks;
        return true;
    }
}

/**
 * Load actual method bodies, retaining their visibility and isolating service boundaries.
 *
 * @param string $file Source file.
 * @param array<string> $names Methods.
 * @return string Unmodified production declarations.
 */
function methods(string $file, array $names): string
{
    $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
    $result = '';
    foreach ($names as $name) {
        if (!preg_match('/    (?:public|private|protected) function ' . preg_quote($name, '/')
            . '\([^\n]*\)[^\n]*\n    \{.*?^    \}/ms', $source, $match)) {
            throw new \RuntimeException('Cannot extract ' . $file . ':' . $name);
        }
        $result .= $match[0] . "\n";
    }
    return $result;
}

/** @return void Load checkout guards and task bodies without booting Joomla or any plugins. */
function loadGuards(): void
{
    $imports = 'namespace ' . __NAMESPACE__ . '; use Joomla\CMS\Factory; use Joomla\CMS\Router\Route;'
        . ' use Joomla\CMS\Uri\Uri; use \Exception; use Joomla\CMS\Plugin\PluginHelper;';
    foreach (['HtmlView' => 'html', 'PdfView' => 'raw'] as $class => $format) {
        eval($imports . 'class ' . $class . ' extends Boundary {'
            . methods('site/views/attendeeregistrations/view.' . $format . '.php', ['display']) . '}');
    }
    eval($imports . 'class Controller extends Boundary {'
        . methods('site/controllers/attendeeregistrations.php',
            ['assertCanManageAttendeeRegistrations', 'setstatus', 'renotify', 'batch']) . '}');
}


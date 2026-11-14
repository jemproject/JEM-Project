<?php
/** Isolated request/session and persistence boundaries for the actual attendee controller. */
declare(strict_types=1);

namespace JemProject\Tests\AttendeeForm;

use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\Input\Input;

final class Application
{
    public Input $input;
    public array $messages = [];
    public array $state = [];
    public int $actor = 7;

    /** @param array $post Synthetic POST request. */
    public function __construct(array $post = [])
    {
        $this->input = new Input($post);
        (new \ReflectionProperty(Input::class, 'inputs'))->setValue($this->input, ['post' => new Input($post)]);
    }
    /** @return Input Request used by native Joomla controller discovery. */
    public function getInput(): Input { return $this->input; }
    /** @return object Current authenticated test administrator. */
    public function getIdentity(): object { return (object) ['id' => $this->actor]; }
    /** @return object Loaded Joomla language. */
    public function getLanguage(): object { return JoomlaFactory::$language; }
    /** @return array Current queue. */
    public function getMessageQueue(): array { return $this->messages; }
    /** @param string $message Text. @param string $type Severity. @return void */
    public function enqueueMessage($message, $type): void { $this->messages[] = ['message' => $message, 'type' => $type]; }
    /** @param string $key State key. @param mixed $default Fallback. @return mixed Session value. */
    public function getUserState($key, $default = null) { return $this->state[$key] ?? $default; }
    /** @param string $key State key. @param mixed $value Session value. @return void */
    public function setUserState($key, $value): void { $this->state[$key] = $value; }
}

final class Model
{
    public bool $success = false;
    public mixed $error = 'Choose an admission type.';
    public ?string $queued = null;
    public array $stored = [];
    public ?object $old = null;
    /** @param array $post Submitted record. @return object|false Controlled persistence result. */
    public function store($post)
    {
        $this->stored[] = $post;
        if ($this->queued !== null) {
            JoomlaFactory::getApplication()->enqueueMessage($this->queued, 'warning');
        }
        return $this->success ? (object) array_replace($post, ['id' => 22]) : false;
    }
    /** @return mixed Simulated model error. */
    public function getError() { return $this->error; }
    /** @param int $id Requested row. @return void */
    public function setId($id): void {}
    /** @return object|null Original persisted row, not a form draft. */
    public function getData(): ?object { return $this->old; }
}

final class Session
{
    /** @return bool This fixture represents an authenticated POST. */
    public static function checkToken(): bool { return true; }
}

final class Factory
{
    /** @return object Same application used by the real helper. */
    public static function getApplication(): object { return JoomlaFactory::getApplication(); }
    /** @param string $group Cache group. @return object No-op cache boundary. */
    public static function getCache($group): object { return new class { public function clean(): void {} }; }
}

final class PluginHelper
{
    /** @param mixed ...$args Plugin selection. @return void No actual plugins loaded. */
    public static function importPlugin(...$args): void {}
}

final class Dispatcher
{
    public static array $events = [];
    /** @return self Dispatcher fixture. */
    public static function getDispatcher(): self { return new self(); }
    /** @param string $name Event name. @param array $args Payload. @return void */
    public function triggerEvent($name, $args): void { self::$events[] = $name; }
}

final class Table
{
    /** @param mixed ...$args Table selection. @return object Bind/checkin without SQL. */
    public static function getInstance(...$args): object
    {
        return new class {
            public int $id = 0;
            public int $event = 0;
            public function bind($post): void { $this->id = (int) ($post['id'] ?? 0); $this->event = (int) ($post['event'] ?? 0); }
            public function checkin(): void {}
        };
    }
}

/** Extract unchanged production methods into a harness with isolated side effects. */
function loadController(): void
{
    require_once JEM_TEST_ROOT . '/admin/controllers/attendee.php';
    require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';
    $code = '';
    foreach (['save', 'cancel'] as $name) {
        $method = new \ReflectionMethod('JemControllerAttendee', $name);
        $code .= implode('', array_slice(file($method->getFileName()), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
    eval('namespace JemProject\\Tests\\AttendeeForm;
        use Joomla\\CMS\\Language\\Text;
        use JemAttendeeFormHelper;
        use JemRegistrationTransition;
        use JemProject\\Tests\\AttendeeForm\\Dispatcher as JemFactory;
        class Controller {
            public Model $model;
            public array $redirect = [];
            private function assertCanManageAttendees(): void {}
            public function getModel($name) { return $this->model; }
            public function setRedirect($url, $message = "", $type = "message") { $this->redirect = [$url, $message, $type]; }
        ' . $code . '}');
}

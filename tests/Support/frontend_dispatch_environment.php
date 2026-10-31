<?php

declare(strict_types=1);

namespace JemProject\Tests\Support\FrontendDispatch;

use stdClass;

/** Minimal Joomla service boundary; the real JEM controller is loaded unchanged. */
final class Application
{
    public object $input;
    public object $document;

    /** @param array<string, string> $request Request fields. @param string $format Document format. */
    public function __construct(array $request, string $format)
    {
        $this->input = new class ($request) {
            /** @param array<string, string> $request Request fields. */
            public function __construct(private array $request)
            {
            }

            /** @param string $key Field name. @param string $default Fallback. @return string Command. */
            public function getCmd(string $key, string $default = ''): string
            {
                return $this->request[$key] ?? $default;
            }
        };
        $this->document = new class ($format) {
            /** @param string $format Document format. */
            public function __construct(private string $format)
            {
            }

            /** @return string Requested document format. */
            public function getType(): string
            {
                return $this->format;
            }
        };
    }

    /** @return object Current document. */
    public function getDocument(): object
    {
        return $this->document;
    }
}

/** Supply the current test application to the production controller. */
final class Factory
{
    public static Application $app;

    /** @return Application Current application. */
    public static function getApplication(): Application
    {
        return self::$app;
    }
}

/** Record model selection and view rendering without changing dispatch logic. */
class ControllerBoundary
{
    public array $modelRequests = [];
    public array $viewRequest = [];
    public ViewBoundary $view;

    /** Initialize the observing view. */
    public function __construct()
    {
        $this->view = new ViewBoundary();
    }

    /** @param string $name Requested model name. @return object Model identity. */
    public function getModel(string $name): object
    {
        $this->modelRequests[] = $name;

        return (object) ['name' => $name];
    }

    /** @param string $name View name. @param string $format Document format. @return ViewBoundary View. */
    public function getView(string $name, string $format): ViewBoundary
    {
        $this->viewRequest = [$name, $format];

        return $this->view;
    }
}

/** Observe the default model, selected layout and display invocation. */
final class ViewBoundary
{
    public ?object $model = null;
    public ?object $document = null;
    public string $layout = '';
    public bool $displayed = false;

    /** @param object $model Model identity. @param bool $default Select as default. @return void */
    public function setModel(object $model, bool $default = false): void
    {
        if ($default) {
            $this->model = $model;
        }
    }

    /** @param string $layout Requested layout. @return void */
    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    /** @return void Record that controller dispatch reached rendering. */
    public function display(): void
    {
        $this->displayed = true;
    }
}

/** Guest identity for non-editor view dispatch. */
final class UserFactory
{
    /** @return stdClass Guest identity; no editor permissions are exercised here. */
    public static function getUser(): stdClass
    {
        return new stdClass();
    }
}

/** Observe the controller's common asset initialization. */
final class Helper
{
    public static int $iconLoads = 0;

    /** @return void Count icon-font initialization requests. */
    public static function loadIconFont(): void
    {
        ++self::$iconLoads;
    }
}

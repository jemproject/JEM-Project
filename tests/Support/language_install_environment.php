<?php
declare(strict_types=1);

namespace Joomla\CMS {
    class Factory
    {
        public static object $app;
        public static function getApplication(): object
        {
            return self::$app;
        }
    }
}
namespace Joomla\CMS\Language {
    class Text
    {
        public static function _(string $key): string
        {
            return $key;
        }
        public static function sprintf(string $key, ...$values): string
        {
            return $key . ' ' . implode(' ', $values);
        }
    }
}
namespace Joomla\CMS\MVC\Controller {
    class BaseController
    {
        public array $redirect = [];
        public function setRedirect(...$args): void
        {
            $this->redirect = $args;
        }
    }
}
namespace Joomla\CMS\Installer {
    class Installer
    {
        public static ?self $instance = null;
        public bool $upgrade = false;
        public bool $overwrite = false;
        public bool $result = true;
        public int $installs = 0;
        public static function getInstance(): self
        {
            return self::$instance ??= new self();
        }
        public function setUpgrade(bool $value): void
        {
            $this->upgrade = $value;
        }
        public function setOverwrite(bool $value): void
        {
            $this->overwrite = $value;
        }
        public function install(string $directory): bool
        {
            $this->installs++;
            return $this->result;
        }
    }
    class InstallerHelper
    {
        public static function unpack(string $path, bool $flag): array
        {
            return ['type' => 'file', 'dir' => '/fixture/package'];
        }
    }
}
namespace Joomla\Filesystem {
    class File
    {
        public static function write(string $path, string $body): bool
        {
            return true; // No filesystem mutation in controller orchestration tests.
        }
    }
    class Path
    {
        public static function clean(string $path): string
        {
            return $path;
        }
    }
}
namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        public static function _(string $name): string
        {
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
    class JemHelper
    {
        public static bool $validToken = true;
        public static function requirePostToken(): void
        {
            if (!self::$validToken) {
                throw new \RuntimeException('Invalid token', 403);
            }
        }
        public static function getParam(...$args): string
        {
            return '5.1.0';
        }
    }
    class LanguageInstallApplication
    {
        public bool $admin = true;
        public string $packageId = 'es-ES-test';
        public function getIdentity(): object
        {
            return $this;
        }
        public function authorise(string $permission): bool
        {
            return $permission === 'core.admin' && $this->admin;
        }
        public function getInput(): object
        {
            return $this;
        }
        public function getString(string $key): string
        {
            return $this->packageId;
        }
        public function get(string $key): string
        {
            return '/fixture/tmp';
        }
    }
}

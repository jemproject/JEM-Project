<?php

/**
 * Focused JEM 4.5.0 installer preflight test; run with: php tests/installer-upgrade-guard.php
 */

namespace Joomla\CMS {
    class Factory
    {
        public static object $application;
        public static object $database;

        public static function getApplication(): object
        {
            return self::$application;
        }

        public static function getContainer(): object
        {
            return new class {
                public function get(string $service): object
                {
                    return Factory::$database;
                }
            };
        }
    }
}

namespace Joomla\CMS\Language {
    class Text
    {
        public static function sprintf(string $key, ...$values): string
        {
            return $key . ': ' . implode(', ', $values);
        }

        public static function _(string $key): string
        {
            return $key;
        }
    }
}

namespace {
    define('_JEXEC', 1);
    define('JVERSION', '5.4.8');
    define('JPATH_ADMINISTRATOR', __DIR__);

    require dirname(__DIR__) . '/package/pkg_install.php';
    require dirname(__DIR__) . '/script.php';

    class TestQuery
    {
        public function select($value): self
        {
            return $this;
        }

        public function from($value): self
        {
            return $this;
        }

        public function where($value): self
        {
            return $this;
        }
    }

    class TestDatabase
    {
        public ?string $manifestCache = null;
        public bool $hasComponent = true;

        public function getQuery(bool $new): TestQuery
        {
            return new TestQuery();
        }

        public function setQuery($query): void
        {
        }

        public function loadAssoc(): ?array
        {
            return $this->hasComponent ? array('manifest_cache' => $this->manifestCache) : null;
        }

        public function loadResult(): ?string
        {
            return $this->manifestCache;
        }
    }

    class TestApplication
    {
        public array $messages = array();

        public function enqueueMessage(string $message, string $type): void
        {
            $this->messages[] = array($message, $type);
        }

        public function getLanguage(): object
        {
            return new class {
                public function load(...$arguments): void
                {
                }
            };
        }
    }

    class TestPackageInstaller extends Pkg_JemInstallerScript
    {
        public bool $requirementsChecked = false;

        public function checkRequirements()
        {
            $this->requirementsChecked = true;
            return true;
        }
    }

    function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    $database = new TestDatabase();
    $application = new TestApplication();
    \Joomla\CMS\Factory::$database = $database;
    \Joomla\CMS\Factory::$application = $application;

    foreach (array('2.3.0', '2.3.5', null) as $version) {
        $database->manifestCache = $version === null ? '{}' : json_encode(array('version' => $version));
        $application->messages = array();
        $installer = new TestPackageInstaller();
        check($installer->preflight('update', null) === false, 'Package accepted ' . ($version ?? 'unknown'));
        check(!$installer->requirementsChecked, 'Package ran later preflight checks for legacy JEM');
        check(count($application->messages) === 1, 'Package did not explain rejected upgrade');

        $component = new com_jemInstallerScript();
        check(
            $component->preflight('update', (object) array('manifest' => (object) array('version' => '4.5.0'))) === false,
            'Component accepted ' . ($version ?? 'unknown')
        );
        check(count($application->messages) === 2, 'Component did not explain rejected upgrade');
    }

    foreach (array('2.3.6', '4.4.0', '4.4.2', '4.5.0') as $version) {
        $database->manifestCache = json_encode(array('version' => $version));
        $installer = new TestPackageInstaller();
        check($installer->preflight('update', null) === true, 'Package rejected ' . $version);
        check($installer->requirementsChecked, 'Package skipped normal requirements for ' . $version);
    }

    $database->hasComponent = false;
    $installer = new TestPackageInstaller();
    check($installer->preflight('install', null) === true, 'Package rejected a fresh install');

    echo "Installer upgrade guard: passed.\n";
}

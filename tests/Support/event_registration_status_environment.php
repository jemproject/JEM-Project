<?php

declare(strict_types=1);

namespace Joomla\CMS\MVC\Model {
    /** Minimal model shell; the tested store/setStatus methods are loaded from production. */
    class BaseDatabaseModel
    {
        private string $error = '';
        /** @param string $error Public failure. @return void */
        public function setError($error): void { $this->error = (string) $error; }
        /** @return string Most recent public failure. */
        public function getError(): string { return $this->error; }
    }
}

namespace Joomla\CMS\Log {
    /** No installed log targets are used in the isolated model tests. */
    final class Log { public const ERROR = 3; }
}

namespace {
    /** The synthetic administrator uses the Ticketing profile in this fixture. */
    final class JemFeaturePolicy
    {
        public const FEATURE_PRICING = 'pricing';
        /** @return self Enabled test profile. */
        public static function current(): self { return new self(); }
        /** @param string $feature Tested feature. @return bool Whether enabled for the fixture. */
        public function allows($feature): bool { return $feature === self::FEATURE_PRICING; }
    }
}

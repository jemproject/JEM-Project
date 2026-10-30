<?php

declare(strict_types=1);

use JemProject\Tests\Support\PhpClassContract;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Negative fixtures ensure the JSON bridge exception cannot hide broken loaders.
 */
final class PhpClassContractTest extends TestCase
{
    private string $directory;

    /**
     * Create an isolated fixture directory; no production files are modified.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/jem-controller-bridge-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory));
    }

    /**
     * Remove only the two known fixture files and their unique directory.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach (['example.json.php', 'example.php'] as $name) {
            $path = $this->directory . '/' . $name;
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->directory);
    }

    /**
     * Check formatting variations without loading the controller implementation.
     *
     * @param string $bridge Source of a legitimate minimal bridge.
     * @return void
     */
    #[DataProvider('validBridges')]
    public function testAcceptsGuardedDelegation(string $bridge): void
    {
        $this->writeFixtures($bridge);
        $this->assertBridge();
    }

    /** @return iterable<string, array{string}> Supported bridge formatting. */
    public static function validBridges(): iterable
    {
        yield 'minimal' => [self::bridgeSource()];
        yield 'comments and whitespace' => ["<?php\n/** Bridge. */\ndefined('_JEXEC') or die;\n// Load target.\nrequire_once /* shared */ __DIR__ . '/example.php';\n"];
        yield 'double quotes and closing tag' => ['<?php defined("_JEXEC") or die; require_once __DIR__ . "/example.php"; ?>'];
    }

    /**
     * Reject malformed delegation even when its text mentions the correct target.
     *
     * @param string $bridge Invalid bridge source.
     * @return void
     */
    #[DataProvider('invalidBridges')]
    public function testRejectsInvalidBridge(string $bridge): void
    {
        $this->writeFixtures($bridge);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('must contain only the Joomla guard');
        $this->assertBridge();
    }

    /** @return iterable<string, array{string}> Invalid loader and guard cases. */
    public static function invalidBridges(): iterable
    {
        $guard = "<?php defined('_JEXEC') or die; ";
        $load = "require_once __DIR__ . '/example.php';";
        yield 'wrong target' => [$guard . "require_once __DIR__ . '/other.php';"];
        yield 'commented loader' => [$guard . '// ' . $load];
        yield 'quoted loader' => [$guard . var_export($load, true) . ';'];
        yield 'conditional loader' => [$guard . 'if (false) { ' . $load . ' }'];
        yield 'early return' => [$guard . 'return; ' . $load];
        yield 'missing guard' => ['<?php ' . $load];
        yield 'commented guard' => ["<?php /* defined('_JEXEC') or die; */ " . $load];
        yield 'include instead of require_once' => [$guard . "include __DIR__ . '/example.php';"];
        yield 'extra output' => [$guard . $load . ' echo "unexpected";'];
        yield 'non-PHP loader' => [$guard . ' ?>' . $load];
    }

    /**
     * Require a real class declaration, not a comment or a quoted class name.
     *
     * @param string $target Invalid target source.
     * @return void
     */
    #[DataProvider('invalidTargets')]
    public function testRejectsWrongTargetClass(string $target): void
    {
        $this->writeFixtures(self::bridgeSource(), $target);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('must declare JemControllerExample');
        $this->assertBridge();
    }

    /**
     * Apply the same real-declaration requirement to ordinary implementation files.
     *
     * @param string $target Invalid class source.
     * @return void
     */
    #[DataProvider('invalidTargets')]
    public function testOrdinaryClassContractRejectsInvalidDeclaration(string $target): void
    {
        $this->writeFixtures(self::bridgeSource(), $target);
        $this->expectException(AssertionFailedError::class);
        PhpClassContract::assertClass($this->directory . '/example.php', 'JemControllerExample');
    }

    /**
     * Helper declarations and PHP's case-insensitive names must not cause failures.
     *
     * @param string $target Valid implementation with auxiliary declarations.
     * @return void
     */
    #[DataProvider('validTargets')]
    public function testFindsExpectedClassAmongOtherDeclarations(string $target): void
    {
        $this->writeFixtures(self::bridgeSource(), $target);
        $this->assertBridge();
    }

    /** @return iterable<string, array{string}> Valid class ordering and naming cases. */
    public static function validTargets(): iterable
    {
        yield 'auxiliary exception first' => ['<?php class HelperException extends RuntimeException {} class JemControllerExample extends BaseController {}'];
        yield 'case insensitive name' => ['<?php class JemControllerEXAMPLE extends BaseController {}'];
        yield 'anonymous class first' => ['<?php $helper = new class extends BaseController {}; class JemControllerExample extends BaseController {}'];
    }

    /**
     * An unapproved JSON file is an ordinary class contract, not an exemption.
     *
     * @return void
     */
    public function testUnapprovedBridgeDoesNotSatisfyAClassContract(): void
    {
        $this->writeFixtures(self::bridgeSource());
        $this->expectException(AssertionFailedError::class);
        PhpClassContract::assertClass($this->directory . '/example.json.php', 'JemControllerExampleJson');
    }

    /**
     * Every discovered controller must have exactly one class or bridge test.
     *
     * @param string $client Administrator or site contract group.
     * @return void
     */
    #[DataProvider('controllerClients')]
    public function testControllerProvidersCoverEveryPhpFile(string $client): void
    {
        require_once JEM_TEST_ROOT . '/tests/Static/Code/' . $client . '/' . $client . 'CodeContractsTest.php';
        $class = $client . 'CodeContractsTest';
        $prefix = strtolower($client);
        $classProvider = $prefix . 'ControllerProvider';
        $bridgeProvider = $prefix . 'ControllerBridgeProvider';
        $covered = [];
        foreach ([$classProvider, $bridgeProvider] as $provider) {
            foreach ($class::$provider() as $case) {
                $covered[] = str_replace('\\', '/', $case[0]);
            }
        }
        $expected = array_map(
            static fn (string $path): string => str_replace('\\', '/', $path),
            glob(JEM_TEST_ROOT . '/' . $prefix . '/controllers/*.php') ?: []
        );
        sort($covered);
        sort($expected);
        self::assertNotEmpty($expected);
        self::assertSame($expected, $covered);
    }

    /** @return iterable<string, array{string}> Both legacy controller trees. */
    public static function controllerClients(): iterable
    {
        yield 'administrator' => ['Admin'];
        yield 'frontend' => ['Site'];
    }

    /** @return iterable<string, array{string}> Incorrect implementation declarations. */
    public static function invalidTargets(): iterable
    {
        yield 'wrong class' => ['<?php class JemControllerOther extends BaseController {}'];
        yield 'comment only' => ['<?php // class JemControllerExample extends BaseController {}'];
        yield 'string only' => ['<?php $text = "class JemControllerExample extends BaseController {}";'];
        yield 'no parent class' => ['<?php class JemControllerExample {}'];
        yield 'empty implementation' => ['<?php'];
    }

    /**
     * A registered bridge or target missing from the filesystem must still fail.
     *
     * @param string $missing Fixture file to omit.
     * @return void
     */
    #[DataProvider('missingFiles')]
    public function testRejectsMissingFile(string $missing): void
    {
        $this->writeFixtures(self::bridgeSource());
        unlink($this->directory . '/' . $missing);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Missing ');
        $this->assertBridge();
    }

    /** @return iterable<string, array{string}> Files whose presence is mandatory. */
    public static function missingFiles(): iterable
    {
        yield 'bridge' => ['example.json.php'];
        yield 'target' => ['example.php'];
    }

    /**
     * Store only test fixtures; classes are inspected as text and never loaded.
     *
     * @param string      $bridge Bridge source.
     * @param string|null $target Target source, or the valid default implementation.
     * @return void
     */
    private function writeFixtures(string $bridge, ?string $target = null): void
    {
        file_put_contents($this->directory . '/example.json.php', $bridge);
        file_put_contents(
            $this->directory . '/example.php',
            $target ?? "<?php defined('_JEXEC') or die; class JemControllerExample extends BaseController {}"
        );
    }

    /**
     * Check the known fixture paths against the real contract assertion.
     *
     * @return void
     */
    private function assertBridge(): void
    {
        PhpClassContract::assertBridge(
            $this->directory . '/example.json.php',
            $this->directory . '/example.php',
            'JemControllerExample'
        );
    }

    /** @return string Minimal valid controller bridge source. */
    private static function bridgeSource(): string
    {
        return "<?php defined('_JEXEC') or die; require_once __DIR__ . '/example.php';";
    }
}

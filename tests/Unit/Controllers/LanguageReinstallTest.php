<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class LanguageReinstallTest extends TestCase
{
    private object $controller;
    private array $package;

    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/language_install_environment.php';
        require_once JEM_TEST_ROOT . '/admin/controllers/languages.php';
        Factory::$app = new LanguageInstallApplication();
        $this->package = [
            'id' => 'es-ES-test', 'element' => 'com_jem_es-ES', 'tag' => 'es-ES',
            'jem' => '5.0', 'version' => '5.0.1', 'bytes' => 7,
            'checksum' => hash('sha256', 'fixture'), 'url' => 'https://www.joomlaeventmanager.net/test.zip',
        ];
        $this->setCatalog($this->package);
        $this->controller = new class extends JemControllerLanguages {
            public ?string $installed = '5.0.1';
            public bool $joomlaInstalled = true;
            public bool $validManifest = true;
            public bool $metadataFailure = false;
            public string $body = 'fixture';
            public int $downloads = 0;
            public int $cleanups = 0;
            protected function isJoomlaLanguageInstalled($tag)
            {
                return $this->joomlaInstalled;
            }
            protected function getInstalledPackageVersion(string $element): ?string
            {
                if ($this->metadataFailure) {
                    throw new RuntimeException('metadata_failure');
                }
                return $this->installed;
            }
            protected function downloadPackage(array $package): array
            {
                $this->downloads++;
                return ['body' => $this->body];
            }
            protected function validatePackageManifest($directory, array $package)
            {
                return $this->validManifest;
            }
            protected function cleanupPackage($temporaryPackage, $unpacked)
            {
                $this->cleanups++;
            }
        };
    }

    private function setCatalog(array $package): void
    {
        $property = new ReflectionProperty(JemLanguageCatalogHelper::class, 'catalog');
        $property->setValue(null, ['languages' => ['es-ES' => ['packages' => [$package]]]]);
    }

    public static function installStates(): iterable
    {
        yield 'install' => [null, true, false, 'INSTALL_SUCCESS'];
        yield 'update' => ['5.0.0', true, true, 'INSTALL_SUCCESS'];
        yield 'reinstall' => ['5.0.1', true, true, 'REINSTALL_SUCCESS'];
        yield 'downgrade blocked' => ['5.1.0', false, false, 'DOWNGRADE_BLOCKED'];
    }

    #[DataProvider('installStates')]
    public function testInstallStateIsRecheckedOnPost(?string $installed, bool $success, bool $upgrade, string $message): void
    {
        $this->controller->installed = $installed;
        self::assertSame($success, $this->controller->install());
        self::assertSame((int) $success, $this->controller->downloads);
        self::assertSame((int) $success, Installer::getInstance()->installs);
        self::assertSame($upgrade, Installer::getInstance()->upgrade);
        self::assertSame($upgrade, Installer::getInstance()->overwrite);
        self::assertStringContainsString($message, $this->controller->redirect[1]);
        self::assertSame(1, $this->controller->cleanups);
    }

    public function testSameVersionRefreshDownloadsAgainOnEveryRequest(): void
    {
        self::assertTrue($this->controller->install());
        $this->controller->body = 'updated';
        $this->package['checksum'] = hash('sha256', 'updated');
        $this->setCatalog($this->package);
        self::assertTrue($this->controller->install());
        self::assertSame(2, $this->controller->downloads);
        self::assertSame(2, Installer::getInstance()->installs);
    }

    public function testChangedZipWithStaleChecksumIsRejected(): void
    {
        $this->controller->body = 'changed';
        self::assertFalse($this->controller->install());
        self::assertSame(0, Installer::getInstance()->installs);
        self::assertSame(1, $this->controller->cleanups);
    }

    public function testWrongSizeIsRejected(): void
    {
        $this->controller->body = 'different size';
        self::assertFalse($this->controller->install());
        self::assertSame(0, Installer::getInstance()->installs);
    }

    public function testInvalidManifestIsRejectedBeforeOverwriteIsEnabled(): void
    {
        $this->controller->validManifest = false;
        self::assertFalse($this->controller->install());
        self::assertSame(0, Installer::getInstance()->installs);
        self::assertFalse(Installer::getInstance()->overwrite);
    }

    public function testMissingJoomlaLanguageCannotBeReinstalled(): void
    {
        $this->controller->joomlaInstalled = false;
        self::assertFalse($this->controller->install());
        self::assertSame(0, $this->controller->downloads);
    }

    public function testUnreadableInstalledMetadataFailsClosed(): void
    {
        $this->controller->metadataFailure = true;
        self::assertFalse($this->controller->install());
        self::assertSame(0, $this->controller->downloads);
    }

    public function testNativeInstallerFailureIsReported(): void
    {
        Installer::getInstance()->result = false;
        self::assertFalse($this->controller->install());
        self::assertStringContainsString('INSTALL_FAILED', $this->controller->redirect[1]);
        self::assertSame(1, $this->controller->cleanups);
    }

    public function testCoreAdminIsRequired(): void
    {
        Factory::$app->admin = false;
        $this->expectExceptionCode(403);
        $this->controller->install();
    }

    public function testPostTokenIsRequired(): void
    {
        JemHelper::$validToken = false;
        $this->expectExceptionCode(403);
        $this->controller->install();
    }

    public function testUncataloguedPackageIsRejected(): void
    {
        Factory::$app->packageId = 'forged';
        self::assertFalse($this->controller->install());
        self::assertSame(0, $this->controller->downloads);
    }

    public function testWrongJemMajorIsRejected(): void
    {
        $this->package['jem'] = '6.0';
        $this->setCatalog($this->package);
        self::assertFalse($this->controller->install());
        self::assertSame(0, $this->controller->downloads);
    }

    public function testBundledEnglishIsNotReinstalled(): void
    {
        $this->package['tag'] = 'en-GB';
        $this->setCatalog($this->package);
        self::assertFalse($this->controller->install());
        self::assertSame(0, $this->controller->downloads);
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImageResourceSecurityTest extends TestCase
{
    public function testUploadAndDecodePathsUseTheSharedResourcePolicy(): void
    {
        $image = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/image.class.php');
        $pdf = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/pdfimagepolicy.class.php');

        self::assertStringContainsString('imageresourcepolicy.class.php', $image);
        self::assertGreaterThanOrEqual(4, substr_count($image, 'JemImageResourcePolicy::inspect('));
        self::assertStringContainsString('@filesize($tmpName)', $image);
        self::assertStringContainsString('random_bytes(8)', $image);
        self::assertStringNotContainsString('$now = rand()', $image);
        self::assertStringContainsString("require_once __DIR__ . '/imageresourcepolicy.class.php';", $pdf);
        self::assertStringContainsString('JemImageResourcePolicy::inspect(', $pdf);
    }

    public function testPolicyBoundsDimensionsFramesExpansionAndMemoryBeforeDecode(): void
    {
        $policy = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/imageresourcepolicy.class.php');

        self::assertStringContainsString('DEFAULT_MAX_DIMENSION = 3840', $policy);
        self::assertStringContainsString('MAX_PIXELS', $policy);
        self::assertStringContainsString('MAX_FRAMES', $policy);
        self::assertStringContainsString('MAX_ANIMATION_PIXELS', $policy);
        self::assertStringContainsString('MAX_EXPANSION_RATIO', $policy);
        self::assertStringContainsString('MEMORY_LIMIT_EXCEEDED', $policy);
        self::assertStringContainsString('getimagesize($path)', $policy);
        self::assertStringNotContainsString('imagecreatefrom', $policy);
    }

    public function testPackageValidationRequiresTheSharedPolicy(): void
    {
        $builder = (string) file_get_contents(JEM_TEST_ROOT . '/scripts/build-packages.php');

        self::assertStringContainsString("'site/classes/imageresourcepolicy.class.php'", $builder);
    }
}

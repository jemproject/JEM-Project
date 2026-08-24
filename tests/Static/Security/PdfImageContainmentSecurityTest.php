<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PdfImageContainmentSecurityTest extends TestCase
{
    public function testPdfImagePolicyUsesRealPathsMediaRootsAndRasterValidation(): void
    {
        $policy = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/pdfimagepolicy.class.php');

        self::assertStringContainsString("defined('_JEXEC') or die;", $policy);
        self::assertStringContainsString("'images'", $policy);
        self::assertStringContainsString("'media/com_jem'", $policy);
        self::assertStringNotContainsString("array('images', 'media')", $policy);
        self::assertGreaterThanOrEqual(3, substr_count($policy, 'realpath('));
        self::assertStringContainsString("\$segment === '..'", $policy);
        self::assertStringContainsString('containsControlCharacter(', $policy);
        self::assertStringContainsString('isContainedPath(', $policy);
        self::assertStringContainsString('@getimagesize($path)', $policy);
        self::assertStringContainsString('ALLOWED_IMAGE_TYPES', $policy);
    }

    public function testEventPdfRoutesEveryStoredImageSourceThroughThePolicy(): void
    {
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/event/view.raw.php');

        self::assertStringContainsString('JemPdfImagePolicy::resolveLocalImage(', $view);
        self::assertStringContainsString("\$this->resolvePdfImagePath((string) \$flyer['original'], \$type)", $view);
        self::assertStringContainsString("\$sourcePath = \$this->resolvePdfImagePath(\$image, 'event');", $view);
        self::assertStringNotContainsString("JPATH_SITE . '/' . \$flyer['original']", $view);
        self::assertStringNotContainsString("JPATH_SITE . '/' . ltrim(\$thumb", $view);
        self::assertStringNotContainsString('$localHosts', $view);
    }

    public function testSharedPdfImageBuilderUsesTheSamePolicy(): void
    {
        $pdfView = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/pdfview.class.php');

        self::assertSame(2, substr_count($pdfView, 'JemPdfImagePolicy::resolveLocalImage('));
        self::assertStringNotContainsString("\$path = JPATH_SITE . '/' . \$source", $pdfView);
    }

    public function testRuntimeAndPackageLoadThePdfImagePolicy(): void
    {
        $entrypoint = (string) file_get_contents(JEM_TEST_ROOT . '/site/jem.php');
        $builder = (string) file_get_contents(JEM_TEST_ROOT . '/scripts/build-packages.php');
        $policyPosition = strpos($entrypoint, "require_once (JPATH_COMPONENT_SITE.'/classes/pdfimagepolicy.class.php');");
        $pdfPosition = strpos($entrypoint, "require_once (JPATH_COMPONENT_SITE.'/classes/pdf.class.php');");

        self::assertNotFalse($policyPosition);
        self::assertNotFalse($pdfPosition);
        self::assertLessThan($pdfPosition, $policyPosition);
        self::assertStringContainsString("'site/classes/pdfimagepolicy.class.php'", $builder);
    }
}

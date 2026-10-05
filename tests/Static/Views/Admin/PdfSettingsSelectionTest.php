<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PdfSettingsSelectionTest extends TestCase
{
    public function testEmptyPdfViewSelectionIsPreservedWhenSettingsAreSaved(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/settings.php');

        self::assertStringContainsString(
            "\$pdfEnabledViews = \$data['pdf_enabled_views'] ?? array();",
            $model
        );
        self::assertStringContainsString("\$data['pdf_enabled_views'] = implode(", $model);
        self::assertStringNotContainsString(
            "if (empty(\$data['pdf_enabled_views']))",
            $model
        );
    }
}

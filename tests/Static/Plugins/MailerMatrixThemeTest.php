<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Guard the scoped template-color contract independently of browser availability. */
final class MailerMatrixThemeTest extends TestCase
{
    /** @return void Header foreground/background follow Atum, retaining non-theme fallbacks. */
    public function testHeadersUseTemplateColors(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/plugins/plg_jem_mailer/fields/mailerprofiles.php');
        self::assertSame(1, preg_match('/\.jem-mailer-matrix-table thead th\{([^}]+)\}/', $source, $match));
        self::assertStringContainsString('background:var(--tertiary-bg,#e7edf5);', $match[1]);
        self::assertStringContainsString('color:var(--body-color,#17324f);', $match[1]);
        self::assertStringNotContainsString('!important', $match[1]);
        self::assertStringContainsString('font-weight:700;', $match[1]);
        self::assertStringContainsString('vertical-align:middle', $match[1]);
    }
}

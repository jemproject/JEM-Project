<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/cssfilepolicy.class.php';

final class CssFilePolicyTest extends TestCase
{
    #[DataProvider('validNames')]
    public function testAcceptsStylesheetFileNames(string $fileName): void
    {
        self::assertTrue(JemCssFilePolicy::isValidFileName($fileName));
    }

    public static function validNames(): iterable
    {
        yield ['jem.css'];
        yield ['calendar-responsive.css'];
        yield ['lightbox.min.css'];
        yield ['tema-área_2.css'];
        yield ['JEM.CSS'];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsNonStylesheetAndUnsafeFileNames(string $fileName): void
    {
        self::assertFalse(JemCssFilePolicy::isValidFileName($fileName));
    }

    public static function invalidNames(): iterable
    {
        yield [''];
        yield ['../jem.css'];
        yield ['custom/jem.css'];
        yield ['custom\\jem.css'];
        yield ['source.php'];
        yield ['source.php.css'];
        yield ['source.phtml.css'];
        yield ['source.css.php'];
        yield ['source..css'];
        yield ['source file.css'];
        yield ['.htaccess'];
    }
}

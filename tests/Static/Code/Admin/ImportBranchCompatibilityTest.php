<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImportBranchCompatibilityTest extends TestCase
{
    public function testImportModelDoesNotRequireRegistrationReferencesFromJem51(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/import.php');

        self::assertStringNotContainsString('registrationidentity.class.php', $source);
        self::assertStringNotContainsString('JemRegistrationIdentity::generateRegistrationReference()', $source);
    }
}

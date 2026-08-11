<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationidentity.class.php';

final class RegistrationIdentityTest extends TestCase
{
    public function testRegistrationReferenceUsesOpaqueCrockfordBase32Format(): void
    {
        $reference = JemRegistrationIdentity::generateRegistrationReference(str_repeat("\0", 16));

        self::assertSame('R-' . str_repeat('0', 26), $reference);
        self::assertSame(28, strlen($reference));
        self::assertTrue(JemRegistrationIdentity::isRegistrationReference($reference));
        self::assertFalse(JemRegistrationIdentity::isOperationReference($reference));
    }

    public function testOperationAndRegistrationNamespacesAreSeparate(): void
    {
        $bytes = hex2bin('00112233445566778899aabbccddeeff');
        $registration = JemRegistrationIdentity::generateRegistrationReference($bytes);
        $operation = JemRegistrationIdentity::generateOperationReference($bytes);

        self::assertSame(substr($registration, 2), substr($operation, 2));
        self::assertTrue(JemRegistrationIdentity::isRegistrationReference($registration));
        self::assertTrue(JemRegistrationIdentity::isOperationReference($operation));
    }

    public function testInvalidReferencesAndByteLengthsAreRejected(): void
    {
        foreach (array('', 'R-123', 'r-' . str_repeat('0', 26), 'R-' . str_repeat('I', 26)) as $value) {
            self::assertFalse(JemRegistrationIdentity::isRegistrationReference($value));
        }

        $this->expectException(InvalidArgumentException::class);
        JemRegistrationIdentity::generateRegistrationReference('short');
    }
}

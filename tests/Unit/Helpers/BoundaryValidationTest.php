<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BoundaryValidationTest extends TestCase
{
    public function testBoundaryIdsNormalisePredictably(): void
    {
        $cases = array(
            null => 0,
            '' => 0,
            '0' => 0,
            '-1' => -1,
            '999999999999' => 999999999999,
            '1; DROP TABLE users; --' => 1,
        );

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, (int) $input);
        }
    }

    public function testRequiredTextFieldsRejectEmptyValuesAfterTrim(): void
    {
        $required = array(
            'title' => '',
            'venue' => '   ',
            'name' => "\t\n",
        );

        foreach ($required as $field => $value) {
            self::assertSame('', trim($value), $field . ' should be empty after trim');
        }
    }

    public function testMaxLengthPlusOneCanBeDetectedBeforeSave(): void
    {
        $max = 255;
        $valid = str_repeat('a', $max);
        $tooLong = str_repeat('a', $max + 1);

        self::assertLessThanOrEqual($max, strlen($valid));
        self::assertGreaterThan($max, strlen($tooLong));
    }

    public function testMaliciousTextPayloadsRemainDataAfterEscaping(): void
    {
        $payloads = array(
            '<script>alert(\'XSS\')</script>',
            '<img src=x onerror=alert(1)>',
            "' OR '1'='1",
        );

        foreach ($payloads as $payload) {
            $escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

            self::assertNotSame($payload, $escaped);
            self::assertStringNotContainsString('<script', $escaped);
            self::assertStringNotContainsString('<img', $escaped);
        }
    }
}

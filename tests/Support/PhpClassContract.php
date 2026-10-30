<?php

declare(strict_types=1);

namespace JemProject\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Static class-declaration and approved legacy controller-bridge contracts.
 */
final class PhpClassContract
{
    /**
     * Check a guarded bridge and its class-defining target without executing either.
     *
     * @param string $bridge        Approved bridge file path.
     * @param string $target        Expected sibling implementation path.
     * @param string $expectedClass Controller class Joomla must find after loading.
     * @return void
     */
    public static function assertBridge(string $bridge, string $target, string $expectedClass): void
    {
        Assert::assertFileExists($bridge, 'Missing approved controller bridge: ' . $bridge);
        Assert::assertFileExists($target, 'Missing controller bridge target: ' . $target);
        Assert::assertSame(
            realpath(dirname($bridge)),
            realpath(dirname($target)),
            'Controller bridge targets must be siblings of their bridge.'
        );

        $expected = "<?php defined('_JEXEC') or die; require_once __DIR__ . '/" . basename($target) . "';";
        Assert::assertSame(
            self::tokens($expected),
            self::tokens((string) file_get_contents($bridge)),
            $bridge . ' must contain only the Joomla guard and require_once of ' . basename($target)
        );

        self::assertClass($target, $expectedClass);
    }

    /**
     * Find the expected declaration even when a helper class appears before it.
     *
     * @param string $path          PHP implementation to inspect without loading.
     * @param string $expectedClass Required class name, compared case-insensitively.
     * @return void
     */
    public static function assertClass(string $path, string $expectedClass): void
    {
        Assert::assertFileExists($path, 'Missing class implementation: ' . $path);
        $tokens = self::tokens((string) file_get_contents($path));
        $classes = [];
        foreach ($tokens as $index => $token) {
            if (
                is_array($token) && $token[0] === T_CLASS
                && ($tokens[$index + 1][0] ?? null) === T_STRING
                && ($tokens[$index + 2][0] ?? null) === T_EXTENDS
            ) {
                $classes[] = strtolower($tokens[$index + 1][1]);
            }
        }

        Assert::assertContains(
            strtolower($expectedClass),
            $classes,
            $path . ' must declare ' . $expectedClass . ' with an extends clause.'
        );
    }

    /**
     * Normalize PHP tokens while excluding comments and insignificant formatting.
     * Keep token types so quoted text cannot masquerade as executable PHP.
     *
     * @param string $source PHP source to inspect, never execute.
     * @return array<int, array{int, string}|string> Significant tokens.
     */
    private static function tokens(string $source): array
    {
        $result = [];
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                [$kind, $text] = $token;
                if (
                    in_array($kind, [T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                    || ($kind === T_INLINE_HTML && trim($text) === '')
                ) {
                    continue;
                }

                // The contract literals contain no escapes; allow either quote style.
                if ($kind === T_CONSTANT_ENCAPSED_STRING) {
                    $text = substr($text, 1, -1);
                }

                $result[] = [$kind, $text];
            } else {
                $result[] = $token;
            }
        }

        return $result;
    }
}

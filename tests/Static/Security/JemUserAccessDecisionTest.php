<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JemUserAccessDecisionTest extends TestCase
{
    public function testBooleanApiDelegatesToDetailedDecisionWithoutChangingItsSignature(): void
    {
        $code = file_get_contents(JEM_TEST_ROOT . '/site/classes/user.class.php');

        self::assertIsString($code);
        self::assertStringContainsString(
            'public function can($action, $type, $id = false, $created_by = false, $categoryIds = false)',
            $code
        );
        self::assertStringContainsString(
            'return $this->getAccessDecision($action, $type, $id, $created_by, $categoryIds)->isAllowed();',
            $code
        );
    }

    public function testDetailedDecisionDocumentsEverySupportedActionAndBlockingLayer(): void
    {
        $code = file_get_contents(JEM_TEST_ROOT . '/site/classes/user.class.php');

        foreach (array("case 'add':", "case 'edit':", "case 'publish':", "case 'delete':") as $actionCase) {
            self::assertStringContainsString($actionCase, $code);
        }

        foreach (array(
            'AUTHENTICATION_REQUIRED',
            'INVALID_RESOURCE_TYPE',
            'INVALID_ACTION',
            'NOT_RECORD_OWNER',
            'CATEGORY_NOT_FOUND',
            'CATEGORY_VIEW_DENIED',
            'JEM_GROUP_REQUIRED',
            'JEM_GROUP_ACTION_DENIED',
            'joomla_core_manage',
            'joomla_acl',
            'jem_global_setting',
            'jem_autopublish',
            'jem_group',
        ) as $contractValue) {
            self::assertStringContainsString($contractValue, $code);
        }
    }

    public function testEveryRepositoryConsumerStillUsesTheSupportedCanSignature(): void
    {
        $roots = array('site', 'admin', 'modules', 'plugins');
        $calls = 0;

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(JEM_TEST_ROOT . '/' . $root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $tokens = token_get_all((string) file_get_contents($file->getPathname()));
                $count = count($tokens);

                for ($i = 0; $i < $count; $i++) {
                    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_OBJECT_OPERATOR) {
                        continue;
                    }

                    $nameIndex = $this->nextMeaningfulToken($tokens, $i + 1);
                    if ($nameIndex === null || !is_array($tokens[$nameIndex]) || strtolower($tokens[$nameIndex][1]) !== 'can') {
                        continue;
                    }

                    $openIndex = $this->nextMeaningfulToken($tokens, $nameIndex + 1);
                    if ($openIndex === null || $tokens[$openIndex] !== '(') {
                        continue;
                    }

                    $argumentCount = $this->countCallArguments($tokens, $openIndex);
                    if ($argumentCount !== -1) {
                        self::assertGreaterThanOrEqual(2, $argumentCount, $file->getPathname());
                        self::assertLessThanOrEqual(5, $argumentCount, $file->getPathname());
                    }
                    $calls++;
                }
            }
        }

        self::assertGreaterThan(25, $calls, 'Expected to audit the existing JemUser::can() consumers.');
    }

    private function nextMeaningfulToken(array $tokens, int $start): ?int
    {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    private function countCallArguments(array $tokens, int $openIndex): int
    {
        $parentheses = 1;
        $brackets = 0;
        $braces = 0;
        $commas = 0;
        $hasContent = false;
        $usesArgumentUnpacking = false;

        for ($i = $openIndex + 1, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '(') {
                $parentheses++;
            } elseif ($token === ')') {
                $parentheses--;
                if ($parentheses === 0) {
                    if ($usesArgumentUnpacking) {
                        return -1;
                    }

                    return $hasContent ? $commas + 1 : 0;
                }
            } elseif ($token === '[') {
                $brackets++;
            } elseif ($token === ']') {
                $brackets--;
            } elseif ($token === '{') {
                $braces++;
            } elseif ($token === '}') {
                $braces--;
            } elseif ($token === ',' && $parentheses === 1 && $brackets === 0 && $braces === 0) {
                $commas++;
            } elseif (is_array($token) && $token[0] === T_ELLIPSIS && $parentheses === 1) {
                $usesArgumentUnpacking = true;
                $hasContent = true;
            } elseif (!(is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))) {
                $hasContent = true;
            }
        }

        self::fail('Unclosed can() call found while auditing permission consumers.');
    }
}

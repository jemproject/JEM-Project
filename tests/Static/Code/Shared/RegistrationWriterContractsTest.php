<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegistrationWriterContractsTest extends TestCase
{
    public function testCoreRegistrationWritesAreConfinedToTheTransactionalService(): void
    {
        $allowed = str_replace('\\', '/', realpath(JEM_TEST_ROOT . '/site/classes/registrationservice.class.php'));
        $violations = array();
        $patterns = array(
            '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+[`\'\"]*#__jem_register/i',
            '/->(?:insert|update|delete)\s*\([^;]{0,160}#__jem_register/is',
            '/->(?:insertObject|updateObject|deleteObject)\s*\(\s*[\'\"]#__jem_register[\'\"]/i',
        );

        foreach (array('/admin', '/site', '/plugins') as $relativeRoot) {
            $root = JEM_TEST_ROOT . $relativeRoot;
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getRealPath());
                if ($path === $allowed) {
                    continue;
                }

                $code = (string) file_get_contents($file->getPathname());
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $code) === 1) {
                        $violations[] = substr($path, strlen(str_replace('\\', '/', JEM_TEST_ROOT)) + 1);
                        break;
                    }
                }
            }
        }

        sort($violations);
        self::assertSame(array(), $violations, 'Registration writes must use JemRegistrationService.');
    }
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/JoomlaTestCase.php';

final class ImportModelBootstrapTest extends JoomlaTestCase
{
    public function testImportModelLoadsWithoutJem51RegistrationClass(): void
    {
        self::bootJoomlaSite();

        if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
            define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        }

        require_once JEM_TEST_ROOT . '/admin/models/import.php';

        self::assertTrue(class_exists(JemModelImport::class, false));
    }
}

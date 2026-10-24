<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class ImportErrorDetailsTest extends JoomlaTestCase
{
    public function testImportErrorsKeepOnlyThreeBoundedRowDetails(): void
    {
        self::bootJoomlaSite();

        if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
            define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        }

        require_once JEM_TEST_ROOT . '/admin/models/import.php';

        $model = (new ReflectionClass(JemModelImport::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(JemModelImport::class, 'appendImportErrorDetail');
        $result = array('error_details' => array());

        for ($line = 2; $line <= 5; $line++) {
            $arguments = array(&$result, $line, '<b>Invalid event</b>');
            $method->invokeArgs($model, $arguments);
        }

        self::assertCount(3, $result['error_details']);
        self::assertSame(array('line' => 2, 'reason' => 'Invalid event'), $result['error_details'][0]);
        self::assertSame(4, $result['error_details'][2]['line']);
    }
}

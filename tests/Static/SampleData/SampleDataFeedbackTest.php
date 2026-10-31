<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SampleDataFeedbackTest extends TestCase
{
    public function testConstructionDoesNotChangeDataOrExtractFiles(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/sampledata.php');
        self::assertSame(1, preg_match('/public function __construct\(\)\s*\{(.*?)\n    \}/s', $source, $match));
        self::assertStringNotContainsString('checkForJemData', $match[1]);
        self::assertStringNotContainsString('unpack', $match[1]);
    }

    public function testFailureFeedbackAndNonReversibleImportConfirmation(): void
    {
        $controller = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/sampledata.php');
        self::assertStringContainsString("\$type = 'error';", $controller);
        self::assertStringContainsString('$model->getError()', $controller);
        self::assertStringContainsString('$this->setRedirect($link, $msg, $type)', $controller);
        $view = file_get_contents(JEM_TEST_ROOT . '/admin/views/main/view.html.php');
        self::assertStringContainsString("window.confirm(", $view);
        self::assertStringContainsString('COM_JEM_SAMPLEDATA_CONFIRM', $view);
        $language = parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
        self::assertStringContainsString('There is no undo after a successful import.', $language['COM_JEM_SAMPLEDATA_CONFIRM']);
    }

    /** Verify the browser keeps one form, one token and a button-level confirmation. */
    public function testQuickActionsUseTheContainingFormAndConfirmOnActivation(): void
    {
        $view = file_get_contents(JEM_TEST_ROOT . '/admin/views/main/view.html.php');
        $template = file_get_contents(JEM_TEST_ROOT . '/admin/views/main/tmpl/default.php');
        self::assertSame(0, preg_match('/<\/?form\b/i', $view));
        self::assertSame(1, preg_match_all('/<form\b/i', $template));
        self::assertSame(1, substr_count($template, "HTMLHelper::_('form.token')"));
        self::assertStringContainsString('id="application-form" method="post"', $template);
        self::assertStringContainsString(' onclick="return window.confirm(', $view);
        self::assertSame(1, preg_match('/<button\b[^\r\n]*echo \$confirmation;/', $view));
        self::assertStringContainsString('type="submit" name="task"', $view);
        self::assertStringNotContainsString('onsubmit=', $view);
    }
}

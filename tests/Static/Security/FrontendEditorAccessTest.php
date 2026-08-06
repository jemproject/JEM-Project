<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrontendEditorAccessTest extends TestCase
{
    public function testSharedPolicyNormalisesRoutesAndRejectsAmbiguousIds(): void
    {
        $policy = $this->read('/site/classes/frontendaccess.class.php');

        self::assertStringContainsString("array('a_id', 'id')", $policy);
        self::assertStringContainsString('count($ids) > 1', $policy);
        self::assertStringContainsString('COM_JEM_ERROR_INVALID_RECORD_ID', $policy);
        self::assertStringContainsString('getAuthorisedViewLevels()', $policy);
        self::assertStringContainsString("getUserState('com_jem.edit.event.id'", $policy);
    }

    public function testMainControllerGatesEditorsAndSelectorsBeforeViewCreation(): void
    {
        $controller = $this->read('/site/controller.php');
        $guard = strpos($controller, "if ((\$viewName === 'editevent') || (\$viewName === 'editvenue'))");
        $view = strpos($controller, '$view = $this->getView(');

        self::assertNotFalse($guard);
        self::assertNotFalse($view);
        self::assertLessThan($view, $guard);
        self::assertStringContainsString("array('choosevenue', 'choosecontact', 'choosearticle', 'chooseusers')", $controller);
        self::assertStringContainsString("\$this->checkToken('request')", $controller);
        self::assertStringContainsString("\$jinput->exists('from_id')", $controller);
        self::assertStringContainsString("'com_jem.edit.' . \$type", $controller);
    }

    public function testGuestGateRunsBeforeEventSelectorLayouts(): void
    {
        $view = $this->read('/site/views/editevent/view.html.php');
        $guestGate = strpos($view, 'JemFrontendAccess::redirectGuestToLogin');

        self::assertNotFalse($guestGate);

        foreach (array('choosevenue', 'choosecontact', 'choosearticle', 'chooseusers') as $layout) {
            $layoutBranch = strpos($view, "getLayout() == '" . $layout . "'");
            self::assertNotFalse($layoutBranch);
            self::assertLessThan($layoutBranch, $guestGate);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function editorControllerProvider(): iterable
    {
        yield 'event' => array('/site/controllers/event.php', 'event');
        yield 'venue' => array('/site/controllers/venue.php', 'venue');
    }

    #[DataProvider('editorControllerProvider')]
    public function testEditorControllersAuthoriseStoredRecordsBeforeSave(string $path, string $type): void
    {
        $controller = $this->read($path);
        $allowEdit = $this->method($controller, 'allowEdit');
        $save = $this->method($controller, 'save');

        self::assertStringContainsString('getItem($recordId)', $allowEdit);
        self::assertStringContainsString("JemFrontendAccess::canEdit(\$user, '" . $type . "', \$record)", $allowEdit);
        self::assertStringNotContainsString("\$data['created_by']", $allowEdit);
        self::assertStringNotContainsString("\$data['access']", $allowEdit);

        self::assertStringContainsString('$this->checkToken();', $save);
        self::assertStringContainsString('$this->getFrontendItemOrFail(', $save);
        self::assertStringContainsString("\$this->assertFrontendCanEdit('" . $type . "'", $save);
        self::assertStringContainsString("\$this->assertFrontendCanAdd('" . $type . "'", $save);
        self::assertStringNotContainsString("jexit('Invalid Token')", $save);
    }

    public function testEditorRoutesUseTheSameRecordKey(): void
    {
        $router = $this->read('/site/router.php');
        $noMenuRule = $this->read('/site/services/JemNomenuRules.php');

        self::assertStringContainsString("foreach (array('editevent', 'editvenue') as \$viewName)", $router);
        self::assertStringContainsString("\$viewConfig->setKey('a_id')", $router);
        foreach (array('editevent', 'editvenue') as $viewName) {
            $case = strpos($noMenuRule, "case '" . $viewName . "':");
            $nextBreak = strpos($noMenuRule, 'break;', $case === false ? 0 : $case);

            self::assertNotFalse($case);
            self::assertNotFalse($nextBreak);
            self::assertStringContainsString("\$vars['a_id']", substr($noMenuRule, $case, $nextBreak - $case));
        }
    }

    public function testNoMenuRouterHasNoCopiedDebugScaffolding(): void
    {
        $noMenuRule = $this->read('/site/services/JemNomenuRules.php');

        self::assertStringNotContainsString('com_mywalks', $noMenuRule);
        self::assertStringNotContainsString('$test = \'Test\'', $noMenuRule);
        self::assertStringNotContainsString('print_r($segments)', $noMenuRule);
        self::assertStringContainsString('if (empty($segments))', $noMenuRule);
    }

    public function testSelectorQueriesRespectJoomlaViewLevels(): void
    {
        $model = $this->read('/site/models/editevent.php');
        $venueField = $this->read('/site/models/fields/modal/venue.php');
        $contactField = $this->read('/site/models/fields/modal/contact.php');
        $articleField = $this->read('/site/models/fields/modal/article.php');

        self::assertStringContainsString("l.access IN (", $model);
        self::assertStringContainsString("con.access IN (", $model);
        self::assertStringContainsString("cat.access IN (", $model);
        self::assertStringContainsString("access IN (", $venueField);
        self::assertStringContainsString("con.access", $contactField);
        self::assertStringContainsString("cat.access", $contactField);
        self::assertStringContainsString("->where(\$db->quoteName('access')", $articleField);
    }

    public function testMissingEditorRowsDoNotBecomeNewRecords(): void
    {
        foreach (array('/site/models/editevent.php', '/site/models/editvenue.php') as $path) {
            $model = $this->read($path);

            self::assertMatchesRegularExpression(
                '/\\$return = \\$table->load\\(\\$itemId\\);.*?if \\(\\$return === false\\).*?return false;/s',
                $model
            );
        }
    }

    public function testAssociatedArticleUpdatesRequireJoomlaArticleAcl(): void
    {
        $model = $this->read('/admin/models/event.php');

        self::assertStringContainsString("authorise('core.edit', \$articleAsset)", $model);
        self::assertStringContainsString("authorise('core.edit.own', \$articleAsset)", $model);
        self::assertStringContainsString('COM_JEM_EVENT_ARTICLE_SYNC_NO_PERMISSION', $model);
    }

    private function method(string $php, string $name): string
    {
        $start = strpos($php, 'function ' . $name . '(');
        self::assertNotFalse($start, 'Method not found: ' . $name);

        $end = strpos($php, "\n    /**", $start);

        if ($end === false) {
            $end = strlen($php);
        }

        return substr($php, $start, $end - $start);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}

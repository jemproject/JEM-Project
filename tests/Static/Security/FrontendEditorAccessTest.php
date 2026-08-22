<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrontendEditorAccessTest extends TestCase
{
    public function testSharedPolicyPrioritisesCanonicalEditorIdsAndRejectsAmbiguousGenericIds(): void
    {
        $policy = $this->read('/site/classes/frontendaccess.class.php');

        self::assertStringContainsString("\$input->exists('a_id') ? array('a_id') : array('id')", $policy);
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

        $routeContracts = array(
            '/site/classes/output.class.php' => array(
                'view=editevent&task=event.edit&a_id=',
                'view=editevent&task=event.copy&a_id=',
                'view=editvenue&task=venue.edit&a_id=',
                'view=editvenue&task=venue.copy&a_id=',
            ),
            '/site/classes/pdfview.class.php' => array('view=editvenue&task=venue.edit&a_id='),
            '/site/views/venuesmap/tmpl/default.php' => array('view=editvenue&task=venue.edit&a_id='),
            '/site/views/venueslist/tmpl/default_venues.php' => array('view=editvenue&task=venue.edit&a_id='),
            '/site/views/venueslist/tmpl/responsive/default_venues.php' => array('view=editvenue&task=venue.edit&a_id='),
        );

        foreach ($routeContracts as $path => $needles) {
            $source = $this->read($path);

            foreach ($needles as $needle) {
                self::assertStringContainsString($needle, $source, $path);
            }
        }
    }

    public function testFrontendSavesDiscardSubmittedRecordProvenance(): void
    {
        foreach (array('/admin/models/event.php', '/admin/models/venue.php') as $path) {
            $save = $this->method($this->read($path), 'save');

            self::assertStringContainsString('if (!$backend)', $save, $path);
            self::assertStringContainsString("unset(\$data['created'], \$data['created_by']);", $save, $path);
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
        $sync = $this->method($model, 'updateAssociatedArticleFromEvent');
        $create = $this->method($model, 'createAssociatedContentArticle');
        $siteController = $this->method($this->read('/site/controllers/event.php'), 'updateAssociatedArticle');
        $adminController = $this->method($this->read('/admin/controllers/event.php'), 'updateAssociatedArticle');

        self::assertStringContainsString("authorise('core.edit', \$articleAsset)", $model);
        self::assertStringContainsString("authorise('core.edit.own', \$articleAsset)", $model);
        self::assertStringContainsString('COM_JEM_EVENT_ARTICLE_SYNC_NO_PERMISSION', $model);
        self::assertStringContainsString("\$user->get('guest', 0)", $sync);
        self::assertStringContainsString('checked_out', $sync);
        self::assertStringContainsString("Text::_('JLIB_APPLICATION_ERROR_CHECKIN_USER_MISMATCH')", $sync);
        self::assertStringNotContainsString("authorise('core.edit.state'", $sync);
        self::assertStringNotContainsString("'state' =>", $sync);
        self::assertStringNotContainsString('$update->state', $sync);
        self::assertStringNotContainsString('$update->publish_up', $sync);
        self::assertStringNotContainsString('$update->publish_down', $sync);
        self::assertStringContainsString("'state'       => 0", $create);
        self::assertStringNotContainsString('$hasArticleText ? 1 : 0', $create);

        self::assertStringContainsString('$this->checkToken();', $siteController);
        self::assertStringNotContainsString("checkToken('get')", $siteController);
        self::assertStringContainsString('$app->input->post', $siteController);
        self::assertStringContainsString("\$this->assertFrontendCanEdit('event'", $siteController);
        self::assertStringContainsString('Session::checkToken()', $adminController);
        self::assertStringNotContainsString("checkToken('get')", $adminController);
        self::assertStringContainsString('$app->input->post', $adminController);
        self::assertStringContainsString('$this->allowEdit(', $adminController);
    }

    public function testAssociatedArticleSyncActionsArePostForms(): void
    {
        foreach (array('/site/controllers/event.php', '/admin/controllers/event.php') as $path) {
            $notice = $this->method($this->read($path), 'handleAssociatedArticleSyncNotice');

            self::assertStringContainsString('method="post"', $notice, $path);
            self::assertStringContainsString('name="task" value="event.updateAssociatedArticle"', $notice, $path);
            self::assertStringContainsString('Session::getFormToken()', $notice, $path);
            self::assertStringNotContainsString('&task=event.updateAssociatedArticle', $notice, $path);
        }
    }

    public function testRelatedFrontendMutationsAuthoriseStoredResources(): void
    {
        foreach (array('/site/models/myevents.php' => 'event', '/site/models/myvenues.php' => 'venue') as $path => $type) {
            $publish = $this->method($this->read($path), 'publish');

            self::assertStringContainsString("->select(array('id', 'created_by'))", $publish, $path);
            self::assertStringContainsString("\$user->can('publish', '" . $type . "'", $publish, $path);
        }

        $attendees = $this->read('/site/controllers/attendees.php');
        foreach (array('attendeeadd', 'attendeeremove', 'attendeetoggle', 'export') as $method) {
            self::assertStringContainsString('assertCanManageAttendees(', $this->method($attendees, $method), $method);
        }
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

<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class JedReviewTest extends TestCase
{
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/jed_review_environment.php';
        define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        require_once JEM_TEST_ROOT . '/admin/controllers/jedreview.php';
        \Joomla\CMS\Factory::$app = new JedReviewApplication();
        \Joomla\CMS\Factory::$db = new JedReviewDatabase();
    }

    public function testVisibilityRequiresBackendAuthenticatedManagerAndCompletedMigration(): void
    {
        $app = \Joomla\CMS\Factory::$app;
        self::assertTrue(JemJedReview::allowed($app, false));
        self::assertFalse(JemJedReview::allowed($app, true));
        $app->client = 'site';
        self::assertFalse(JemJedReview::allowed($app, false));
        $app->client = 'administrator';
        $app->user->manage = false;
        self::assertFalse(JemJedReview::allowed($app, false));
        $app->user->manage = true;
        $app->user->id = 0;
        self::assertFalse(JemJedReview::allowed($app, false));
    }

    public function testPersistenceIsPerUserIdempotentAndPreservesOtherPreferences(): void
    {
        $db = \Joomla\CMS\Factory::$db;
        self::assertFalse(JemJedReview::dismissed($db, 7));
        JemJedReview::save($db, 7, 'dismiss');
        JemJedReview::save($db, 7, 'dismiss');
        self::assertTrue(JemJedReview::dismissed($db, 7));
        self::assertFalse(JemJedReview::dismissed($db, 8));
        // A fresh identity (login) reads the same persisted preference, not a session flag.
        $app = new JedReviewApplication();
        self::assertTrue(JemJedReview::dismissed($db, $app->user->id));
        JemJedReview::save($db, 8, 'dismiss');
        JemJedReview::save($db, 7, 'show');
        JemJedReview::save($db, 7, 'show');
        self::assertFalse(JemJedReview::dismissed($db, 7));
        self::assertTrue(JemJedReview::dismissed($db, 8));
        self::assertSame('1990-01-01', $db->pdo->query("SELECT profile_value FROM user_profiles WHERE profile_key = 'profile.dob'")->fetchColumn());
        self::assertStringContainsString('FOR UPDATE', implode("\n", $db->queries));
    }

    public function testFailedInsertRollsBackPreviousPreference(): void
    {
        $db = \Joomla\CMS\Factory::$db;
        JemJedReview::save($db, 7, 'dismiss');
        $db->failInsert = true;
        try {
            JemJedReview::save($db, 7, 'dismiss');
            self::fail('Expected storage failure');
        } catch (RuntimeException $exception) {
            self::assertTrue(JemJedReview::dismissed($db, 7));
            self::assertFalse($db->pdo->inTransaction());
        }
    }

    public function testInvalidActionsAndAnonymousIdsNeverWrite(): void
    {
        $db = \Joomla\CMS\Factory::$db;
        foreach ([[7, 'reviewed'], [7, ''], [0, 'dismiss'], [-1, 'show']] as [$id, $action]) {
            try {
                JemJedReview::save($db, $id, $action);
                self::fail('Invalid input accepted');
            } catch (InvalidArgumentException $exception) {
                self::assertSame([], $db->queries);
            }
        }
    }

    public function testDeletedUserDoesNotCreateOrphanPreference(): void
    {
        $db = \Joomla\CMS\Factory::$db;
        try {
            JemJedReview::save($db, 999, 'dismiss');
            self::fail('Missing user accepted');
        } catch (RuntimeException $exception) {
            self::assertFalse($db->pdo->inTransaction());
            self::assertFalse(JemJedReview::dismissed($db, 999));
        }
    }

    public function testControllerReturnsOnlyCurrentUserPreference(): void
    {
        ob_start();
        (new JemControllerJedreview())->save();
        $result = json_decode(ob_get_clean(), true);
        self::assertTrue($result['success']);
        self::assertSame(['dismissed' => true, 'card' => 'jed'], $result['data']);
        self::assertTrue(JemJedReview::dismissed(\Joomla\CMS\Factory::$db, 7));
        self::assertFalse(JemJedReview::dismissed(\Joomla\CMS\Factory::$db, 8));
        self::assertTrue(\Joomla\CMS\Factory::$app->closed);
        self::assertSame('no-store', \Joomla\CMS\Factory::$app->headers['Cache-Control']);
    }

    public function testControllerProtectsMethodTokenAclAndMigration(): void
    {
        $app = \Joomla\CMS\Factory::$app;
        foreach (['get', 'csrf', 'acl', 'frontend', 'migration', 'guest'] as $case) {
            JemHelper::$method = $case === 'get' ? 'GET' : 'POST';
            JemHelper::$validToken = $case !== 'csrf';
            $app->user->manage = $case !== 'acl';
            $app->user->id = $case === 'guest' ? 0 : 7;
            $app->client = $case === 'frontend' ? 'site' : 'administrator';
            JemAclMigrationStatus::$isPending = $case === 'migration';
            try {
                (new JemControllerJedreview())->save();
                self::fail('Request should be rejected: ' . $case);
            } catch (RuntimeException $exception) {
                self::assertSame($case === 'get' ? 405 : 403, $exception->getCode());
                self::assertSame([], \Joomla\CMS\Factory::$db->queries);
            }
        }
    }

    public function testControllerRejectsUnknownAction(): void
    {
        \Joomla\CMS\Factory::$app = new JedReviewApplication('reviewed');
        $this->expectExceptionCode(400);
        (new JemControllerJedreview())->save();
    }

    public function testControllerHidesStorageErrors(): void
    {
        \Joomla\CMS\Factory::$db->failInsert = true;
        ob_start();
        (new JemControllerJedreview())->save();
        $output = ob_get_clean();
        self::assertFalse(json_decode($output, true)['success']);
        self::assertStringNotContainsString('Private database detail', $output);
        self::assertFalse(JemJedReview::dismissed(\Joomla\CMS\Factory::$db, 7));
    }

    public function testNoJavascriptFallbackRedirectsLocally(): void
    {
        \Joomla\CMS\Factory::$app = new JedReviewApplication('dismiss', 'html');
        $controller = new JemControllerJedreview();
        $controller->save();
        self::assertSame('index.php?option=com_jem', $controller->redirect[0]);
        self::assertTrue(JemJedReview::dismissed(\Joomla\CMS\Factory::$db, 7));
    }

    public function testDonationAndReviewPreferencesAreIndependent(): void
    {
        $db = \Joomla\CMS\Factory::$db;
        JemJedReview::save($db, 7, 'dismiss', 'donate');
        self::assertTrue(JemJedReview::dismissed($db, 7, 'donate'));
        self::assertFalse(JemJedReview::dismissed($db, 7, 'jed'));
        self::assertFalse(JemJedReview::dismissed($db, 8, 'donate'));
        JemJedReview::save($db, 7, 'dismiss', 'jed');
        self::assertTrue(JemJedReview::dismissed($db, 7, 'donate'));
        self::assertTrue(JemJedReview::dismissed($db, 7, 'jed'));
        self::assertSame('1', $db->pdo->query("SELECT profile_value FROM user_profiles WHERE user_id=7 AND profile_key='com_jem.jed_review_dismissed'")->fetchColumn());
    }

    public function testControllerAcceptsDonationButRejectsOtherCardsAndRestore(): void
    {
        \Joomla\CMS\Factory::$app = new JedReviewApplication('dismiss', 'json', 'donate');
        ob_start();
        (new JemControllerJedreview())->save();
        self::assertSame(['dismissed' => true, 'card' => 'donate'], json_decode(ob_get_clean(), true)['data']);
        self::assertTrue(JemJedReview::dismissed(\Joomla\CMS\Factory::$db, 7, 'donate'));
        self::assertFalse(JemJedReview::dismissed(\Joomla\CMS\Factory::$db, 7, 'jed'));
        foreach ([['dismiss', 'profile.dob'], ['show', 'jed'], ['dismiss', '']] as [$action, $card]) {
            \Joomla\CMS\Factory::$app = new JedReviewApplication($action, 'json', $card);
            try {
                (new JemControllerJedreview())->save();
                self::fail('Invalid action accepted');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(400, $exception->getCode());
            }
        }
    }
}

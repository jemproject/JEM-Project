<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

require_once __DIR__ . '/JoomlaTestCase.php';

/** Read-only installed-file, real-language/template and profile-query checks. */
final class JedReviewJoomlaIntegrationTest extends JoomlaTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::bootJoomlaSite();
        $language = Language::getInstance('en-GB');
        Factory::$language = $language;
        Factory::getApplication()->loadLanguage($language);
        Factory::getApplication()->loadDocument();
        Factory::getApplication()->createExtensionNamespaceMap();
        $language->load('com_jem', JPATH_ADMINISTRATOR, 'en-GB', true);
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/helpers/jedreview.php';
    }

    public function testInstalledFilesMatchSource(): void
    {
        $files = [
            'admin/helpers/jedreview.php' => 'administrator/components/com_jem/helpers/jedreview.php',
            'admin/controllers/jedreview.php' => 'administrator/components/com_jem/controllers/jedreview.php',
            'admin/controllers/jedreview.json.php' => 'administrator/components/com_jem/controllers/jedreview.json.php',
            'admin/views/main/view.html.php' => 'administrator/components/com_jem/views/main/view.html.php',
            'admin/views/main/tmpl/default.php' => 'administrator/components/com_jem/views/main/tmpl/default.php',
            'admin/views/main/tmpl/default_review.php' => 'administrator/components/com_jem/views/main/tmpl/default_review.php',
            'admin/views/main/tmpl/default_communitybuttons.php' => 'administrator/components/com_jem/views/main/tmpl/default_communitybuttons.php',
            'admin/views/help/tmpl/default.php' => 'administrator/components/com_jem/views/help/tmpl/default.php',
            'admin/language/en-GB/com_jem.ini' => 'administrator/language/en-GB/com_jem.ini',
            'media/js/jed-review.js' => 'media/com_jem/js/jed-review.js',
            'media/css/community-cards.css' => 'media/com_jem/css/community-cards.css',
            'media/images/icon-48-donate.svg' => 'media/com_jem/images/icon-48-donate.svg',
            'media/images/icon-48-jed-review.svg' => 'media/com_jem/images/icon-48-jed-review.svg',
            'media/images/jem-review-banner.webp' => 'media/com_jem/images/jem-review-banner.webp',
        ];
        foreach ($files as $source => $installed) {
            self::assertSame(hash_file('sha256', JEM_TEST_ROOT . '/' . $source), hash_file('sha256', JPATH_SITE . '/' . $installed));
        }
    }

    public function testRealProfileQueryDoesNotWritePreferences(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $id = (int) $db->setQuery($db->getQuery(true)->select('MIN(id)')->from($db->quoteName('#__users')))->loadResult();
        self::assertGreaterThan(0, $id);
        $before = JemJedReview::dismissed($db, $id);
        self::assertIsBool($before);
        self::assertSame($before, JemJedReview::dismissed($db, $id));
        self::assertFalse(JemJedReview::allowed(Factory::getApplication(), false), 'Site applications must not expose the card.');
    }

    public function testJsonRequestResolvesRealJoomlaController(): void
    {
        $input = Factory::getApplication()->getInput();
        $task = $input->getCmd('task');
        $format = $input->getCmd('format');
        $input->set('task', 'jedreview.save');
        $input->set('format', 'json');
        if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
            define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_jem');
        }
        try {
            $controller = \Joomla\CMS\MVC\Controller\BaseController::getInstance('Jem', [
                'base_path' => JPATH_COMPONENT_ADMINISTRATOR,
            ]);
            self::assertInstanceOf(JemControllerJedreview::class, $controller);
            self::assertSame('save', $input->getCmd('task'));
        } finally {
            $input->set('task', $task);
            $input->set('format', $format);
        }
    }

    public function testInstalledTemplateRendersWithRealJoomlaLanguageAndToken(): void
    {
        self::assertSame('Share your experience with JEM', Text::_('COM_JEM_JED_REVIEW_TITLE'));
        $view = new class {
            protected bool $jedReviewAvailable = true;
            protected array $communityCardDismissed = [];

            public function render(bool $available, bool $donate, bool $jed): string
            {
                $this->jedReviewAvailable = $available;
                $this->communityCardDismissed = ['donate' => $donate, 'jed' => $jed];
                ob_start();
                try {
                    include JPATH_ADMINISTRATOR . '/components/com_jem/views/main/tmpl/default_review.php';
                    include JPATH_ADMINISTRATOR . '/components/com_jem/views/main/tmpl/default_communitybuttons.php';
                    return ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            }
        };
        self::assertSame('', trim($view->render(false, false, false)));
        foreach ([[false, false], [false, true], [true, false], [true, true]] as [$donate, $jed]) {
            $html = $view->render(true, $donate, $jed);
            self::assertStringContainsString('Share your experience with JEM', $html);
            self::assertStringNotContainsString('COM_JEM_JED_REVIEW_', $html);
            self::assertStringContainsString('https://extensions.joomla.org/extension/jem/', $html);
            self::assertStringContainsString('task=jedreview.save', $html);
            self::assertStringContainsString('rel="noopener noreferrer"', $html);
            self::assertMatchesRegularExpression('/type="hidden" name="[a-f0-9]{32}" value="1"/', $html);
            self::assertSame($jed, str_contains($html, 'data-jem-community-card="jed" hidden'));
            self::assertSame(!$jed, str_contains($html, 'data-jem-community-button="jed" hidden'));
            self::assertSame($donate, str_contains($html, 'data-jem-community-card="donate" hidden'));
            self::assertSame(!$donate, str_contains($html, 'data-jem-community-button="donate" hidden'));
            self::assertSame($donate && $jed, str_contains($html, 'data-jem-community-cards hidden'));
        }
    }
}

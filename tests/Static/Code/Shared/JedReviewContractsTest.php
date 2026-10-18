<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JedReviewContractsTest extends TestCase
{
    public function testStringsAndLocalisedSafeTemplate(): void
    {
        $strings = parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
        foreach (['admin/views/main/tmpl/default_review.php', 'admin/views/main/tmpl/default_communitybuttons.php', 'admin/helpers/jedreview.php', 'admin/controllers/jedreview.php'] as $file) {
            $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
            preg_match_all('/COM_JEM_(?:JED_REVIEW|COMMUNITY|MAIN)_[A-Z_]+/', $source, $keys);
            foreach (array_unique($keys[0]) as $key) {
                self::assertArrayHasKey($key, $strings);
                self::assertNotSame('', $strings[$key]);
            }
        }
        $template = file_get_contents(JEM_TEST_ROOT . '/admin/views/main/tmpl/default_review.php');
        self::assertStringContainsString('rel="noopener noreferrer"', $template);
        self::assertStringContainsString('aria-live="polite"', $template);
        self::assertStringContainsString("HTMLHelper::_('form.token')", $template);
        self::assertStringContainsString('empty($this->jedReviewAvailable)', $template);
    }

    public function testIndependentFormAndBackendOnlyAsset(): void
    {
        $template = file_get_contents(JEM_TEST_ROOT . '/admin/views/main/tmpl/default.php');
        self::assertMatchesRegularExpression('/<\/form>\s*<\?php if \(\$this->jedReviewAvailable\).*loadTemplate\(\x27review\x27\)/s', $template);
        $view = file_get_contents(JEM_TEST_ROOT . '/admin/views/main/view.html.php');
        self::assertStringContainsString('JemJedReview::allowed($app, JemAclMigrationStatus::pending())', $view);
        self::assertStringContainsString('com_jem/jed-review.js', $view);
        $controller = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/jedreview.php');
        self::assertStringContainsString('JemHelper::requirePostToken()', $controller);
        self::assertStringContainsString('(int) $app->getIdentity()->id', $controller);
        self::assertStringNotContainsString("getInt('user", $controller);
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        self::assertStringContainsString("Session::checkToken('post')", $helper);
        self::assertStringContainsString("\$method !== 'POST'", $helper);
    }

    public function testNoTrackingOrInstallerReset(): void
    {
        $js = file_get_contents(JEM_TEST_ROOT . '/media/js/jed-review.js');
        foreach (['localStorage', 'document.cookie', 'sendBeacon', 'https://', 'innerHTML'] as $token) {
            self::assertStringNotContainsString($token, $js);
        }
        self::assertStringContainsString("credentials: 'same-origin'", $js);
        self::assertStringNotContainsString('jed_review_dismissed', file_get_contents(JEM_TEST_ROOT . '/script.php'));
        self::assertFileDoesNotExist(JEM_TEST_ROOT . '/site/controllers/jedreview.php');
    }

    public function testCommunityCardsHaveMatchingIconsAndNoHelpSwitch(): void
    {
        $help = file_get_contents(JEM_TEST_ROOT . '/admin/views/help/tmpl/default.php');
        self::assertStringNotContainsString('JED_REVIEW', $help);
        $cards = file_get_contents(JEM_TEST_ROOT . '/admin/views/main/tmpl/default_review.php');
        self::assertStringNotContainsString('value="show"', $cards);
        self::assertStringContainsString('com_jem/PayPal_DonateButton.webp', $cards);
        self::assertStringContainsString("\$key === 'donate'", $cards);
        self::assertFileExists(JEM_TEST_ROOT . '/media/images/PayPal_DonateButton.webp');
        self::assertStringNotContainsString('PayPal_DonateButton', file_get_contents(JEM_TEST_ROOT . '/admin/views/main/tmpl/default.php'));
        foreach (['donate', 'jed-review'] as $name) {
            $svg = file_get_contents(JEM_TEST_ROOT . '/media/images/icon-48-' . $name . '.svg');
            self::assertNotFalse(simplexml_load_string($svg));
            self::assertStringContainsString('viewBox="0 0 96 96"', $svg);
            self::assertStringContainsString('#7295b1', $svg);
            self::assertStringContainsString('#f69d00', $svg);
        }
    }
}

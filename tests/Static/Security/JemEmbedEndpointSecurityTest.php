<?php

use PHPUnit\Framework\TestCase;

final class JemEmbedEndpointSecurityTest extends TestCase
{
    public function testEndpointUsesHeaderCredentialsAndFixedRequestBudgets(): void
    {
        $plugin = $this->read('plugins/plg_content_jemembed/jemembed.php');
        $policy = $this->read('plugins/plg_content_jemembed/requestpolicy.php');

        self::assertStringContainsString("get('HTTP_AUTHORIZATION', '', 'raw')", $plugin);
        self::assertStringContainsString("get('REDIRECT_HTTP_AUTHORIZATION', '', 'raw')", $plugin);
        self::assertStringContainsString("\$app->input->exists('token')", $plugin);
        self::assertStringNotContainsString("getString('token'", $plugin);
        self::assertStringContainsString('hash_equals($configuredDigest, $presentedDigest)', $policy);
        self::assertStringContainsString('public const MAX_EVENTS = 100;', $policy);
        self::assertStringContainsString('public const MAX_FILTER_IDS = 50;', $policy);
        self::assertStringContainsString('public const MAX_QUERY_LENGTH = 4096;', $policy);
        self::assertStringContainsString('$unknownParameters = array_diff(', $plugin);
        self::assertStringNotContainsString("'Itemid'", $plugin);
        self::assertStringContainsString("consumeRateLimit('ip:'", $plugin);
        self::assertStringContainsString("consumeRateLimit('credential:'", $plugin);
        self::assertStringContainsString('flock($handle, LOCK_EX)', $policy);
        self::assertStringContainsString("'lifetime' => self::RESPONSE_CACHE_MINUTES", $plugin);
        self::assertStringContainsString('$app->getLanguage()->getTag()', $plugin);
    }

    public function testEndpointAlwaysQueriesAsPublicGuestWithStrictAccess(): void
    {
        $plugin = $this->read('plugins/plg_content_jemembed/jemembed.php');
        $model = $this->read('site/models/eventslist.php');

        self::assertStringContainsString('$guest = JemFactory::getUser(0);', $plugin);
        self::assertStringContainsString("setState('filter.access_levels', \$guest->getAuthorisedViewLevels())", $plugin);
        self::assertStringContainsString("setState('filter.strict_access', true)", $plugin);
        self::assertStringContainsString("getState('filter.access_levels', null)", $model);
        self::assertStringContainsString("getState('filter.strict_access', false)", $model);
        self::assertStringContainsString('if (!$strictAccess && $jemsettings->access_level_locked_events', $model);
        self::assertStringContainsString('if (!$strictAccess && $jemsettings->access_level_locked_venues', $model);
        self::assertStringContainsString('if ($strictAccess) {', $model);
    }

    public function testEndpointBoundsDatabaseContentBeforeHydration(): void
    {
        $plugin = $this->read('plugins/plg_content_jemembed/jemembed.php');
        $model = $this->read('site/models/eventslist.php');
        $helper = $this->read('site/helpers/helper.php');

        self::assertStringContainsString('LEFT(a.introtext, ', $plugin);
        self::assertStringContainsString('LEFT(a.fulltext, ', $plugin);
        self::assertStringContainsString("setState('list.compact_select', true)", $plugin);
        self::assertStringContainsString("setState('list.content_limit', \$contentLimit)", $plugin);
        self::assertStringContainsString("setState('list.skip_attendee_numbers', true)", $plugin);
        self::assertStringContainsString("getState('list.compact_select', false)", $model);
        self::assertStringContainsString("getState('list.content_limit', 0)", $model);
        self::assertStringContainsString("getState('list.skip_attendee_numbers', false)", $model);
        self::assertStringContainsString("'LEFT(' . \$db->quoteName('a.introtext')", $helper);
        self::assertStringContainsString("'LEFT(' . \$db->quoteName('a.fulltext')", $helper);
    }

    public function testEmbedPreservesEventslistFeaturedAndEmptyResultSemantics(): void
    {
        $plugin = $this->read('plugins/plg_content_jemembed/jemembed.php');
        $policy = $this->read('plugins/plg_content_jemembed/requestpolicy.php');

        self::assertStringContainsString("array('on', 'off', 'only')", $policy);
        self::assertStringContainsString("\$parameters['show_featured'] === 'off'", $plugin);
        self::assertStringContainsString("\$parameters['show_featured'] === 'only'", $plugin);
        self::assertStringNotContainsString("\$parameters['show_featured'] == 'on'", $plugin);
        self::assertStringContainsString("'noeventsmsg' => 'no_events_msg'", $plugin);
        self::assertStringContainsString("'show_featured'     => 'on'", $plugin);
        self::assertStringContainsString("'no_events_msg'     => ''", $plugin);
    }

    public function testOriginAndErrorsAreNotDerivedFromTheRequest(): void
    {
        $plugin = $this->read('plugins/plg_content_jemembed/jemembed.php');
        $policy = $this->read('plugins/plg_content_jemembed/requestpolicy.php');
        $manifest = $this->read('plugins/plg_content_jemembed/jemembed.xml');

        self::assertStringContainsString("\$this->params->get('base_url', '')", $plugin);
        self::assertStringNotContainsString('Uri::getInstance()', $plugin);
        self::assertStringNotContainsString('HTTP_HOST', $plugin);
        self::assertStringContainsString("return \$this->publicError('Request could not be processed.');", $plugin);
        self::assertStringContainsString("return \$this->publicError('Authentication required.');", $plugin);
        self::assertStringContainsString("isset(\$parts['user'])", $policy);
        self::assertStringContainsString('name="base_url"', $manifest);
        self::assertStringContainsString('<filename>requestpolicy.php</filename>', $manifest);
    }

    public function testBundledProxyKeepsSecretsOutOfUrlsAndRedirects(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is required to inspect the JEM Embed example.');
        }

        $zip = new ZipArchive();
        $path = dirname(__DIR__, 3) . '/plugins/plg_content_jemembed/media/files/jemevents.zip';
        self::assertTrue($zip->open($path) === true, 'Unable to open the JEM Embed example archive.');

        try {
            $php = $zip->getFromName('JEMembed Plugin Files/jemevents.php');
            $javascript = $zip->getFromName('JEMembed Plugin Files/jemevents.js');
            $readme = $zip->getFromName('JEMembed Plugin Files/readme.txt');
        } finally {
            $zip->close();
        }

        self::assertIsString($php);
        self::assertIsString($javascript);
        self::assertIsString($readme);
        self::assertStringContainsString("'Authorization: Bearer ' . \$this->token", $php);
        self::assertStringNotContainsString("['token' => \$this->token]", $php);
        self::assertStringContainsString("'show_featured' => 'featured'", $php);
        self::assertStringContainsString("'cut_title' => 'cuttitle'", $php);
        self::assertStringContainsString("'show_date' => 'date'", $php);
        self::assertStringContainsString("'show_category' => 'category'", $php);
        self::assertStringContainsString("'show_venue' => 'venue'", $php);
        self::assertStringContainsString("'max_events' => 'max'", $php);
        self::assertStringContainsString("'no_events_msg' => 'noeventsmsg'", $php);
        self::assertStringContainsString('foreach (self::QUERY_PARAMETER_MAP', $php);
        self::assertStringContainsString("http_build_query(\$queryParameters", $php);
        self::assertStringNotContainsString("http_build_query(\$this->config", $php);
        self::assertStringContainsString('CURLOPT_FOLLOWLOCATION => false', $php);
        self::assertStringNotContainsString('X-Forwarded-For', $php);
        self::assertStringNotContainsString('HTTP_HOST', $php);
        self::assertStringContainsString("json_encode(['error' => 'Service unavailable'])", $php);
        self::assertStringContainsString('normaliseSourceUrls($payload)', $php);
        self::assertStringContainsString("return \$this->sourceDomain . ltrim(\$url, '/');", $php);
        self::assertStringContainsString("(\$payload['data'][0]['success'] ?? false) !== true", $php);
        self::assertStringContainsString('function safeURL(value)', $javascript);
        self::assertStringContainsString('result?.meta?.parameters?.no_events_msg', $javascript);
        self::assertStringContainsString('messageElement.textContent = emptyMessage', $javascript);
        self::assertStringNotContainsString("innerHTML = '<p>No events found.</p>'", $javascript);
        self::assertStringContainsString('Authorization Bearer header', $readme);
        self::assertStringContainsString('resolves relative event links', $readme);
    }

    public function testAdministratorHelpDoesNotDocumentQueryStringSecrets(): void
    {
        $pluginHelp = $this->read('plugins/plg_content_jemembed/language/en-GB/plg_content_jemembed.sys.ini');
        $administratorHelp = $this->read('admin/help/en-GB/jemembed.html');

        self::assertStringContainsString('Authorization: Bearer YOUR_SECURITY_TOKEN', $pluginHelp);
        self::assertStringNotContainsString('&amp;token=', $pluginHelp);
        self::assertStringContainsString('meta.next_start', $pluginHelp);
        self::assertStringContainsString('Authorization: Bearer YOUR_SECURITY_TOKEN', $administratorHelp);
        self::assertStringNotContainsString('&token=', $administratorHelp);
        self::assertStringNotContainsString('token=YOUR', $administratorHelp);
        self::assertStringContainsString('&catids=2,3,5', $administratorHelp);
        self::assertStringContainsString('id="feed-container"', $administratorHelp);
        self::assertStringContainsString('featured=only', $administratorHelp);
        self::assertStringContainsString('noeventsmsg=text', $administratorHelp);
    }

    public function testRepositoryDocumentationDoesNotPublishQueryStringCredentials(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = array(
            'docs/documentation-web-structure/articles-final/plugins-jem-embed-plugin.html',
            'docs/documentation-web-structure/public-site-snapshot/documentation-plugins-jem-embed-plugin.txt',
        );
        $checked = false;

        foreach ($paths as $relativePath) {
            $path = $root . '/' . $relativePath;

            if (!is_file($path)) {
                continue;
            }

            $checked = true;
            $contents = file_get_contents($path);
            self::assertIsString($contents, 'Unable to read ' . $relativePath);
            self::assertDoesNotMatchRegularExpression('/(?:\?|&|&amp;)token=/i', $contents);
            self::assertStringContainsString('Authorization: Bearer', $contents);
        }

        if (!$checked) {
            self::markTestSkipped('Repository documentation is not present in this worktree.');
        }
    }

    public function testPackageBuilderRequiresTheEmbedPolicyAndProxy(): void
    {
        $builder = $this->read('scripts/build-packages.php');

        self::assertStringContainsString('packages/plg_content_jemembed.zip', $builder);
        self::assertStringContainsString("'requestpolicy.php'", $builder);
        self::assertStringContainsString("'media/files/jemevents.zip'", $builder);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}

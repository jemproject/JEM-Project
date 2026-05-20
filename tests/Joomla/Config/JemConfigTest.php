<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class JemConfigTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();
    }

    public function testJemHelperLoadsConfigurationFromJoomlaDatabase(): void
    {
        $this->loadJemRuntime();

        $config = JemHelper::config();

        self::assertIsObject($config);
        self::assertSame('media/com_jem/attachments', (string) $config->attachments_path);
        self::assertNotSame('', (string) $config->attachments_types);
        self::assertContains((string) $config->attachments_layout, array('row', 'row_full', 'row_uniform', 'column', 'column_full', 'column_uniform'));
        self::assertContains((string) $config->attachments_icon_size, array('none', 'normal', 'medium', 'large'));
    }

    public function testAttachmentConfigurationDoesNotAllowExecutableDefaults(): void
    {
        $types = array_filter(array_map('trim', explode(',', strtolower($this->configValue('attachments_types')))));
        $unsafe = array(
            'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
            'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp',
            'sh', 'bash', 'cmd', 'bat', 'exe', 'dll', 'so',
            'js', 'mjs', 'html', 'htm', 'xhtml', 'svg',
        );

        self::assertSame(
            array(),
            array_values(array_intersect($types, $unsafe)),
            'JEM attachment configuration should not allow executable or browser-active types by default.'
        );
    }

    public function testRequiredConfigKeysExist(): void
    {
        foreach (array(
            'attachments_path',
            'attachments_maxsize',
            'attachments_types',
            'attachments_layout',
            'attachments_icon_size',
            'globalattribs',
            'css',
            'csv_separator',
            'csv_delimiter',
            'csv_bom',
        ) as $key) {
            self::assertNotNull($this->configValue($key), '#__jem_config should define ' . $key . '.');
        }
    }

    private function configValue(string $key): ?string
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('value'))
            ->from($db->quoteName('#__jem_config'))
            ->where($db->quoteName('keyname') . ' = ' . $db->quote($key));

        $db->setQuery($query);
        $value = $db->loadResult();

        return $value === null ? null : (string) $value;
    }

    private function loadJemRuntime(): void
    {
        require_once JPATH_SITE . '/components/com_jem/factory.php';
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
    }
}

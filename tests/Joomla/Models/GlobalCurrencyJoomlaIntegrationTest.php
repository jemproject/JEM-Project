<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class GlobalCurrencyJoomlaIntegrationTest extends JoomlaTestCase
{
    private DatabaseDriver $db;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootJoomlaSite();
        $this->db = Factory::getContainer()->get(DatabaseDriver::class);

        require_once JPATH_SITE . '/components/com_jem/classes/config.class.php';
        require_once JPATH_SITE . '/components/com_jem/classes/featurepolicy.class.php';
        require_once JPATH_SITE . '/components/com_jem/classes/customfields.class.php';
        require_once JPATH_SITE . '/components/com_jem/classes/money.class.php';
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/fields/currencyoptions.php';
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/settings.php';

        $language = Factory::getContainer()
            ->get(LanguageFactoryInterface::class)
            ->createLanguage('en-GB', false);
        Factory::getApplication()->loadLanguage($language);
        $language->load('com_jem', JPATH_ADMINISTRATOR, 'en-GB', true);
    }

    public function testInstalledDefaultAndCurrencyOptionsUseUniqueSymbolLabels(): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('value'))
            ->from($this->db->quoteName('#__jem_config'))
            ->where($this->db->quoteName('keyname') . ' = ' . $this->db->quote('defaultCurrency'));
        $this->db->setQuery($query);
        self::assertSame('EUR', strtoupper(trim((string) $this->db->loadResult())));

        $field = new class extends JFormFieldCurrencyOptions {
            public function exposedOptions(): array
            {
                return $this->getOptions();
            }
        };
        self::assertTrue($field->setup(
            new SimpleXMLElement('<field name="defaultCurrency" type="currencyoptions" />'),
            'EUR'
        ));

        $actual = array();
        foreach ($field->exposedOptions() as $option) {
            $currency = strtoupper(trim((string) ($option->value ?? '')));
            if ($currency === '') {
                continue;
            }
            self::assertArrayNotHasKey($currency, $actual, 'Currency options must not contain duplicates.');
            $actual[$currency] = (string) ($option->text ?? '');
        }

        $query = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('currency'))
            ->from($this->db->quoteName('#__jem_countries'))
            ->where($this->db->quoteName('currency') . " <> ''")
            ->order($this->db->quoteName('currency') . ' ASC');
        $this->db->setQuery($query);
        $expected = array();
        foreach ((array) $this->db->loadColumn() as $currency) {
            $currency = strtoupper(trim((string) $currency));
            if (preg_match('/^[A-Z]{3}$/D', $currency) === 1) {
                $expected[$currency] = JemMoney::currencyLabel(
                    $currency,
                    Factory::getApplication()->getLanguage()->getTag()
                );
            }
        }
        ksort($expected, SORT_STRING);

        self::assertSame($expected, $actual);
        self::assertSame('EUR (€)', $actual['EUR'] ?? null);
    }

    public function testSettingsRejectCurrencyChangeAfterPricesOrOrdersExist(): void
    {
        if (getenv('JEM_TEST_WRITABLE') !== '1') {
            self::markTestSkipped('Set JEM_TEST_WRITABLE=1 to verify the global currency lock.');
        }

        $eventId = 0;
        $registerId = 0;
        try {
            $eventId = $this->insertEvent('single');
            $this->assertCurrencyChangeBlocked();

            $this->deleteWhere('#__jem_events', 'id', $eventId);
            $eventId = $this->insertEvent('classic');
            $registration = (object) array(
                'event' => $eventId,
                'uid' => -random_int(100000, 999999999),
                'places' => 1,
                'uregdate' => gmdate('Y-m-d H:i:s'),
                'uip' => '127.0.0.1',
                'waiting' => 0,
                'status' => 1,
                'comment' => '',
                'reference' => 'CUR-' . strtoupper(bin2hex(random_bytes(10))),
                'created' => gmdate('Y-m-d H:i:s'),
                'modified' => gmdate('Y-m-d H:i:s'),
                'revision' => 1,
                'pricing_mode' => 'classic',
                'currency' => 'EUR',
            );
            $this->db->insertObject('#__jem_register', $registration, 'id');
            $registerId = (int) $registration->id;

            $this->assertCurrencyChangeBlocked();
        } finally {
            if ($registerId > 0) {
                $this->deleteWhere('#__jem_register', 'id', $registerId);
            }
            if ($eventId > 0) {
                $this->deleteWhere('#__jem_events', 'id', $eventId);
            }
        }
    }

    private function assertCurrencyChangeBlocked(): void
    {
        $model = new JemModelSettings(array('dbo' => $this->db));
        $data = (array) $model->getData();
        $data['defaultCurrency'] = 'USD';
        Factory::getApplication()->input->set('meta_keywords', array());
        Factory::getApplication()->input->set('lastupdate', '');

        self::assertFalse($model->store($data));
        self::assertSame(Text::_('COM_JEM_SETTINGS_DEFAULT_CURRENCY_CHANGE_BLOCKED'), $model->getError());

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('value'))
            ->from($this->db->quoteName('#__jem_config'))
            ->where($this->db->quoteName('keyname') . ' = ' . $this->db->quote('defaultCurrency'));
        $this->db->setQuery($query);
        self::assertSame('EUR', strtoupper(trim((string) $this->db->loadResult())));
    }

    private function insertEvent(string $pricingMode): int
    {
        $suffix = strtolower(bin2hex(random_bytes(6)));
        $event = (object) array(
            'title' => 'PHPUnit global currency ' . $suffix,
            'alias' => 'phpunit-global-currency-' . $suffix,
            'introtext' => '',
            'fulltext' => '',
            'created_by_alias' => '',
            'metadata' => '',
            'pricing_mode' => $pricingMode,
            'currency' => 'EUR',
            'published' => 0,
            'created' => gmdate('Y-m-d H:i:s'),
            'created_by' => 0,
        );
        $this->db->insertObject('#__jem_events', $event, 'id');

        return (int) $event->id;
    }

    private function deleteWhere(string $table, string $column, int $value): void
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName($table))
            ->where($this->db->quoteName($column) . ' = ' . $value);
        $this->db->setQuery($query)->execute();
    }
}

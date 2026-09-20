<?php

declare(strict_types=1);

use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\CMS\Categories\CategoryInterface;
use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Factory;
use Joomla\CMS\Extension\LegacyComponent;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Language\Text;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class CategoryCustomFieldsJoomlaIntegrationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();

        $language = Factory::getContainer()
            ->get(LanguageFactoryInterface::class)
            ->createLanguage('en-GB', false);
        Factory::getApplication()->loadLanguage($language);
        JLoader::registerNamespace(
            'Joomla\\Component\\Fields\\Administrator',
            JPATH_ADMINISTRATOR . '/components/com_fields/src'
        );

        if (!class_exists('JemHelper', false)) {
            require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        }

        require_once JEM_TEST_ROOT . '/site/helpers/category.php';
        require_once JEM_TEST_ROOT . '/site/classes/categorycustomfields.class.php';
    }

    public function testJemRegistersTheEventAndVenueContextsForJoomlaFields(): void
    {
        self::assertTrue(method_exists('JemHelper', 'getContexts'));
        self::assertSame('event', JemHelper::validateSection('event'));
        self::assertSame('venue', JemHelper::validateSection('venue'));
        self::assertNull(JemHelper::validateSection('category'));
        self::assertArrayHasKey('com_jem.event', JemHelper::getContexts());
        self::assertArrayHasKey('com_jem.venue', JemHelper::getContexts());

        $component = new LegacyComponent('com_jem');
        self::assertArrayHasKey('com_jem.event', $component->getContexts());
        self::assertArrayHasKey('com_jem.venue', $component->getContexts());
    }

    public function testJemNameIsTranslatedInTheJoomlaFieldsViews(): void
    {
        $language = Factory::getLanguage();

        self::assertTrue($language->load('com_jem', JEM_TEST_ROOT . '/admin', 'en-GB', true, true));
        self::assertSame('JEM', Text::_('COM_JEM'));
    }

    public function testPublishedJemEventFieldsCanBeLoadedThroughThePublicApi(): void
    {
        Factory::getApplication()->bootComponent('com_fields');
        self::assertTrue(class_exists(FieldsHelper::class));
        self::assertIsArray(JemCategoryCustomFields::getJoomlaEventFields());
        self::assertIsArray(JemCategoryCustomFields::getJoomlaVenueFields());
    }

    public function testJemFieldContextsUseACompatibleEmptyCategoryAdapter(): void
    {
        $component = new LegacyComponent('com_jem');
        $eventCategories = $component->getCategory(array(), 'event');
        $venueCategories = $component->getCategory(array(), 'venue');

        self::assertInstanceOf(CategoryInterface::class, $eventCategories);
        self::assertInstanceOf(CategoryInterface::class, $venueCategories);
        self::assertInstanceOf(CategoryNode::class, $eventCategories->get('root'));
        self::assertInstanceOf(CategoryNode::class, $venueCategories->get('root'));
        self::assertFalse($eventCategories->get('root')->hasChildren());
        self::assertFalse($venueCategories->get('root')->hasChildren());
        self::assertNull($eventCategories->get(0));
        self::assertNull($venueCategories->get(0));
        self::assertSame(JemEventCategories::class, get_class($eventCategories));
        self::assertSame(JemVenueCategories::class, get_class($venueCategories));
    }

    public function testJoomlaFieldGroupsBecomeSeparateJemEditTabs(): void
    {
        $field = static function (int $id, string $name): object {
            return new class ($id, $name) {
                public string $label;
                public string $input;

                public function __construct(private int $id, string $name)
                {
                    $this->label = '<label>' . $name . '</label>';
                    $this->input = '<input name="jform[com_fields][' . strtolower($name) . ']">';
                }

                public function getAttribute(string $name): ?string
                {
                    return $name === 'data-jem-field-id' ? (string) $this->id : null;
                }
            };
        };
        $fieldsets = array(
            'fields-7' => (object) array(
                'label'       => 'Venue facilities',
                'description' => 'Facility details',
            ),
            'fields-8' => (object) array(
                'label'       => 'Venue access',
                'description' => '',
            ),
        );
        $fields = array(
            'fields-7' => array($field(42, 'Parking')),
            'fields-8' => array($field(43, 'Transport')),
        );
        $form = new class ($fieldsets, $fields) {
            public function __construct(private array $fieldsets, private array $fields)
            {
            }

            public function getFieldsets(string $group): array
            {
                return $group === 'com_fields' ? $this->fieldsets : array();
            }

            public function getFieldset(string $name): array
            {
                return $this->fields[$name] ?? array();
            }
        };

        HTMLHelper::_('uitab.startTabSet', 'jem-editvenue-tabs');
        $html = JemCategoryCustomFields::renderJoomlaFormTabs(
            $form,
            'jem-editvenue-tabs',
            'venue-fields',
            'venue'
        );
        HTMLHelper::_('uitab.endTabSet');

        self::assertStringContainsString('Venue facilities', $html);
        self::assertStringContainsString('Venue access', $html);
        self::assertStringContainsString('data-jem-venue-joomla-field-id="42"', $html);
        self::assertStringContainsString('data-jem-venue-joomla-field-id="43"', $html);
        self::assertSame(2, substr_count($html, '<joomla-tab-element'));
    }
}

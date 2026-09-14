<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\LanguageFactoryInterface;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class EventTimezoneFieldTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();
    }

    public function testCustomTimezoneStartsWithARealEmptyOption(): void
    {
        $language = Factory::getContainer()
            ->get(LanguageFactoryInterface::class)
            ->createLanguage('en-GB', false);
        Factory::getApplication()->loadLanguage($language);
        $language->load('com_jem', JPATH_ADMINISTRATOR . '/components/com_jem', 'en-GB', true);
        Form::addFieldPath(JEM_TEST_ROOT . '/admin/models/fields');

        $form = Form::getInstance(
            'com_jem.test.eventtimezone',
            JEM_TEST_ROOT . '/admin/models/forms/event.xml',
            array('control' => 'jform')
        );
        $form->bind(array('timezone_mode' => 'custom', 'timezone' => ''));

        $field = $form->getField('timezone');
        $method = new ReflectionMethod($field, 'getGroups');
        $groups = $method->invoke($field);

        self::assertArrayHasKey(0, $groups);
        self::assertSame('', $groups[0][0]->value, 'The first timezone option must have a real empty value.');
        self::assertContains(
            $groups[0][0]->text,
            array('- Select event timezone -', 'COM_JEM_EVENT_TIMEZONE_SELECT')
        );
        self::assertArrayHasKey('Africa', $groups);
        self::assertSame('Africa/Abidjan', $groups['Africa'][0]->value);
    }
}

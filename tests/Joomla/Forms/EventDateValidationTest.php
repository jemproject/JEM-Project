<?php

declare(strict_types=1);

use Joomla\CMS\Form\Form;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventDateValidationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();

        $language = Factory::getContainer()
            ->get(LanguageFactoryInterface::class)
            ->createLanguage('en-GB', false);
        Factory::getApplication()->loadLanguage($language);
        $language->load('com_jem', JEM_TEST_ROOT . '/site', 'en-GB', true);

        if (!class_exists('JemHelper')) {
            require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        }
    }

    public function testExactCalendarDateValidationRejectsRolloverDates(): void
    {
        self::assertTrue(JemHelper::isValidCalendarDate('2028-02-29'));
        self::assertTrue(JemHelper::isValidCalendarDate('2026-08-04 12:30:59', 'Y-m-d H:i:s'));

        self::assertFalse(JemHelper::isValidCalendarDate('2027-02-29'));
        self::assertFalse(JemHelper::isValidCalendarDate('2027-04-31'));
        self::assertFalse(JemHelper::isValidCalendarDate('2027-00-10'));
        self::assertFalse(JemHelper::isValidCalendarDate('2027-10-00'));
        self::assertFalse(JemHelper::isValidCalendarDate(''));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function calendarFieldProvider(): iterable
    {
        yield 'backend event form field' => array(JEM_TEST_ROOT . '/admin/models/fields');
        yield 'frontend event form field' => array(JEM_TEST_ROOT . '/site/models/fields');
    }

    #[DataProvider('calendarFieldProvider')]
    public function testCalendarFieldAllowsBlankAndValidDates(string $fieldPath): void
    {
        $field = $this->calendarField($fieldPath);

        self::assertSame('', $field->filter(''));
        self::assertStringStartsWith('2028-02-29 ', (string) $field->filter('2028-02-29'));
    }

    #[DataProvider('calendarFieldProvider')]
    public function testCalendarFieldRejectsInvalidNonEmptyDate(string $fieldPath): void
    {
        $field = $this->calendarField($fieldPath);

        $this->expectException(Exception::class);
        $field->filter('2027-02-29');
    }

    private function calendarField(string $fieldPath): object
    {
        Form::addFieldPath($fieldPath);

        $form = new Form('com_jem.test.strictdate', array('control' => 'jform'));
        $form->load(
            '<form><field name="dates" type="calendarjem" label="COM_JEM_STARTDATE" '
            . 'format="%Y-%m-%d" filterformat="Y-m-d" filter="string" showtime="false" /></form>'
        );
        $field = $form->getField('dates');

        self::assertNotFalse($field);

        return $field;
    }
}

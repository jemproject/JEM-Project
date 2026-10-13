<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real Joomla form binding/filtering; no site boot, database writes or installation. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class RecurrenceExclusionsFormTest extends TestCase
{
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to Joomla. No database is used.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_ROOT', $root);
        define('JPATH_BASE', $root);
        define('JPATH_ADMINISTRATOR', $root . '/administrator');
        define('JPATH_SITE', 'jemrecurrence://site');
        Factory::$application = new stdClass();
        require_once JEM_TEST_ROOT . '/admin/models/fields/recurrenceexclusions.php';
        Form::addFieldPath(JEM_TEST_ROOT . '/admin/models/fields');
    }

    public function testAdminAndFrontendFormsKeepSelectionAndClearItExplicitly(): void
    {
        foreach (['admin', 'site'] as $client) {
            $source = simplexml_load_file(JEM_TEST_ROOT . '/' . $client . '/models/forms/event.xml');
            $element = $source->xpath('//field[@name="recurrence_exclusions"]')[0];
            $form = new Form('recurrence.' . $client, ['control' => 'jform']);
            $form->setDatabase($this->createMock(\Joomla\Database\DatabaseInterface::class));
            $form->setCurrentUser($this->createMock(\Joomla\CMS\User\User::class));
            self::assertTrue($form->load('<form>' . $element->asXML() . '</form>'));
            self::assertTrue($form->bind(['recurrence_exclusions' => ['day:3', 'type:2']]));
            $field = $form->getField('recurrence_exclusions');
            self::assertInstanceOf(JFormFieldRecurrenceexclusions::class, $field);
            self::assertSame('jform[recurrence_exclusions][]', $field->name);
            self::assertSame(['day:3', 'type:2'], $field->value);
            self::assertSame(['day:3', 'type:2'], $form->filter(['recurrence_exclusions' => ['day:3', 'type:2']])['recurrence_exclusions']);
            self::assertSame([], JemRecurrenceExclusions::normalise($form->filter(['recurrence_exclusions' => ['']])['recurrence_exclusions']));
            self::assertArrayNotHasKey('recurrence_exclusions', $form->filter([]));
        }
    }
}

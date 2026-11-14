<?php

declare(strict_types=1);

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Response\JsonResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Loading shared fields must not emit bytes into an editor's JSON response. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class JemTypeFieldOutputTest extends TestCase
{
    /**
     * Load real Joomla form classes with database access disabled.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $root = (string) getenv('JEM_TEST_JOOMLA_ROOT');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a read-only Joomla tree.');
        }
        require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
    }

    /**
     * Cover both editor entry points, with and without preceding legal JSON whitespace.
     *
     * @return iterable Named editor/output scenarios.
     */
    public static function editorResponses(): iterable
    {
        foreach (['site', 'admin'] as $client) {
            yield $client . ' JSON only' => [$client, ''];
            yield $client . ' JSON after whitespace' => [$client, "\r\n"];
        }
    }

    /**
     * Resolve the actual event type field and decode the resulting JSON unchanged.
     *
     * @param string $client Editor form and field directory.
     * @param string $prefix Valid response whitespace emitted before field loading.
     * @return void
     */
    #[DataProvider('editorResponses')]
    public function testLoadingTheTypeFieldKeepsJsonValid(string $client, string $prefix): void
    {
        Form::addFieldPath(JEM_TEST_ROOT . '/' . $client . '/models/fields');
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $client . '/models/forms/event.xml');
        $definition = $xml->xpath('//field[@name="type_id"]')[0];
        $form = new Form('com_jem.event', ['control' => 'jform']);
        self::assertTrue($form->load('<form>' . $definition->asXML() . '</form>'));
        $payload = ['required' => false];

        ob_start();
        try {
            echo $prefix;
            $field = $form->getField('type_id');
            echo new JsonResponse($payload, null, false, true);
            $body = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertInstanceOf(FormField::class, $field);
        self::assertSame('Jemtype', $field->type);
        self::assertSame($prefix . (string) new JsonResponse($payload, null, false, true), $body);
        self::assertSame($payload, json_decode($body, true, 512, JSON_THROW_ON_ERROR)['data']);
    }
}

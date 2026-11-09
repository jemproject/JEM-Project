<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\Input\Input;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class RecurrencePreviewDateTest extends TestCase
{
    /** Load actual Joomla fields and JEM code without a database connection. */
    protected function setUp(): void
    {
        if (!is_file((string) getenv('JEM_TEST_JOOMLA_ROOT') . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a Joomla 5/6 tree.');
        }
        require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
        require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        require_once JEM_TEST_ROOT . '/site/classes/controller.form.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/recurrencechange.class.php';
        require_once JEM_TEST_ROOT . '/admin/models/event.php';
    }

    /** @return iterable<string, array{string, string, string}> Editor and locale formats. */
    public static function formats(): iterable
    {
        foreach (['site', 'admin'] as $side) {
            foreach (['de-DE' => ['%d.%m.%Y', 'd.m.Y'], 'es-ES' => ['%d-%m-%Y', 'd-m-Y'],
                'fr-FR' => ['%d/%m/%Y', 'd/m/Y'], 'en-GB' => ['%Y-%m-%d', 'Y-m-d'],
                'en-US' => ['%m/%d/%Y', 'm/d/Y']] as $tag => $formats) {
                yield $side . ' ' . $tag => [$side, ...$formats];
            }
        }
    }

    /** Verify unchanged dates and independent events pass through the actual AJAX action. */
    #[DataProvider('formats')]
    public function testExistingEventDatesUseTheSameFilteringAsSave(string $side, string $display, string $filter): void
    {
        [$controller, $model, $app] = $this->editor($side, $display, $filter);
        $data = ['id' => 1, 'title' => '<strong>Unchanged content</strong>'];
        foreach (['dates', 'enddates', 'recurrence_limit_date'] as $name) {
            $data[$name] = (new DateTimeImmutable($model->stored[$name]))->format($filter);
        }
        $response = $this->preview($controller, $app, $data);
        self::assertTrue($response['success'], $response['message'] ?? '');
        self::assertFalse($response['data']['required']);
        foreach (['dates', 'enddates', 'recurrence_limit_date'] as $name) {
            self::assertSame($model->stored[$name], $model->candidate[$name]);
        }
        self::assertSame($data['title'], $model->candidate['title']);
        self::assertSame($data, $app->input->post->get('jform', [], 'array'));
        self::assertTrue($controller->tokenChecked);
        self::assertTrue($app->closed);

        // Normal save passes form-filtered data directly to the model, without a second conversion.
        $model->previewRecurrenceChange($model->form->filter($data) + ['id' => 1]);
        self::assertSame('2026-11-08', $model->candidate['dates']);
    }

    /** The preview and save must calculate identical decisions and fingerprints for a series. */
    #[DataProvider('formats')]
    public function testRecurrenceDecisionsMatchSaveAndKeepRegistrationProtection(string $side, string $display, string $filter): void
    {
        [$controller, $model, $app] = $this->editor($side, $display, $filter);
        $model->stored['recurrence_type'] = 2;
        $model->children = [array_replace($model->stored, ['id' => 2, 'recurrence_first_id' => 1])];
        $model->registrations = [['id' => 1, 'event' => 2, 'status' => 1]];
        $unchanged = ['id' => 1, 'dates' => (new DateTimeImmutable('2026-11-08'))->format($filter)];
        self::assertFalse($this->preview($controller, $app, $unchanged)['data']['required']);
        $changed = ['id' => 1, 'dates' => (new DateTimeImmutable('2026-11-09'))->format($filter)];
        $response = $this->preview($controller, $app, $changed);
        self::assertTrue($response['success'], $response['message'] ?? '');
        self::assertTrue($response['data']['required']);
        self::assertFalse($response['data']['deleteAllowed']);
        self::assertSame('2026-11-15', $model->candidate['enddates'], 'Omitted ISO dates must not be localised again.');
        $savedPlan = $model->previewRecurrenceChange($model->form->filter($changed) + ['id' => 1]);
        self::assertSame($savedPlan['fingerprint'], $response['data']['fingerprint']);
    }

    /** Keep empty dates and reject impossible dates in each of the three submitted fields. */
    #[DataProvider('formats')]
    public function testOpenDatesAndInvalidDates(string $side, string $display, string $filter): void
    {
        [$controller, $model, $app] = $this->editor($side, $display, $filter);
        $empty = ['id' => 1, 'dates' => '', 'enddates' => '', 'recurrence_limit_date' => ''];
        $response = $this->preview($controller, $app, $empty);
        self::assertTrue($response['success'], $response['message'] ?? '');
        self::assertEmpty($model->candidate['dates']);
        $invalid = str_replace(['Y', 'm', 'd'], ['2026', '11', '31'], $filter);
        foreach (['dates', 'enddates', 'recurrence_limit_date'] as $name) {
            $model->candidate = [];
            $response = $this->preview($controller, $app, ['id' => 1, $name => $invalid]);
            self::assertFalse($response['success']);
            self::assertStringContainsString('not a valid calendar date', $response['message']);
            self::assertSame([], $model->candidate);
        }
    }

    /**
     * Canonical preview data must not depend on the request's translated date format.
     * @param string $side Editor source directory.
     * @param string $display Request calendar format.
     * @param string $filter Request PHP date format.
     * @return void
     */
    #[DataProvider('formats')]
    public function testCanonicalDatesIgnorePreviewLanguage(string $side, string $display, string $filter): void
    {
        [$controller, $model, $app] = $this->editor($side, $display, $filter);
        $data = ['id' => 1, 'dates' => '2026-11-08', 'enddates' => '2026-11-15',
            'recurrence_limit_date' => '2026-12-20', 'title' => '<strong>Unchanged title</strong>'];
        $response = $this->preview($controller, $app, $data, 'iso');
        self::assertTrue($response['success'], $response['message'] ?? '');
        self::assertFalse($response['data']['required']);
        foreach (['dates', 'enddates', 'recurrence_limit_date', 'title'] as $name) {
            self::assertSame($data[$name], $model->candidate[$name]);
        }
        self::assertSame($data, $app->input->post->get('jform', [], 'array'));
        self::assertTrue($controller->tokenChecked);
        self::assertTrue($app->closed);

        // Older JS/overrides without the protocol marker retain localised filtering.
        $local = ['id' => 1, 'dates' => (new DateTimeImmutable('2026-11-08'))->format($filter)];
        self::assertTrue($this->preview($controller, $app, $local)['success']);
        self::assertSame('2026-11-08', $model->candidate['dates']);
    }

    /**
     * The ISO preview and normal localised save must agree, including booking safeguards.
     * @param string $side Editor source directory.
     * @param string $display Request calendar format.
     * @param string $filter Request PHP date format.
     * @return void
     */
    #[DataProvider('formats')]
    public function testCanonicalDatesKeepRecurrenceDecision(string $side, string $display, string $filter): void
    {
        [$controller, $model, $app] = $this->editor($side, $display, $filter);
        $model->stored['recurrence_type'] = 2;
        $model->children = [array_replace($model->stored, ['id' => 2, 'recurrence_first_id' => 1])];
        $model->registrations = [['id' => 1, 'event' => 2, 'status' => 1]];
        $response = $this->preview($controller, $app, ['id' => 1, 'dates' => '2026-11-09'], 'iso');
        self::assertTrue($response['success'], $response['message'] ?? '');
        self::assertTrue($response['data']['required']);
        self::assertFalse($response['data']['deleteAllowed']);
        self::assertSame('2026-11-15', $model->candidate['enddates']);
        self::assertSame('2026-12-20', $model->candidate['recurrence_limit_date']);
        $local = ['id' => 1, 'dates' => (new DateTimeImmutable('2026-11-09'))->format($filter)];
        $saved = $model->previewRecurrenceChange($model->form->filter($local) + ['id' => 1]);
        self::assertSame($saved['fingerprint'], $response['data']['fingerprint']);
        self::assertSame($saved['deleteAllowed'], $response['data']['deleteAllowed']);
    }

    /**
     * Explicit ISO dates are strict; empty/omitted values retain the event's existing rules.
     * @param string $side Editor source directory.
     * @param string $display Request calendar format.
     * @param string $filter Request PHP date format.
     * @return void
     */
    #[DataProvider('formats')]
    public function testCanonicalDatesRejectMalformedValues(string $side, string $display, string $filter): void
    {
        [$controller, $model, $app] = $this->editor($side, $display, $filter);
        foreach (['dates', 'enddates', 'recurrence_limit_date'] as $name) {
            foreach (['2026-11-31', '2026-2-03', '2026-11-13 00:00:00', '13.11.2026',
                '0000-00-00', "2026-11-13\0", [], null, ' ', '2026-11-13extra'] as $invalid) {
                $model->candidate = [];
                $response = $this->preview($controller, $app, ['id' => 1, $name => $invalid], 'iso');
                self::assertFalse($response['success']);
                self::assertStringContainsString('not a valid calendar date', $response['message']);
                self::assertSame([], $model->candidate);
            }
        }
        $response = $this->preview($controller, $app, ['id' => 1, 'dates' => '2028-02-29'], 'iso');
        self::assertTrue($response['success'], $response['message'] ?? '');
        self::assertSame('2028-02-29', $model->candidate['dates']);
        self::assertTrue($this->preview($controller, $app, ['id' => 1], 'iso')['success']);
        self::assertSame('2026-11-08', $model->candidate['dates']);
        self::assertTrue($this->preview($controller, $app, ['id' => 1, 'dates' => '',
            'enddates' => '', 'recurrence_limit_date' => ''], 'iso')['success']);
        self::assertEmpty($model->candidate['dates']);

        $model->stored['recurrence_type'] = 2;
        $response = $this->preview($controller, $app, ['id' => 1, 'recurrence_limit_date' => ''], 'iso');
        self::assertFalse($response['success']);
        self::assertSame('Please enter an end date for the recurrence.', $response['message']);
        $response = $this->preview($controller, $app, ['id' => 1, 'recurrence_limit_date' => '2026-11-07'], 'iso');
        self::assertFalse($response['success']);
        self::assertStringContainsString('cannot be earlier', $response['message']);
    }

    /** @return array Controller, model and application with real form fields and deterministic stored data. */
    private function editor(string $side, string $display, string $filter): array
    {
        $language = Factory::getApplication()->getLanguage();
        $strings = new ReflectionProperty($language, 'strings');
        $strings->setValue($language, array_replace($strings->getValue($language), [
            'DATE_FORMAT_CALENDAR_DATE' => $display, 'DATE_FORMAT_FILTER_DATE' => $filter,
        ]));
        Form::addFieldPath(JEM_TEST_ROOT . '/' . $side . '/models/fields');
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $side . '/models/forms/event.xml');
        $fields = $xml->xpath('//field[@name="dates" or @name="enddates" or @name="recurrence_limit_date"]');
        $form = new Form('recurrence.preview.' . $side, ['control' => 'jform']);
        $form->load('<form>' . implode('', array_map(static fn ($field) => $field->asXML(), $fields)) . '</form>');

        $model = new class ($form) extends JemModelEvent {
            public array $stored = ['id' => 1, 'dates' => '2026-11-08', 'enddates' => '2026-11-15',
                'recurrence_limit_date' => '2026-12-20', 'recurrence_type' => 0, 'recurrence_first_id' => 0,
                'recurrence_number' => 1, 'recurrence_byday' => '', 'recurrence_bylastday' => '', 'attribs' => '{}'];
            public array $candidate = [];
            public array $children = [];
            public array $registrations = [];

            /** Inject the real calendar form without booting the MVC model. */
            public function __construct(public Form $form)
            {
            }

            /** Return the editor form; database-dependent form plugins are outside this test. */
            public function getForm($data = [], $loadData = true)
            {
                return $this->form;
            }

            /** Return a stored-event fixture in place of a database table. */
            public function getTable($type = 'Event', $prefix = 'JemTable', $config = [])
            {
                return new class ($this->stored) {
                    public int $recurrence_number = 1;
                    public string $recurrence_byday = '';
                    public string $recurrence_bylastday = '';
                    public string $attribs = '{}';
                    /** Keep the original stored ISO values available to the model. */
                    public function __construct(private array $stored)
                    {
                    }
                    /** Simulate loading only the fixture event. */
                    public function load($id): bool
                    {
                        return $id === 1;
                    }
                    /** Return the stored row. */
                    public function getProperties(): array
                    {
                        return $this->stored;
                    }
                };
            }

            /** Run the real candidate normalisation and recurrence planner without a database snapshot. */
            public function previewRecurrenceChange(array $data, bool $lock = false): array
            {
                $this->candidate = $this->recurrenceChangeCandidate($data);
                return JemRecurrenceChange::plan(
                    ['root' => $this->stored, 'children' => $this->children, 'registrations' => $this->registrations],
                    $this->candidate,
                    static fn (): bool => true,
                    '2026-11-08'
                );
            }
        };
        $app = new class (Factory::getApplication()) {
            public Input $input;
            public bool $closed = false;
            /** Delegate language/document access to the read-only Joomla bootstrap. */
            public function __construct(private object $base)
            {
                $this->input = new class ([]) extends Input {
                    public Input $post;
                };
            }
            /** Forward Joomla services required by the real fields. */
            public function __call($name, $args)
            {
                return $this->base->$name(...$args);
            }
            /** Capture-only response headers; no HTTP response is sent by a test. */
            public function setHeader(...$args): void
            {
            }
            /** Do not send native HTTP headers from PHPUnit. */
            public function sendHeaders(): void
            {
            }
            /** Supply an empty Joomla message queue to JsonResponse. */
            public function getMessageQueue(): array
            {
                return [];
            }
            /** Record completion without exiting PHPUnit. */
            public function close(): void
            {
                $this->closed = true;
            }
        };
        Factory::$application = $app;
        $controller = new class ($model, $app) extends JemControllerForm {
            public bool $tokenChecked = false;
            /** Inject test dependencies without registering routes or loading a session. */
            public function __construct(private object $model, object $app)
            {
                $this->app = $app;
                $this->input = $app->input;
            }
            /** Return the actual JEM model subclass used by the fixture. */
            public function getModel($name = '', $prefix = '', $config = [])
            {
                return $this->model;
            }
            /** Record the unchanged CSRF gate; token authentication itself belongs to Joomla. */
            public function checkToken($method = 'post', $redirect = true)
            {
                $this->tokenChecked = true;
                return true;
            }
            /** Permit only the fixture event in this authorised-editor test. */
            protected function allowEdit($data = [], $key = 'id')
            {
                return (int) ($data[$key] ?? 0) === 1;
            }
        };
        return [$controller, $model, $app];
    }

    /**
     * Invoke the production preview action using the selected date transport.
     * @param object $controller Authorised fixture controller.
     * @param object $app Capture-only application services.
     * @param array $data Submitted event fields.
     * @param string $dateFormat Explicit transport marker, or empty for legacy clients.
     * @return array Decoded JSON response from the actual preview action.
     */
    private function preview(object $controller, object $app, array $data, string $dateFormat = ''): array
    {
        $app->input->post = new Input(['jform' => $data, 'jem_recurrence_date_format' => $dateFormat]);
        ob_start();
        try {
            $controller->recurrencePreview();
            return json_decode((string) ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
    }
}

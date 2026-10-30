<?php

declare(strict_types=1);

// Only identity grants, routing and application setup are fixtures. Controllers,
// models, Joomla's token check, input handling and SQL execution are production code.
final class PublicationDatabase extends \Joomla\Database\Sqlite\SqliteDriver
{
    public static function isSupported()
    {
        return extension_loaded('pdo_sqlite');
    }

    public function escape($text, $extra = false)
    {
        $this->connect();
        return substr($this->connection->quote((string) $text), 1, -1);
    }
}

final class JemFactory
{
    public static function getUser(): self
    {
        return new self();
    }

    public function get($key)
    {
        return $key === 'id' ? 7 : null;
    }

    public function can($action, $entity, $id, $owner): bool
    {
        return $action === 'publish' && in_array($entity, ['event', 'venue'], true)
            && $owner === 7 && in_array($id, [1, 3, 4, 5, 6, 7], true);
    }
}

final class JemHelperRoute
{
    public static function getMyEventsRoute(): string
    {
        return 'index.php?option=com_jem&view=myevents';
    }

    public static function getMyVenuesRoute(): string
    {
        return 'index.php?option=com_jem&view=myvenues';
    }
}

final class PublicationApplication
{
    public \Joomla\Input\Input $input;
    public array $messages = [];
    private object $session;

    public function __construct()
    {
        $this->input = new \Joomla\Input\Input();
        $this->session = new class {
            public function get($key)
            {
                $user = new \Joomla\CMS\User\User();
                $user->id = 7;
                return $user;
            }

            public function getToken($forceNew = false): string
            {
                return 'synthetic-publication-session';
            }

            public function isNew(): bool
            {
                return false;
            }
        };
    }

    public function request(array $ids, bool $validToken = true): void
    {
        $this->input = new \Joomla\Input\Input(['cid' => $ids]);
        $this->input->post->set(\Joomla\CMS\Session\Session::getFormToken(), $validToken ? 1 : '');
    }

    public function getInput(): \Joomla\Input\Input
    {
        return $this->input;
    }

    public function getSession(): object
    {
        return $this->session;
    }

    public function get($key): string
    {
        return 'synthetic-publication-secret';
    }

    public function enqueueMessage($message, $type): void
    {
        $this->messages[] = [$message, $type];
    }
}

trait PublicationControllerFixture
{
    private \Joomla\CMS\MVC\Model\BaseDatabaseModel $fixtureModel;

    public function __construct($model)
    {
        $this->fixtureModel = $model;
    }

    public function getModel($name = '', $prefix = '', $config = [])
    {
        return $this->fixtureModel;
    }

    public function result(): array
    {
        return [$this->message, $this->messageType, $this->redirect];
    }
}

final class PublicationEventsController extends JemControllerMyevents
{
    use PublicationControllerFixture;
}

final class PublicationVenuesController extends JemControllerMyvenues
{
    use PublicationControllerFixture;
}

function jexit($message): void
{
    throw new RuntimeException($message);
}

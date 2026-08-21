<?php

declare(strict_types=1);

namespace Joomla\CMS\Language {
    if (!class_exists(Text::class, false)) {
        final class Text
        {
            public static function _($key): string
            {
                return (string) $key;
            }
        }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;

    require_once JEM_TEST_ROOT . '/site/classes/frontendaccess.class.php';

    final class FrontendAccessPolicyTest extends TestCase
    {
        public function testCanonicalAndLegacyIdsAreNormalised(): void
        {
            $canonical = new FrontendAccessInputStub(array('a_id' => '42'));
            self::assertSame(42, JemFrontendAccess::normaliseRecordId($canonical, true));
            self::assertSame(42, $canonical->value('id'));

            $legacy = new FrontendAccessInputStub(array('id' => '73:event-alias'));
            self::assertSame(73, JemFrontendAccess::normaliseRecordId($legacy, true));
            self::assertSame(73, $legacy->value('a_id'));
        }

        public function testCanonicalEditorIdOverridesAnUnrelatedRoutedItemId(): void
        {
            $input = new FrontendAccessInputStub(array(
                'a_id' => '13',
                'id' => '12:source-event',
            ));

            self::assertSame(13, JemFrontendAccess::normaliseRecordId($input, true));
            self::assertSame(13, $input->value('a_id'));
            self::assertSame(13, $input->value('id'));
        }

        public function testMalformedAndMissingEditorIdsAreRejected(): void
        {
            foreach (array(
                new FrontendAccessInputStub(array('id' => '12invalid')),
                new FrontendAccessInputStub(array()),
                new FrontendAccessInputStub(array('id' => '12', 'a_id' => '13invalid')),
            ) as $input) {
                try {
                    JemFrontendAccess::normaliseRecordId($input, true);
                    self::fail('Invalid editor id was accepted.');
                } catch (Exception $exception) {
                    self::assertSame(400, $exception->getCode());
                }
            }
        }

        public function testGenericIdReaderStillRejectsAmbiguousValues(): void
        {
            $input = new FrontendAccessInputStub(array('id' => '12', 'a_id' => '13'));

            $this->expectException(Exception::class);
            $this->expectExceptionCode(400);

            JemFrontendAccess::readId($input, array('a_id', 'id'), true);
        }

        public function testEventCreatePermissionReceivesSelectedCategories(): void
        {
            $user = new FrontendAccessUserStub(array(1), true);

            self::assertTrue(JemFrontendAccess::canAdd($user, 'event', array(8, '9', 0)));
            self::assertSame(array('add', 'event', false, false, array(8, 9)), $user->lastCanArguments);
        }

        public function testEditPermissionRequiresViewLevelAndUsesStoredOwner(): void
        {
            $user = new FrontendAccessUserStub(array(1, 3), true);
            $item = (object) array('id' => 27, 'created_by' => 14, 'access' => 3);

            self::assertTrue(JemFrontendAccess::canEdit($user, 'venue', $item));
            self::assertSame(array('edit', 'venue', 27, 14), $user->lastCanArguments);

            $item->access = 7;
            self::assertFalse(JemFrontendAccess::canEdit($user, 'venue', $item));
        }

        public function testDetailedEditDecisionExplainsHiddenAndMissingRecords(): void
        {
            $user = new FrontendAccessUserStub(array(1, 3), true);

            $missing = JemFrontendAccess::decideEdit($user, 'event', null);
            self::assertSame(JemAccessDecision::RECORD_NOT_FOUND, $missing->getCode());
            self::assertSame(404, $missing->getHttpStatus());

            $hidden = JemFrontendAccess::decideEdit(
                $user,
                'event',
                (object) array('id' => 81, 'created_by' => 4, 'access' => 7)
            );
            self::assertSame(JemAccessDecision::VIEW_LEVEL_DENIED, $hidden->getCode());
            self::assertSame('joomla_view_level', $hidden->getSource());
            self::assertSame(404, $hidden->getHttpStatus());
        }

        public function testLegacyBooleanUserDecoratorsRemainCompatible(): void
        {
            $user = new FrontendAccessUserStub(array(1), false);
            $decision = JemFrontendAccess::decideAdd($user, 'venue');

            self::assertFalse($decision->isAllowed());
            self::assertSame(JemAccessDecision::ACTION_NOT_ALLOWED, $decision->getCode());
            self::assertSame('legacy_boolean', $decision->getSource());
            self::assertSame(array('add', 'venue', false, false, false), $user->lastCanArguments);
        }
    }

    final class FrontendAccessInputStub
    {
        /** @var array<string, mixed> */
        private array $data;

        /** @param array<string, mixed> $data */
        public function __construct(array $data)
        {
            $this->data = $data;
        }

        public function exists($key): bool
        {
            return array_key_exists((string) $key, $this->data);
        }

        public function get($key, $default = null, $filter = 'cmd')
        {
            return $this->data[(string) $key] ?? $default;
        }

        public function set($key, $value): void
        {
            $this->data[(string) $key] = $value;
        }

        public function value(string $key)
        {
            return $this->data[$key] ?? null;
        }
    }

    final class FrontendAccessUserStub
    {
        /** @var list<int> */
        private array $levels;

        private bool $result;

        /** @var array<int, mixed> */
        public array $lastCanArguments = array();

        /** @param list<int> $levels */
        public function __construct(array $levels, bool $result)
        {
            $this->levels = $levels;
            $this->result = $result;
        }

        public function getAuthorisedViewLevels(): array
        {
            return $this->levels;
        }

        public function can(...$arguments): bool
        {
            $this->lastCanArguments = $arguments;

            return $this->result;
        }
    }
}

# Getting Started With JEM Tests

JEM now includes an automated test suite intended to make everyday development safer, faster, and easier to review. The goal is not to replace manual Joomla testing, but to catch common regressions before a change reaches a browser or a release ZIP.

The test suite is deliberately split into layers. Most checks are fast and do not need Joomla. Joomla integration tests are available separately for developers who have a local Joomla 5 installation.

## Why These Tests Exist

JEM has a broad surface area: backend forms, frontend forms, legacy Joomla MVC classes, SQL migrations, language files, media assets, attachments, links, sample data, packaging, and security-sensitive upload paths. A small change can easily affect something far away.

These tests help us answer practical questions before committing code:

- Did PHP syntax stay valid?
- Are XML form files still well formed?
- Are language keys still present and unique?
- Are attachment and link options consistent between backend and frontend?
- Are security guardrails still present for SQL injection, XSS, CSRF, permissions, uploads, file paths, secrets, and suspicious code?
- Does the package build exclude development files?
- Can a local Joomla 5 installation load JEM tables, models, schema, and configuration?

## Test Layers

### Static Tests

Static tests inspect repository files directly. They do not boot Joomla, do not connect to a database, and do not need a web server.

Use them for contracts that can be verified from source files:

- PHP syntax.
- XML validity and form field contracts.
- Language key usage.
- View/template structure.
- CSS and asset contracts.
- SQL defaults and migrations.
- Sample data expectations.
- Security and malware/backdoor guardrails.
- Package exclusion rules and ZIP artifact inspection.

Run them with:

```bash
composer test:static
```

### Unit Tests

Unit tests cover pure PHP helpers and small behavior that can run without Joomla.

Use them for:

- Encoding normalization.
- Attachment display helper behavior.
- Boundary/value normalization.

Run them with:

```bash
composer test:unit
composer test:unit:helpers
```

### Joomla Integration Tests

Joomla integration tests boot a local Joomla 5 installation and inspect the installed JEM component. They are separate from the default test command because they need local setup.

Current Joomla tests are read-only. They do not create, update, or delete records. They verify:

- Joomla can boot without executing a web request.
- The database connection is available.
- `com_jem` is installed and enabled.
- JEM tables and critical columns exist.
- JEM configuration can be loaded.
- JEM table and model classes can be instantiated.

Configure the local Joomla root first:

```bash
export JEM_TEST_JOOMLA_ROOT=/path/to/joomla/root
composer test:joomla
```

PowerShell example:

```powershell
$env:JEM_TEST_JOOMLA_ROOT = 'C:\xampp\htdocs\jl542'
composer test:joomla
```

## Quick Start

Install dependencies:

```bash
composer install
```

Run the fast suite:

```bash
composer test
```

This runs static and unit tests only. It is the command most developers should run before committing.

Run all read-only Joomla integration tests:

```bash
composer test:joomla
```

Run a focused block:

```bash
composer test:static:security
composer test:static:xml
composer test:static:package
composer test:joomla:schema
composer test:joomla:models
```

## Useful PHPUnit Options

Composer forwards arguments after `--` to PHPUnit.

Readable test names:

```bash
composer test -- --testdox
```

Detailed execution output:

```bash
composer test -- --debug
```

Stop on the first failure:

```bash
composer test -- --stop-on-failure
```

Run one class or method:

```bash
composer test:static -- --filter FormValidationContractsTest
composer test:joomla -- --filter JemSchemaTest
```

List available suites:

```bash
vendor/bin/phpunit --list-suites
```

## Reading Failures

When a test fails, PHPUnit reports the test class, method, data set, source file, and line number. Many JEM assertions also include the project file being checked.

Example shape:

```text
FormValidationContractsTest::testVenueFormsKeepRequiredFieldsAndUrlFilters
admin/models/forms/venue.xml field url should keep filter="url".
```

This means the failing line in the test tells you which contract failed, while the assertion message tells you which JEM source file needs review.

## Adding New Tests

Choose the lightest layer that proves the behavior.

Use a static test when the rule can be checked from source files. Examples:

- A form field must have `required="true"`.
- A controller must call `Session::checkToken()`.
- A build file must exclude `tests/**`.
- A template must escape a user-controlled field.

Use a unit test when the behavior is pure PHP and does not need Joomla. Examples:

- Normalize broken CSV encoding.
- Resolve an attachment display class.
- Clamp invalid boundary values.

Use a Joomla integration test when the behavior depends on Joomla services. Examples:

- Load JEM configuration through `JemHelper::config()`.
- Instantiate a Joomla model or table.
- Check installed database schema.
- Later phases: create/update/delete records with cleanup.

## Rules for Joomla Integration Tests

Current Joomla tests must remain read-only unless a suite is explicitly marked as writable.

Do:

- Read `JEM_TEST_JOOMLA_ROOT` from the environment.
- Skip clearly if local Joomla is not configured.
- Use Joomla's own `configuration.php` for database settings.
- Keep tests grouped by topic: `Bootstrap`, `Schema`, `Config`, `Models`.
- Use clear assertion messages with source paths or table names.

Do not:

- Hard-code local paths.
- Hard-code database credentials.
- Hard-code user IDs.
- Modify real events, venues, categories, users, or sample data.
- Add browser or visual tests to the default `composer test` command.

## Future Writable Tests

CRUD, import, upload, ACL, CSRF POST, browser, and visual tests will need stronger isolation.

Before adding writable tests, define a strategy such as:

- A dedicated test database.
- Records prefixed with `JEM_TEST_`.
- Automatic cleanup in `tearDown()`.
- Defensive cleanup before each test.
- No dependency on personal sample data.

Writable tests should live in dedicated suites so developers can choose when to run them.

## Package Build Checks

The package tests verify that the build rules and ZIP artifacts do not include development files.

The component ZIP must not include:

- `tests/**`
- `vendor/**` from Composer
- Composer files
- PHPUnit files
- `.phpunit.cache/**`
- AI/agent folders such as `.claude/**`, `.codex/**`, `.agents/**`, `.cursor/**`
- local `.env*` files
- keys, certificates, backups, logs, or loose SQL dumps

Note that `media/vendor/**` is allowed because it contains frontend assets such as FontAwesome.

## Joomla 5 Now, Joomla 6 Later

These tests are currently designed for the Joomla 5 version of JEM. Keep tests focused on JEM-owned behavior where possible. Joomla internals may change during a future JEM 5.0.0 migration for Joomla 6, so integration tests should stay isolated and easy to review.

Static and pure unit tests should remain mostly portable. Joomla bootstrap, database, browser, and visual suites are expected to need review during the Joomla 6 migration.

## Recommended Developer Workflow

Before committing a normal code change:

```bash
composer test
```

Before committing changes that touch forms, views, SQL, packaging, or security-sensitive code:

```bash
composer test:static
composer test:static:security
composer test:static:package
```

Before committing changes that touch Joomla models, tables, schema, or configuration:

```bash
composer test
composer test:joomla
```

Small tests, run often, save time. They are a safety net for the project and a shared language for reviewers.

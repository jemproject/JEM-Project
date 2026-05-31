# JEM Tests

This test suite starts with repository-level checks that do not require a running Joomla site or a database. Tests are grouped by topic so a developer can run the whole suite or focus on the area touched by a change.

## Setup

Requirements for the current test suite:

- PHP compatible with the project.
- Composer.
- The PHP extensions normally needed by Composer/PHPUnit, including `dom`, `libxml`, and `simplexml`.

```bash
composer install
```

The current `static` and `unit` suites do not need a web server, a Joomla installation, or a database. A developer can run them from any local checkout, regardless of whether they use XAMPP, MAMP, DDEV, Docker, Apache, Nginx, or PHP's built-in server.

See [TESTING_GUIDE.md](TESTING_GUIDE.md) for a developer-friendly introduction to the test suite and [COVERAGE.md](COVERAGE.md) for the current coverage map, the boundary between static/unit/Joomla integration tests, and debugging tips.

## Local Environment

No local path needs to be changed before running the fast test suite:

```bash
composer test
composer test:static
composer test:unit
```

The fast suites are portable. They do not need a web server, a Joomla installation, or a database.

### Joomla Integration Tests

The `test:joomla*` suites need a local Joomla 6 installation with JEM installed. Configure the Joomla root with the `JEM_TEST_JOOMLA_ROOT` environment variable. This value must point to the folder that contains Joomla's `configuration.php`.

Current Joomla integration tests are read-only. They boot Joomla, inspect schema/configuration, instantiate models/tables, and validate table-level normalization without storing records.

PowerShell example:

```powershell
$env:JEM_TEST_JOOMLA_ROOT = 'C:\xampp\htdocs\jl542'
composer test:joomla:environment
composer test:joomla
```

Windows `cmd.exe` example:

```bat
set JEM_TEST_JOOMLA_ROOT=C:\xampp\htdocs\jl542
composer test:joomla
```

macOS/Linux example:

```bash
export JEM_TEST_JOOMLA_ROOT=/Applications/MAMP/htdocs/jl542
composer test:joomla
```

Other common local roots:

```bash
# XAMPP on Windows
JEM_TEST_JOOMLA_ROOT=C:\xampp\htdocs\jl542

# MAMP on macOS
JEM_TEST_JOOMLA_ROOT=/Applications/MAMP/htdocs/jl542

# DDEV, Docker, Apache, or Nginx checkout
JEM_TEST_JOOMLA_ROOT=/path/to/joomla/root
```

The Joomla database connection is read from Joomla's own `configuration.php`. Do not duplicate database credentials in committed PHPUnit files.

If `JEM_TEST_JOOMLA_ROOT` is not set, Joomla integration tests are skipped with a clear message. The default `composer test` command intentionally does not run Joomla integration tests.

### Future Writable Integration Tests

CRUD, import, upload, browser, and visual tests will need extra isolation because they may create, update, or delete data. When those suites are introduced, configure environment-specific values outside committed files, for example through shell variables or a local ignored environment file:

```bash
JEM_TEST_JOOMLA_ROOT=/path/to/local/joomla
JEM_TEST_BASE_URL=http://localhost/path-to-joomla
JEM_TEST_DB_HOST=127.0.0.1
JEM_TEST_DB_NAME=jem_test
JEM_TEST_DB_USER=root
JEM_TEST_DB_PASSWORD=
```

Writable tests should use a dedicated test database or records clearly marked with a `JEM_TEST_` prefix and guaranteed cleanup.

Do not hard-code personal local paths, URLs, database names, credentials, or user IDs in PHPUnit tests. Tests that need Joomla, a browser, or a database should stay in dedicated suites so developers can still run the fast repository checks without local services.

## Run

```bash
composer test
```

Useful focused commands:

```bash
composer test:static
composer test:unit
composer test:static:syntax
composer test:static:xml
composer test:static:xml:admin
composer test:static:xml:site
composer test:static:xml:shared
composer test:static:language
composer test:static:language:admin
composer test:static:language:site
composer test:static:code
composer test:static:code:admin
composer test:static:code:site
composer test:static:code:shared
composer test:static:views
composer test:static:views:admin
composer test:static:views:site
composer test:static:views:shared
composer test:static:assets
composer test:static:security
composer test:static:integrity
composer test:static:sql
composer test:static:sampledata
composer test:static:package
composer test:joomla
composer test:joomla:bootstrap
composer test:joomla:schema
composer test:joomla:config
composer test:joomla:models
composer test:joomla:environment
composer test:unit:helpers
```

## Build Package

The official ZIP build uses the Ant file in the repository root.

Local requirements:

- Java available on `PATH`.
- Apache Ant available on `PATH`.
- A local `build.config` file created from `build.config.example`.

Example:

```bash
cp build.config.example build.config
ant build
```

PowerShell example:

```powershell
Copy-Item -Path build.config.example -Destination build.config
ant build
```

`build.config` is intentionally ignored by Git because it can contain local paths and FTP values. The component ZIP excludes test and development files such as `tests/**`, `vendor/**`, Composer files, PHPUnit files, `.phpunit.cache/**`, AI/agent tool folders such as `.claude/**`, `.codex/**`, `.agents/**`, and `.cursor/**`, local `.env*` files, keys, certificates, backups, logs, and loose SQL files. The package tests check both the Ant exclude rules and any existing package ZIP artifacts.

## Output Detail

Composer forwards arguments after `--` to PHPUnit. Use this when you need more detail than the default dot progress.

Human-readable test names:

```bash
composer test:static:views -- --testdox
composer test:unit:helpers -- --testdox
```

Detailed PHPUnit debug output:

```bash
composer test:static:views -- --debug
composer test -- --debug
```

Show progress with team-friendly colors:

```bash
composer test:static:views -- --colors=always
```

Show unit progress as a line-based arrow bar, suitable for tee logs:

```bash
composer test:unit:progress
composer test:unit:helpers:progress 2>&1 | tee test-unit-helpers.log
```

Run only tests matching a class, method, or data set name:

```bash
composer test:static -- --filter ViewPartialContractsTest
composer test:static -- --filter testLiteralLoadTemplateCallsHaveMatchingPartial
composer test:static:views -- --filter "admin event edit"
```

Stop on the first problem while developing:

```bash
composer test -- --stop-on-failure
composer test -- --stop-on-error
composer test -- --fail-on-warning
```

List available PHPUnit tests/suites when exploring the project:

```bash
vendor/bin/phpunit --list-suites
vendor/bin/phpunit --list-tests
```

## Current Blocks

- `static:syntax`: PHP syntax checks across the repository.
- `static:xml`: form/view XML validity and option contracts.
- `static:xml:admin`: backend form/view XML.
- `static:xml:site`: frontend form/view XML.
- `static:xml:shared`: modules, plugins, and cross-area XML option contracts.
- `static:language`: language keys used by form XML and view PHP files.
- `static:language:admin`: backend language checks.
- `static:language:site`: frontend language checks.
- `static:code`: controllers, models, fields, and helpers contracts.
- `static:code:admin`: backend controllers, models, fields, and helpers contracts.
- `static:code:site`: frontend controllers, models, fields, and helpers contracts.
- `static:code:shared`: cross-area helper contracts.
- `static:views`: direct-access guards, view partials, edit tabs, and attachment/link view contracts.
- `static:views:admin`: backend view contracts.
- `static:views:site`: frontend and common site view contracts.
- `static:views:shared`: cross-area contracts that intentionally compare backend, frontend, and shared templates.
- `static:assets`: CSS/asset layout contracts.
- `static:security`: static security guardrails for SQL injection, XSS escaping, authorization contracts, CSRF token presence in POST forms, sensitive controller token checks, dangerous functions, direct superglobals, obfuscation patterns, reviewed external domains, hidden iframe/script injection, network-call review, artificial latency, writable backdoor patterns, file permission and upload path policies, debug-output leakage, committed secrets, and password-field privacy.
- `static:integrity`: repository integrity checks such as media references and language file duplicates.
- `static:sql`: SQL defaults and migration contracts.
- `static:sampledata`: sample data SQL and current-user ownership wiring.
- `static:package`: package build exclusions.
- `unit:helpers`: pure helper behavior and boundary/payload normalization checks without Joomla.

## Joomla Version Compatibility

This suite targets JEM 5.x for Joomla 6 only. Tests should prefer JEM-owned contracts over Joomla internals so future Joomla 6 minors remain easy to review.

Static and pure unit tests should remain mostly portable. Joomla bootstrap, database, functional, and visual tests should stay isolated in their own suites.

Future phases can add separate suites for Joomla bootstrap integration, database integration, functional backend/site flows, and visual/responsive UX checks.

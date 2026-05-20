# JEM Test Coverage Map

This document maps the requested JEM test areas to the current test layers.

## Current Layers

- Static tests: fast repository checks that do not boot Joomla, do not use a database, and can run in any checkout.
- Unit tests: pure PHP helper tests that avoid Joomla runtime dependencies.
- Joomla integration tests: tests that boot or inspect a local Joomla installation. These are introduced separately because they need local configuration, database state, users, sessions, and ACL.

## Already Covered Without Joomla

- SQL injection guardrails: raw request values are not concatenated into SQL, selected IDs are cast before SQL fragments, and malicious boundary IDs are normalized.
- XSS guardrails: common site templates escape text fields, event type output is escaped, and known XSS payloads are escaped or stripped as expected.
- CSRF guardrails: POST forms render Joomla tokens and state-changing controllers call token checks.
- Authorization contracts: admin and site controllers/models keep permission checks in the expected places.
- Attachment security: allowed extensions exclude executable/browser-active types, upload paths stay inside configured folders, MIME checks remain in place, and unsafe names are sanitized.
- Form XML contracts: event/venue required fields, safe HTML filters, URL filters, numeric bounds, attachment options, and link options.
- Package integrity: development tools, tests, vendor, secrets, local env files, backups, logs, and loose SQL files are excluded from the component ZIP.
- Malware/backdoor guardrails: dangerous PHP functions, obfuscation primitives, hidden iframes, dynamic external scripts, artificial latency, direct superglobals, hardcoded secrets, and debug output are reviewed.
- Sample data ownership: sample data uses the installing/current user rather than fixed author IDs.

## Needs Joomla Integration

These tests need a Joomla 5 runtime and should be implemented under `tests/Joomla`:

- Model CRUD: create, update, delete, publish, unpublish, feature, load invalid IDs, required field failures, date validation, duplicate alias/name validation, and relation loading for events, venues, categories, attachments, and links.
- Controller flows: add, edit, save, delete, publish, CSV import, redirects, error messages, and 403 behavior.
- Joomla ACL: users with and without permissions, creator-only edits, canEdit/canDelete/canPublish behavior, and access level visibility.
- Joomla CSRF/session: POST without token rejected by controllers, valid token accepted, and session integration.
- JForm validation: runtime validation using Joomla form classes and component field types.
- Database integration: migrations, schema defaults, sample data installation, fixture cleanup, and relational integrity.
- Read-only Joomla schema/config integration: JEM tables, critical columns, installed component status, database connection, and key configuration values.
- Read-only Joomla model/table integration: legacy JEM tables instantiate against the Joomla database, admin models return expected table classes, invalid loads do not resolve records, and table-level normalization can be checked without storing data.
- Frontend/backend rendering: view display, 404 cases, restricted content visibility, search, calendar filters, and browser-level UX.

## PHPUnit Diagnostics

When a test fails, PHPUnit prints the test class, method, dataset name when present, and the file/line where the assertion failed. Use these commands while debugging:

```bash
composer test -- --testdox
composer test -- --debug
composer test -- --stop-on-failure
composer test -- --filter FormValidationContractsTest
```

The `--testdox` mode is best for seeing the readable test name. The `--debug` mode is noisier but shows execution progress. Assertion messages in this suite include relative project paths whenever the failing contract relates to a source file.

## Joomla Integration Entry Point

Set the local Joomla root before running Joomla tests:

```bash
# Windows / XAMPP example
set JEM_TEST_JOOMLA_ROOT=C:\xampp\htdocs\jl542
composer test:joomla:environment
composer test:joomla:bootstrap
```

PowerShell example:

```powershell
$env:JEM_TEST_JOOMLA_ROOT = 'C:\xampp\htdocs\jl542'
composer test:joomla:environment
```

The default `composer test` command intentionally excludes Joomla integration tests so the fast checks remain portable for every developer.

The first Joomla integration layer boots the Joomla site application without executing the web request, verifies the Joomla major version, opens the configured database connection, and confirms that `com_jem` is installed and enabled.

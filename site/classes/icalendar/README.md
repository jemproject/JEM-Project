# JEM Calendar

JEM-owned PHP primitives for the supported iCalendar recurrence subset. They do not load Joomla, Composer packages, translations or network resources. The current JEM ICS exporter still uses its existing iCalcreator serializer; this library replaces only the newly proposed third-party recurrence dependency.

## Specification and scope

- [RFC 5545 section 3.3.10](https://www.rfc-editor.org/rfc/rfc5545.html#section-3.3.10): RRULE syntax, default values, BY selectors and evaluation order.
- [Section 3.8.5.3](https://www.rfc-editor.org/rfc/rfc5545.html#section-3.8.5.3): recurrence-set semantics and examples.
- [Section 3.3.5](https://www.rfc-editor.org/rfc/rfc5545.html#section-3.3.5): DATE-TIME, UTC, floating time and ambiguous local times.
- [Section 3.6.1](https://www.rfc-editor.org/rfc/rfc5545.html#section-3.6.1): concrete VEVENT and exclusive all-day end dates.

This is not a complete RFC 5545 implementation or a general-purpose ICS parser. It supports DAILY, WEEKLY, MONTHLY and YEARLY rules; INTERVAL; WKST; BYMONTH, BYWEEKNO, BYYEARDAY, BYMONTHDAY, BYDAY (including legal ordinals), BYHOUR, BYMINUTE, BYSECOND (0-59) and BYSETPOS. Invalid combinations are rejected. Leap seconds, sub-daily frequencies, EXRULE, RECURRENCE-ID RANGE and arbitrary VTIMEZONE definitions are outside the current import scope.

## API

`Jem\Calendar\RecurrenceRule::dates(array $parts, DateTimeImmutable $start, DateTimeImmutable $end, callable $validLocal, int &$visited): Generator`

- Pass uppercase rule parts, without COUNT, UNTIL or DTSTART. Values are RRULE strings.
- Represent the source wall-clock start and inclusive expansion boundary in UTC objects; this avoids PHP changing calendar arithmetic at DST boundaries.
- The callback rejects nonexistent source-zone local times before BYSETPOS is applied.
- The shared counter bounds examined days/time combinations across the caller's calendar to 50,000. The caller also imposes its date horizon and output limits.
- Candidates are unique, chronological wall-clock dates. The caller converts them to the actual source zone and applies COUNT to valid occurrences, not nonexistent candidates. EXDATE exclusions are applied after COUNT and do not extend the rule.
- Invalid syntax throws `InvalidArgumentException`; excessive expansion throws `OverflowException`. Applications translate these exceptions at their boundary; exception messages are not a UI contract.

`Jem\Calendar\DateTimeValue::local(string $stamp, DateTimeZone $zone, ?string $utcHint = null): ?DateTimeImmutable`

- Input is a local `YmdTHis` value. Invalid dates and DST gaps return null.
- Without a matching explicit instant, a repeated time resolves to its first occurrence.
- An imported UTC hint is accepted only when valid and when it converts to the exact requested wall-clock value in the effective timezone. A changed date/time makes the old hint inapplicable.

## JEM adapters and round trips

`admin/helpers/importics.php` owns parsed VEVENT grouping, UID revisions, COUNT/UNTIL, RRULE/RDATE union, EXDATE removal, detached overrides, duration, windows and output budgets. It maps library exceptions to Joomla INI keys. `admin/controllers/import.php` owns preview/commit and stores imported UTC boundaries with the existing event metadata so the repeated DST hour remains distinguishable.

JEM exports the selected concrete events as separate VEVENTs, with JEM UIDs and without reconstructing RRULE/RDATE/EXDATE. Import/export/import must preserve the selected supported occurrence schedule and explicit instants; it does not preserve file bytes, original UIDs, property order, recurrence grouping, unselected dates or unsupported source properties. It is not calendar synchronisation. A configured import window limits the set that can subsequently be exported.

## Verification and maintenance

Tests include RFC examples, leap years, missing month days, signed selectors, WKST, DST gaps/folds, positional ordering, budgets, INI translation, real import/reimport and concrete ICS round trips. The standalone tests run without Joomla. The RFC supplies stable semantics, but implementation defects, PHP compatibility, timezone database changes and interoperability still require maintenance.

Copyright JEM contributors; GNU GPL v3 or later, consistent with the project. This code was written for JEM from the RFC specification; it does not contain the removed php-rrule implementation.

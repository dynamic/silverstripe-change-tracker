# Characterization tests

These tests pin what the module does today, including behaviour that is known to be wrong (the tracker cache that
never hits, string ids that bypass the "already linked" check, and so on). A change that alters an observable result is
expected to flip the matching assertion in the same commit and to be listed in `CHANGES.md`.

## Support

- `CharacterizationTestCase` replaces `$_SERVER` with fixed values for every test and restores it afterwards. It
  captures warnings with an error handler instead of letting the runner convert them, and fails a test that leaves a
  warning unclaimed. Tracked records are created inside the tests after an administrator has been logged in, never from
  a fixture file.
- `../Fixtures` holds the test-only data objects. The `Legacy*` fixtures are the code the sites carried before the
  module took it over, copied without changes: `LegacySiteDataChangeRecordExtension` (page URL column, affected pages,
  live `LastEdited` update), `LegacyLastModifiedPage` (last-modified meta tags) and `LegacyAuditPageController`
  (review listings). The tests run against the module's own classes and use these copies only as the reference that
  the module's output is compared with.
- `GoldenFile` compares a value with a file in `golden/`. Class names from the test namespaces are shortened, ids are
  replaced by labels, and payloads are reduced to fields declared by the fixtures, so the files do not depend on
  timestamps, auto increment values or core columns.

## Golden files

The files in `golden/` were recorded from the code before any behaviour change. To record them again, set
`CHANGETRACKER_UPDATE_GOLDEN=1` for a run of `GoldenScenarioTest`. The switch is refused when `CI` or `GITHUB_ACTIONS`
is set, so a pipeline can never rewrite the expected output.

## Running

The suite needs a SilverStripe 6 project around the module and a database user that can create databases:

    vendor/bin/phpunit

`ElementalAffectedPagesTest` is skipped unless `dnadesign/silverstripe-elemental` is installed.

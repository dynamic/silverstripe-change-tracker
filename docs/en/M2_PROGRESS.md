# M2 progress (module line 1)

Checkpoint note for resuming work. Remove this file before 1.0.0.

## Steps completed (all merged into 1 with --no-ff)

- C3 absorb (feature/absorb-site-code): AffectedPagesService (bug-for-bug, element_class via _config/elemental.yml),
  LiveLastEditedPropagator (parameterised SQLUpdate, also used by TrackedManyManyList), DataChangeRecordExtension
  (auto-applied, WeakMap memo cleared on write), LastModifiedExtension (methods only), AuditListingExtension
  (allowed_actions ADMIN on the extension, document_extensions). Injector ManyManyList swap auto-applied.
  Fixtures switched to the module extension; goldens byte-identical. Parity tests compare against verbatim copies of
  the site code (tests/Fixtures/Legacy*).
- C4 rename (feature/rename): dynamic/silverstripe-change-tracker, Dynamic\ChangeTracker, TrackedManyManyList in
  src/ORM, composer require/require-dev/suggest/conflict, LICENSE line, .upgrade.yml removed. Test diff namespace-only.
- C4 style (feature/coding-standard): phpcs.xml.dist (PSR-12 minus LineLength; camel caps method names silenced for
  framework/template methods; side-effects rule excluded for PruneChangesBeforeJob; Legacy fixtures excluded), phpcbf
  run (whitespace only).
- C5 data compatibility (feature/data-compat): admin code pinned to CMS_ACCESS_DataChangeAdmin,
  SiteTreeChangeRecordable.published_state_permission (CMS_ACCESS_LeftAndMain), _config/legacy.yml with both remap
  keys, DataChangeRecord::requireDefaultRecords() migration (ClassName, Permission, PermissionRoleCode,
  QueuedJobDescriptor), LegacyMigrationTest.
- CHANGELOG started (feature/changelog).
- Fixes, each its own commit with CHANGELOG and tests/Characterization/CHANGES.md entries:
  truncation to column size (feature/truncate-to-field-size); is_numeric ids and tracked check before byID
  (feature/many-many-ids); addMany(?? []) and Referer fallback (feature/page-url-fixes); cleanup task characterised,
  import fixed, orphan join rows pruned in task and job (feature/cleanup-task); DataChangeConvertJsonTask and
  SignificantChangeRecordable removed (feature/drop-unused).

## Current state

- Branch 1 head: merge of feature/drop-unused. This note is on feature/m2-progress (not merged).
- Last green run, after the last merge into 1 (harness run.sh and gha-mode.sh):
  - mode A, fw 5.4.30, no elemental: 189 tests, 563 assertions, 4 skipped
  - mode B default, fw 5.4.30: 189 tests, 576 assertions, 1 skipped
  - mode B fw53, fw 5.3.23: 189 tests, 576 assertions, 1 skipped
  - mode B elemental, fw 5.4.30: 189 tests, 579 assertions
  - mode B fw53-elemental, fw 5.3.23: 189 tests, 579 assertions
- Golden files: byte-identical to the C1 recording (git diff 19454f5 -- tests/Characterization/golden is empty).
- Name gate: Symbiote\DataChange / Symbiote-DataChange only in _config/legacy.yml, src/Model/DataChangeRecord.php
  (LEGACY_* constants) and tests/LegacyMigrationTest.php.

## Half-done

- Nothing is uncommitted. Not started: docs/en/DEFERRED.md, README.md rewrite, docs/en/ADOPTING.md, CONTRIBUTING.md
  and docs/en/quick-start.md refresh for the new package.
- CHANGELOG intentional list still lacks the null-user warning entry from the upstream 5.1.0 merge.

## Next actions

1. docs/en/DEFERRED.md: cache-key merge (DataChangeRecordTrackTest::testServiceCacheKeyMismatchPinned), setByIDList
   removals (TrackedManyManyListTest::testSetByIdListAddsRecordedRemovalsNot, testRemoveAllNotRecorded), schema
   strategy (AffectedPagesTest::testExactClassOnly, testKeyCollisionSkipsPageLegacy, testHasOneToBaseSiteTreeIgnored),
   sticky New (testStickyNewAfterCreateSameProcess, testStickyNewIsSharedAcrossRecordsOfOtherClasses,
   testNewTypeWithNoChangesStillWrites), inverted array_replace (testSameRecordObjectMergeOrderIsInverted), Password
   in Publish payloads (testPublishStoresToMapInAfterNullBefore), nested-JSON nl2br TypeError
   (DataChangeRecordCMSFieldsTest::testNestedJsonCurrentBehaviour), belongs_many_many owner
   (TrackedManyManyListTest::testBelongsManyManyCurrentBehaviour).
2. README.md, CHANGELOG null-user entry, docs/en/ADOPTING.md (MWTR, AE, AP, LFL on SS5 and SS6, per plan "Docs").
3. Run the full harness matrix: FLUSH=1 ./run.sh, then for VARIANT in "" fw53 elemental fw53-elemental:
   ./gha-mode.sh sync and ./gha-mode.sh test.
4. Branch 2 can be cut from 1 (C5 is merged); deferred until Packagist.

## Harness changes made outside the repository

- gha-mode.sh: new fw53-elemental variant; non-elemental variants drop elemental from the copy's require-dev.
- Harness root composer.json and phpunit.xml now require dynamic/silverstripe-change-tracker 1.x-dev from the path
  repository.

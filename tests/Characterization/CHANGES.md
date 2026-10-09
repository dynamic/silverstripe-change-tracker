# Changes to the recorded behaviour

Each entry lists an assertion or golden file that was changed on purpose, with the reason. Anything not listed here is
unchanged from the first recording.

## Merge of upstream 5.1.0

- `DataChangeRecordTrackTest::testNoMemberWarnsAndStoresZero` is now `testNoMemberStoresZeroWithoutWarning`.
  Saving a tracked record while nobody is logged in used to raise two "Attempt to read property ID on null" warnings
  from `DataChangeRecord::track()`. The current user is now read once and only used when it exists, so no warning is
  raised. The stored values are the same as before: `ChangedByID` is 0 and `CurrentEmail` is null, and the test still
  asserts both.
- No golden files changed. The string casts added to `getJSONData()`, `TrackedManyManyList::recordManyManyChange()`
  and the pruning tasks do not alter any recorded output.

## Site code absorbed into the module

The tests now run against `DataChangeRecordExtension`, which the module applies to `DataChangeRecord` itself, instead of
the site extension applied by the tests. No golden file changed, which shows the module reproduces the site extension.
The verbatim site extension stays in `Fixtures` as the reference for `AffectedPagesParityTest` and
`LiveLastEditedPropagatorTest`.

- `TrackedManyManyListTest::testSwapAppliesToAllManyMany`: the module now installs the tracked many_many list for every
  many_many relation, as each site did in its own configuration, so a relation list is a `TrackedManyManyList` without
  any test setup. Nothing is recorded until a join table is listed in `trackedRelationships`.
- `TrackedManyManyListTest::testUntrackedJoinNoRecordButByIdQueryStillRuns`: the assertion is unchanged; the stock
  list it measures against is now selected explicitly, because the default is the tracked list.
- `AffectedPagesLookupCountTest::testComputedTwicePerPublishOfARecordThatIsNotAPage` is now
  `testComputedOncePerPublishOfARecordThatIsNotAPage`. The live `LastEdited` update and the join rows written by
  `track()` used to run the lookup separately, one after the other on the same record; the result is now kept on the
  record object until it is written again. The pages found and the rows written are the same. The counter moved from
  a subclass of the site extension to a subclass of `AffectedPagesService`.

## Rename to Dynamic\ChangeTracker

Test files changed by namespace only. The strings derived from class names changed with them: the admin's record
path (`Dynamic-ChangeTracker-Model-DataChangeRecord`), the join table of the upstream underscore fixtures and, until the
next change, the permission code derived from the admin class. No golden file changed.

## Fixed permission code for the Data Changes admin

- `DataChangeAdminTest::testRequiredPermissionCode`: the admin requires `CMS_ACCESS_DataChangeAdmin`, set in
  configuration, instead of a code derived from its class name. Groups and roles holding the code of the old package
  are migrated by the build (`LegacyMigrationTest`).
- `DataChangeAdminTest::testNoPermission403`: the last step used to show that `CMS_ACCESS_DataChangeAdmin` did not
  open the admin. It now shows that a code derived from the class name does not.
- `DataChangeAdminTest::testPermissionHolders`: `CMS_ACCESS_DataChangeAdmin` now opens the admin.

## Permission for the Published States tab

- `SiteTreeChangeRecordableTest::testTabVisibleForHolderOfTheLiteralCode` is now
  `testTabHiddenForHolderOfTheAdminCodeOnly`. The tab used to check the literal code `CMS_ACCESS_DataChangeAdmin`,
  which the admin did not require, so only administrators and holders of `CMS_ACCESS_LeftAndMain` saw it in practice.
  Now that the admin requires that code, the tab checks `published_state_permission`, `CMS_ACCESS_LeftAndMain` by
  default, so the people who see the tab stay the same. Someone holding only the admin's code sees the admin but not
  the tab, as before. `testTabPermissionIsConfigurable` covers the setting.

## Values cut to the column size before writing

- `DataChangeRecordTrackTest::testLongValuesTruncated` and `testLongMultibyteValuesMatchDatabaseTruncation` are
  unchanged and still pass: `track()` now cuts ChangeType, ObjectTitle, CurrentURL, Referer, Agent and RemoteIP to
  their column size by characters, which keeps exactly what the database kept when it cut them.
- New: `testTrackCutsValuesBeforeWriting` shows the record object holds the cut values, and
  `testObjectTitleNonStringCast` now also checks the in-memory title is the string `'42'`.

## Ids given to TrackedManyManyList::add()

`add()` used `is_int()` to tell an id from a record and read `->ID` on anything else, so a numeric string id raised a
warning, missed the "already linked" check, and an int id missed the extra data comparison.

- `TrackedManyManyListTest::testAddNumericStringId`: a numeric string id of an item that is already linked is no longer
  recorded a second time, and no warning is raised.
- `TrackedManyManyListTest::testAddExistingByIdAlwaysRecords` is now `testAddExistingByIdComparesTheStoredExtraData`:
  an id with unchanged extra data records nothing, as a record object already did; changed extra data is recorded.
- `TrackedManyManyListTest::testSetByIdListWithStringIdsWarns` is now `testSetByIdListWithStringIds`: the same rows are
  recorded, without the warnings.
- No golden file changed.

## No lookup for joins that are not tracked

- `TrackedManyManyListTest::testUntrackedJoinNoRecordButByIdQueryStillRuns` is now
  `testUntrackedJoinNoRecordAndNoByIdQuery`. `add()` checks whether the join table is tracked before it looks the item
  up, so an add to any other many_many relation runs the same queries as the stock list. Nothing recorded changes.

## No pages from the lookup

- New: `AffectedPagesTest::testNoAnswerFromTheLookupWritesNoJoinRows`. When the lookup returns null, `track()` passes
  an empty list to `addMany()`. It used to pass null, which raised two "foreach() argument must be of type
  array|object" warnings. No rows are written either way.

## Referer fallback of the page URL column

- `PageUrlColumnTest::testRefererFallback`: for a record without join rows and without a current URL, the column used
  to read `$record->Referrer`, which is not a field, so the referer was never used. It now reads `Referer`, and a CMS
  edit form URL there names the page. `track()` always sets the current URL, so only records written some other way
  are affected. A further step shows a record without either URL still reads "No pages affected".

## History cleanup task runs again

- `CleanupDataChangeHistoryTaskTest::testImportOfTheRecordClassIsBroken` is now `testPrunesBelowTheNewestOldRecord`.
  The task imported a class that does not exist and failed with "Class not found" as soon as it looked for records.
  It now imports `DataChangeRecord` and works as written: it finds the records older than the date and deletes every
  record with a lower id than the newest of them, as the pruning job does. `testDryRunWithoutRun` and
  `testRecentDateNeedsForce` pin the dry run and the three month guard.

## Pruning removes the join rows of pruned records

- `PruneChangesBeforeJobTest::testPruneLeavesTheJoinRowsOfDeletedRecords` is now
  `testPruneRemovesTheJoinRowsOfDeletedRecords`: AffectedPages rows whose change record no longer exists are deleted
  after the records. They were never shown anywhere.
- `CleanupDataChangeHistoryTaskTest::testPrunesBelowTheNewestOldRecord` checks the same for the task, which also
  prints how many join rows it removed.

## Line 2 (Silverstripe 6) test differences

Silverstripe 6 removed or changed the APIs these tests used. Each change below asserts the same behaviour through the
Silverstripe 6 API, with no weaker assertion. The golden files are unchanged from line 1.

- `LegacyMigrationTest` and `Fixtures\RemapExposingDatabaseAdmin`: the fixture extends `SilverStripe\Dev\Command\DbBuild`
  instead of `SilverStripe\ORM\DatabaseAdmin`, which Silverstripe 6 does not have. The build runs the ClassName remapping
  in `DbBuild`, and the fixture reads the `DbBuild` remapping configuration. `testBuildRemapRewritesLegacyRows` asserts
  the same rows.
- `DataChangeRecordCMSFieldsTest::testUserFieldShowsNameAndEmail`, `testChangedValuesAreHtmlTextWithInsDel` and
  `testMissingForTemplateReplaced`: `Value()` is `getValue()`. Silverstripe 6 form fields have no `Value()`, and the
  module reads field values with `getValue()` in `DataChangeRecord::getCMSFields()`. The asserted values are the same.
- `Fixtures\ObjectValueReadonlyField` overrides `getValue()` instead of `Value()`, for the same reason. It still returns
  the object only when the Referer field has no value, so the module still replaces it with
  `[Missing stdClass::forTemplate]`, and `testMissingForTemplateReplaced` asserts that value.
- `CharacterizationTestCase::makePlain()` takes a `$skipValidation` argument. `testLongValuesTruncated` and
  `testLongMultibyteValuesMatchDatabaseTruncation` pass it for the long titles, and `testTrackCutsValuesBeforeWriting`
  does too. `testObjectTitleNonStringCast` writes its integer title with `skipValidation`. Silverstripe 6 validates the
  title on write (length and type), so the fixture write failed before the module ran. The assertions are unchanged:
  they check the values the module stores and cuts.
- `DataChangeRecordCMSFieldsTest::testNestedJsonCurrentBehaviour` and `DataChangeCMSTest::testCMSFieldsWithJSONDataCurrentlyThrows`:
  the TypeError for a nested JSON value is raised by `SilverStripe\ORM\FieldType\DBField::XML()` ("Return value must be of
  type string, array returned") instead of `nl2br()`. The defect is unchanged: the edit form of such a record still throws
  a TypeError, and the tests still assert it.
- `Fixtures\LegacySiteDataChangeRecordExtension` (the verbatim site copy): a has_one relation is wrapped as a one-page list
  by `asList()`, as `AffectedPagesService` does. Silverstripe 5 iterated a has_one object as a list of itself. Silverstripe
  6 models are not iterable, so the copy loop finds no pages, and a site copy loses its has_one affected pages on
  Silverstripe 6. The wrap keeps the copy at the Silverstripe 5 result, so `AffectedPagesParityTest` and
  `LiveLastEditedPropagatorTest` compare the module with the behaviour the sites had.
- `Support\TaskInvoker::run()`: on Silverstripe 6, `BuildTask::run()` writes a `Running task` heading and a timing line
  around `execute()`. The helper calls `execute()` directly through a `PolyOutput` in HTML format and returns only what the
  task wrote, so the exact-output assertions in `CleanupDataChangeHistoryTaskTest` compare the task's own output. Line 1
  keeps `ob_start()` around `run()`. This is the only file in `tests/Support` that differs between the lines.
- `Characterization\HostileValuesTest` (line 2 only, 3 tests): a change record with no member, an integer title, and a
  body that is not valid UTF-8. The file's docblock records that these exist on line 2 only because Silverstripe 6 is
  stricter about these values than Silverstripe 5. This is added coverage: it replaces no line 1 assertion. The tests
  check the stored values, the CMS fields where a test builds them, and that no warnings are emitted.

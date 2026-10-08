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

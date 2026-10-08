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

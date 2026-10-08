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

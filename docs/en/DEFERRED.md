# Deferred defects

Each defect below is kept as it is in the fork, because fixing it would change output that sites can see: stored
change rows, admin pages, meta tags or the many_many join rows. Each entry names the test that pins the current
behaviour. When a defect is fixed, the pinning test changes in the same commit, and the change is listed in the
CHANGELOG under "Intentional behaviour changes".

Test references are `Class::method`, under `tests/Characterization/` unless stated otherwise.

| Defect | Why it is deferred | Pinning test |
|---|---|---|
| Cache key merge: the lookup key of `dcr_cache` ends with the change type, the stored key does not, so the cache never hits | Fixing it changes which record object is reused between writes, and so the merged Before and After values | `DataChangeRecordTrackTest::testServiceCacheKeyMismatchPinned` |
| `setByIDList` removals: removals made through `setByIDList()` are not recorded | Fixing it adds Remove rows that were never stored | `TrackedManyManyListTest::testSetByIdListAddsRecordedRemovalsNot`, `TrackedManyManyListTest::testRemoveAllNotRecorded` |
| Schema-based affected pages: the lookup matches by class and key, so a subclass is not found, a key collision skips the page, and a has_one to SiteTree is ignored | Changing the lookup changes the AffectedPages rows and the Page URL column | `AffectedPagesTest::testExactClassOnly`, `AffectedPagesTest::testKeyCollisionSkipsPageLegacy`, `AffectedPagesTest::testHasOneToBaseSiteTreeIgnored` |
| Sticky `New` change type: `ChangeRecordable::$changeType` is set to `New` on create and not reset, so later changes in the same process can be stored as `New` | Changing it changes the ChangeType of stored rows | `DataChangeRecordTrackTest::testStickyNewAfterCreateSameProcess`, `DataChangeRecordTrackTest::testStickyNewIsSharedAcrossRecordsOfOtherClasses`, `DataChangeRecordTrackTest::testNewTypeWithNoChangesStillWrites` |
| Inverted `array_replace` in the `track()` merge: the second call's Before and the first call's After survive when one record object is tracked twice | Fixing the argument order changes the stored Before and After | `DataChangeRecordTrackTest::testSameRecordObjectMergeOrderIsInverted` |
| Password and blacklisted fields in Publish payloads: a Publish row stores the map in After with a null Before, including fields listed in `field_blacklist` | Excluding them changes the stored JSON of every Publish row | `DataChangeRecordTrackTest::testPublishStoresToMapInAfterNullBefore` |
| SecurityID-only Change rows: a change to the form token alone is recorded as a Change row with empty `[]` payloads | Skipping these rows changes the row count of existing sites | `DataChangeRecordTrackTest::testSecurityIDIgnored` |
| Nested JSON `nl2br` TypeError (fork commit `39a5be5`): a JSON field with more than one level of nesting makes the record's CMS fields throw | Fixing it changes the admin screen output for those records | `DataChangeRecordCMSFieldsTest::testNestedJsonCurrentBehaviour`, `DataChangeCMSTest::testCMSFieldsWithJSONDataCurrentlyThrows` |
| `belongs_many_many` owner: adding from the child's side records the child's id as the owning page | Fixing it changes ChangeRecordID and ObjectTitle of existing Add rows | `TrackedManyManyListTest::testBelongsManyManyCurrentBehaviour` |
| Change records built before save: the Change row is written in `onBeforeWrite()`, so it exists before the save succeeds | Moving the write to `onAfterWrite()` changes the order of rows in the golden files | `GoldenScenarioTest::testPageLifecycle`, `DataChangeRecordTrackTest::testChangeStoresOnlyChangedFieldsJson` |
| `onBeforeVersionedPublish` only fires from `copyVersionToStage` (rollback and revert): `Publish Live to Stage` and `Publish N to Stage` rows exist only for those operations | Firing it for other publish paths adds rows that sites do not have today | `DataChangeRecordTrackTest::testRollbackWritesPublishVersionToStage` |
| Record edit form offers Save: the admin edit view of a change record has an `action_doSave` button | Removing it changes the admin form; the record is read-only otherwise | `DataChangeAdminTest::testEditViewOffersSaveButNoDelete` |
| Literal-code PublishedState vs admin 403: the Published States tab checks `published_state_permission` (`CMS_ACCESS_LeftAndMain`), and the admin requires `CMS_ACCESS_DataChangeAdmin`; a holder of only the code the admin used to derive from its class name gets a 403 | Changing either permission check changes who sees the tab and the admin | `SiteTreeChangeRecordableTest::testTabHiddenForHolderOfTheAdminCodeOnly`, `DataChangeAdminTest::testNoPermission403` |
| Dead CMS-edit-URL branch: when a record is written from a page edit form and no page can be found for it, the edit form's page is still ignored, so the branch only applies when other pages exist | Using the edit form URL for every record changes the Page URL column of records with no pages | `PageUrlColumnTest::testCmsEditUrlIsIgnoredWhenNoPagesCanBeFound`, `PageUrlColumnTest::testFromCmsEditUrl` |
| `extra_dependencies`: records of global classes (no page owns them) have no affected page, so they never show a page in the Page URL column | Config to attach pages is not implemented in 1.0, so the output is unchanged | None in this repository. Behaviour is the `AffectedPagesTest` lookup without an extra dependency |
| `listpages` uses the stage `LastEdited`, not the live or effective value | Changing the value changes the audit listing and the admin output | `AuditListingExtensionTest::testAdminListPages200` |
| Hook-based meta tag: the last-modified meta tags are added by the site's `Page::MetaComponents()` call, the module adds methods only | A hook would change the meta tag order on SS5 and the hook names differ on SS6 | `LastModifiedExtensionTest::testMetaComponentsMatchTheSitesCode`, `LastModifiedExtensionTest::testMetaTagsHtmlExact` |
| `Created` index: the change record table has no index on `Created` | Adding an index changes the schema, which the sites compare between lines | None in this repository |

## Items with no test in this repository

- `extra_dependencies` and the `Created` index are not implemented, so there is no characterization test for them.
  Adding either as a fix needs a new test first.

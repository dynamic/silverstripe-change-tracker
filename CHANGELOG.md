# Changelog

All notable changes to this module are listed here. The module follows [Semantic Versioning](https://semver.org/).

Line `1` supports Silverstripe CMS 5, line `2` Silverstripe CMS 6. Class names are the same on both lines.

## 1.0.0 (unreleased)

First release of `dynamic/silverstripe-change-tracker`. It continues
[symbiote/silverstripe-datachange-tracker](https://github.com/symbiote/silverstripe-datachange-tracker) 5.1.0 together
with the Dynamic fork of it (`dynamic/silverstripe-datachange-tracker`, branch `custom-changeDetection-5`, commit
`7aa2fd7`), and takes over the change tracking code that the sites using the fork carried themselves.

### Added

- `DataChangeRecordExtension`, applied to `DataChangeRecord` by the module: the Page URL, Change Type and Record Type
  columns of the Data Changes admin, the AffectedPages join rows, and the update of the live `LastEdited` value of
  affected pages when a record that is not a page is published. It replaces the `DataChangeRecordDataExtension` each
  site copied.
- `AffectedPagesService`, the affected pages lookup of that extension, unchanged. `element_class` is set to the
  elemental `BaseElement` when elemental is installed.
- `LiveLastEditedPropagator`, the live `LastEdited` update used by the extension and by the tracked many_many list.
- `LastModifiedExtension` with `EffectiveLastEdited()` and `withLastModifiedMetaTags()`, the last-modified meta tags
  of the sites' `Page` class. It adds methods only and no hook.
- `AuditListingExtension` with the `listpages` and `listdocs` review listings of the sites' `PageController`,
  restricted to ADMIN in the extension's own `allowed_actions`, with a `document_extensions` setting.
- `SiteTreeChangeRecordable.published_state_permission`, the permission code for the Published States tab.
- `_config/legacy.yml` and `DataChangeRecord::requireDefaultRecords()`, which carry data of the old package over on
  build: ClassName of change records, the admin permission code in groups and roles, and pending pruning jobs.
- `conflict` with `symbiote/silverstripe-datachange-tracker`, `sheadawson/silverstripe-datachange-tracker` and
  `silverstripe-australia/datachange-tracker`, which define the same tables.

### Changed

- Package `dynamic/silverstripe-change-tracker`, namespace `Dynamic\ChangeTracker`. `TrackedManyManyList` moved to
  `Dynamic\ChangeTracker\ORM`. The table names `DataChangeRecord` and `DataChangeRecord_AffectedPages` are unchanged.
- The module replaces `ManyManyList` with `TrackedManyManyList` through the Injector, as every site did in its own
  configuration. Join tables are still only tracked when listed in its `trackedRelationships` property.
- `DataChangeRecord.field_blacklist` contains `SearchContent` by default, as every site configured.
- The Data Changes admin requires the fixed code `CMS_ACCESS_DataChangeAdmin`.

### Removed

- `DataChangeConvertJsonTask`, which converted records stored by the Silverstripe 3 versions from PHP serialisation
  to JSON. It imported a class that does not exist and failed whenever it was run.
- `SignificantChangeRecordable`, which no site using this module applied. A site that applies it should keep a copy.

### Fixed

- `PruneChangesBeforeJob` declares the `priorTo` and `pruneBefore` properties it sets, which PHP 8.2 reports as dynamic
  properties. They stay public, so queued jobs stored before the change still load.

### Intentional behaviour changes vs fork 7aa2fd7

Each change below is pinned by a test; `tests/Characterization/CHANGES.md` names the assertions that changed.

- **Data Changes admin permission code.** The code is `CMS_ACCESS_DataChangeAdmin` instead of
  `CMS_ACCESS_Symbiote\DataChange\Admin\DataChangeAdmin`. Existing grants in groups and roles are migrated on build.
- **Admin record links.** Links to a single change record contain `Dynamic-ChangeTracker-Model-DataChangeRecord`
  instead of `Symbiote-DataChange-Model-DataChangeRecord`. `admin/datachanges` itself is unchanged.
- **Published States tab.** The tab checks `published_state_permission` (`CMS_ACCESS_LeftAndMain`) instead of the
  literal code `CMS_ACCESS_DataChangeAdmin`. The people who see it stay the same: administrators and holders of
  `CMS_ACCESS_LeftAndMain`. A group given the literal code by hand would no longer see the tab.
- **Affected pages lookup runs once per write.** The live `LastEdited` update and the join rows share one lookup per
  record write. The pages found and the rows written are unchanged.
- **Live `LastEdited` update.** The value is passed as a query parameter instead of being formatted into the SQL.
  The same rows get the same values.
- **Long values are cut before writing.** ChangeType, ObjectTitle, CurrentURL, Referer, Agent and RemoteIP are cut to
  their column size by characters in `track()`, and a non-string ObjectTitle is stored as a string. The stored values
  are the ones the database kept before; the record object now holds them too. Silverstripe 6 rejects over-long
  values instead of cutting them, so this is needed for line `2`.
- **Ids in many_many `add()`.** A numeric string id (as submitted by a CheckboxSetField or TagField) is treated as an
  id: no "Attempt to read property ID" warning, and an item that is already linked is not recorded again. An id passed
  with extra fields for an item that is already linked is now compared with the stored extra data, as a record object
  was, so unchanged extra data no longer adds a row. This affects sites that track relations with extra fields.
- **No extra query for untracked joins.** Since the tracked list replaces every many_many list, `add()` now checks
  that the join table is tracked before it looks the item up. Untracked relations run the same queries as the stock
  list.
- **No warning when nobody is logged in.** Saving a tracked record with no logged-in user raised two "Attempt to read
  property ID on null" warnings from `track()`. The current user is now read once and used only when it exists. The
  stored values are unchanged: `ChangedByID` is 0 and `CurrentEmail` is null. This comes with the upstream 5.1.0 merge.
- **No warning when no pages are found.** `track()` passes an empty list to `addMany()` when the affected pages lookup
  returns null, instead of null.
- **Referer fallback of the Page URL column.** For a change record without AffectedPages rows and without a CurrentURL,
  the column now falls back to the Referer field, as intended. It read a misspelt `Referrer` property before, which is
  always empty. `track()` always sets CurrentURL, so records it writes are not affected.
- **History cleanup task works.** `CleanupDataChangeHistoryTask` imported a class that does not exist and failed when
  run with a valid date. It now prunes as its messages describe. Its URL is
  `dev/tasks/Dynamic-ChangeTracker-Job-CleanupDataChangeHistoryTask`.
- **Pruning removes orphan join rows.** `PruneChangesBeforeJob` and `CleanupDataChangeHistoryTask` delete the
  AffectedPages rows of the change records they deleted (and any other row whose record no longer exists). Which
  change records are pruned is unchanged: every record with a lower id than the newest record older than the date.

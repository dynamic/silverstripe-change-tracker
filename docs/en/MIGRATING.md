# Migrating from symbiote/silverstripe-datachange-tracker

This guide is for a site that uses `symbiote/silverstripe-datachange-tracker`, or the Dynamic fork of it (branch
`custom-changeDetection-5`), and may carry its own copies of the tracking code.

Each site has the same steps. Line `1` is for Silverstripe CMS 5 and comes first. Line `2` is for Silverstripe CMS 6;
its section lists what is different on SS6.

## Version matrix

| Module line | Silverstripe CMS | PHP | PHPUnit |
|---|---|---|---|
| `1` (`^1.0`) | `^5.3` | `^8.1` | 9.6 |
| `2` (`^2.0`) | `^6` | `^8.3` | 11 |

Moving from line `1` to line `2` is the constraint change `^1` to `^2`. The config keys are the same.

The lock file pins the commit, so a site gets a newer commit only through a scoped update of the package.

## Before you start (every site, every line)

1. Start from a clean working tree. Make a database snapshot and a backup of the assets. Record the baseline with the
   queries in "Verification SQL" below.
2. Run the read-only checks:
   - `grep -rnE 'Symbiote.DataChange' app/_config app/src` lists every reference to be changed. References in the
     site's tests and its regression suite are not changed (see Step 4).
   - `SELECT COUNT(*) FROM Permission WHERE Code = 'CMS_ACCESS_DataChangeAdmin';` must be `0`. The build migrates the
     old code to this one, so a site that already holds it would not be migrated cleanly.
3. Have the package reachable. Until the module is listed on Packagist, add a VCS repository for it:

   ```json
   {
       "type": "vcs",
       "url": "https://github.com/dynamic/silverstripe-change-tracker.git"
   }
   ```

   The repository is public, so the HTTPS URL needs no SSH key on the servers or in CI.

## Step 1: composer.json

1. Remove the require line `"symbiote/silverstripe-datachange-tracker"` and the VCS repository of the fork, if the site
   uses one.
2. Add `"dynamic/silverstripe-change-tracker": "^1.0"` (or `"^2.0"` on Silverstripe CMS 6).
3. Run a scoped update, and do not run `composer remove` or an unscoped `composer update`:

   ```
   composer update dynamic/silverstripe-change-tracker --with-dependencies
   ```

4. Check the lock diff. It must show the two change tracker packages and nothing else:

   ```
   git diff composer.lock | grep '^[-+]    "name"'
   ```

   `composer show symbiote/silverstripe-datachange-tracker` must fail.

The module conflicts with `symbiote/silverstripe-datachange-tracker`, `sheadawson/silverstripe-datachange-tracker` and
`silverstripe-australia/datachange-tracker`, so the old package must be out of `composer.json` before the update.

## Step 2: changetracker.yml

The site's change tracker config is replaced. The rules:

- Every `Symbiote\DataChange\Extension\SiteTreeChangeRecordable` becomes
  `Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable`, and every
  `Symbiote\DataChange\Extension\ChangeRecordable` becomes `Dynamic\ChangeTracker\Extension\ChangeRecordable`.
- The `Injector` block for `SilverStripe\ORM\ManyManyList` keeps the same `trackedRelationships` property list. The
  module already installs `TrackedManyManyList` for every many_many relation, so the `class:` line is removed. The
  property is read from the Injector instance as `trackedRelationships`; it is not a config key (see "Notes on the
  property name" below).
- `Symbiote\DataChange\Model\DataChangeRecord` becomes `Dynamic\ChangeTracker\Model\DataChangeRecord`. An extension
  the site applied to it to add the Page URL and affected pages columns is removed, because the module applies
  `DataChangeRecordExtension` itself. A `field_blacklist: [SearchContent]` entry is redundant: the module's default is
  `[Password, SearchContent]`, and a site's list is merged with it.

An example, for a site that tracks pages, two data object classes and one many_many relation:

```yaml
---
Name: app-change-tracker-config
---
SilverStripe\Core\Injector\Injector:
  SilverStripe\ORM\ManyManyList:
    properties:
      trackedRelationships:
        - Page_Regions

SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

App\Model\Article:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

App\Model\Download:
  extensions:
    - Dynamic\ChangeTracker\Extension\ChangeRecordable
```

An admin menu group that lists `Symbiote-DataChange-Admin-DataChangeAdmin` (for example in a CMS theme config) changes
to `Dynamic-ChangeTracker-Admin-DataChangeAdmin`. The menu item is the admin's ID, so it changes with the namespace.
Check the menu after the build.

## Step 3: PHP code

### `use` lines

Replace `use Symbiote\DataChange\Extension\SiteTreeChangeRecordable;` with
`use Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable;` in every model that applies the extension in PHP.

### Delete the copy of the data change record extension

A site that carries its own `DataChangeRecordDataExtension` deletes it. The module's `DataChangeRecordExtension` does
the same work: the Page URL and affected pages columns, and the live `LastEdited` update.

### Move listpages and listdocs to AuditListingExtension

A site whose page controller has `listpages` and `listdocs` actions removes both `allowed_actions` entries and both
methods, then registers the extension on the page controller:

```yaml
PageController:
  extensions:
    - Dynamic\ChangeTracker\Control\AuditListingExtension
```

The URLs (`<any page>/listpages` and `<any page>/listdocs`) and the ADMIN permission do not change.

### Last-modified meta

A site whose `Page` class has its own last-modified code (protected methods such as `LastModifiedMetaContent()` and
`EffectiveLastEdited()`, and a block in `MetaComponents()` that adds `lastModified` and `articleModifiedTime`):

- Keep `public function MetaComponents()` as it is, and put the call to `withLastModifiedMetaTags()` in the same
  position as the block it replaces: `$tags = $this->withLastModifiedMetaTags($tags);`.
- Delete the site's own methods. The module's `LastModifiedExtension` has public methods with the same behaviour, and
  the page class uses them through `Page`'s extension list:

  ```yaml
  Page:
    extensions:
      - Dynamic\ChangeTracker\Extension\LastModifiedExtension
  ```

The module ships methods only, with no hook. The `Page::MetaComponents()` call stays in place, so the tags appear in the
same order.

### Published States tab

A site that removes the `PublishedState` field in its own `getCMSFields()` keeps doing so. Check the Published States
tab after the swap: its visibility is set by `published_state_permission`, which defaults to `CMS_ACCESS_LeftAndMain`.

## Step 4: tests, regression baselines and README

- The site's tests and its regression suite record what the site did before the swap. Their assertions, expected
  outputs and recorded baselines are not edited, also where they name the old class or the old permission code
  (`CMS_ACCESS_Symbiote\DataChange\Admin\DataChangeAdmin`). The swap is not a reason to record them again.
- Each intended difference of the swap is registered as a known difference in the site's regression suite, in a
  reviewed change, with the value before, the value after and the reason. The differences the swap causes:
  - the permission code of the Data Changes admin (`CMS_ACCESS_DataChangeAdmin`);
  - the class names in the admin's menu item ID and in the links to a single change record
    (`Dynamic-ChangeTracker-...` instead of `Symbiote-DataChange-...`);
  - the stored `ClassName` of the change records;
  - the warnings that the fork raised and the module does not (a tracked write with nobody logged in, a many_many add
    by string ID), where the suite records warnings.
- If the site's README names the fork, replace it with the module and this guide.

## Step 5: build and verify

1. Commit the changes. Commit before the build, so the build does not churn generated docblocks in the diff.
2. Run the build twice: `sake dev/build flush=1` (`sake db:build --flush` on Silverstripe CMS 6), then the same command
   again. `DataChangeRecord.ClassName` is an enum column. The first build adds the new class name to it and then
   rewrites the rows; the old class name stays allowed in that build, because rows still held it when the table was
   checked. The second build removes the old name from the column. Compare the schema and run the checks below after
   the second build. The first build must not run with `--no-populate`, which skips the data migration.
3. Run the verification SQL below, and compare with the baseline.
4. Open Data Changes, a single change record, the Published States tab on one page, and `listpages` and `listdocs` as an
   administrator and as an editor. Check the Data Changes menu item, and records for a publish and a many_many change.

## Verification SQL

Run before the swap, after the build, and after any later build. The table names are the module's.

```sql
-- counts per change type (before and after must match, apart from the new rows the site writes)
SELECT ChangeType, COUNT(*) FROM DataChangeRecord GROUP BY ChangeType ORDER BY ChangeType;

-- every row has the new class; the legacy class and blank values are gone
SELECT ClassName, COUNT(*) FROM DataChangeRecord GROUP BY ClassName;

-- the join rows, and the orphans (both must be 0)
SELECT COUNT(*) FROM DataChangeRecord_AffectedPages;
SELECT COUNT(*) FROM DataChangeRecord_AffectedPages ap
    LEFT JOIN DataChangeRecord d ON d.ID = ap.DataChangeRecordID WHERE d.ID IS NULL;
SELECT COUNT(*) FROM DataChangeRecord_AffectedPages ap
    LEFT JOIN SiteTree s ON s.ID = ap.SiteTreeID WHERE s.ID IS NULL;

-- permission codes: only the new code, and no old one
SELECT Code, COUNT(*) FROM Permission WHERE Code LIKE '%DataChange%' GROUP BY Code;
SELECT Code, COUNT(*) FROM PermissionRoleCode WHERE Code LIKE '%DataChange%' GROUP BY Code;

-- pruning jobs (sites with queuedjobs); the old class must not be listed
SELECT Implementation, COUNT(*) FROM QueuedJobDescriptor WHERE Implementation LIKE '%DataChange%' GROUP BY Implementation;

-- live LastEdited values, a checksum before and after the swap
SELECT COUNT(*), SUM(UNIX_TIMESTAMP(LastEdited)) FROM SiteTree_Live;
```

Expected after the second build: the `ClassName` query returns one row,
`Dynamic\ChangeTracker\Model\DataChangeRecord`; the orphan queries return 0; the permission queries return only
`CMS_ACCESS_DataChangeAdmin`; the job query returns only `Dynamic\ChangeTracker\Job\PruneChangesBeforeJob`. The live
checksum must equal the baseline (the build does not change `SiteTree_Live`). The change-record counts must equal the
baseline plus the rows written by the site in between.

Also confirm that `grep -rnE 'Symbiote.DataChange' app/_config app/src` lists nothing (the site's tests keep the old
names, see Step 4), and that `SELECT ... WHERE Code LIKE 'CMS_ACCESS_Symbiote%'` returns 0 rows.

## Rollback

Keep the baseline DB snapshot and the commit before the swap.

1. Revert the commit. Run `composer update symbiote/silverstripe-datachange-tracker --with-dependencies` after
   restoring the old `composer.json`, and check the lock diff.
2. Run one build on the old code before the reverse update. `ClassName` is an enum column: after the second build of
   the new code it allows only `Dynamic\ChangeTracker\Model\DataChangeRecord`, so the reverse update fails with
   `Data truncated for column 'ClassName'`. The old code's build adds the old class name to the column and keeps the
   stored values, because a value that rows still hold stays allowed.
3. Reverse the data migration. Run the permission updates only if `CMS_ACCESS_DataChangeAdmin` was not already granted
   before the swap (the pre-check above):

   ```sql
   UPDATE DataChangeRecord SET ClassName = 'Symbiote\\DataChange\\Model\\DataChangeRecord'
       WHERE ClassName = 'Dynamic\\ChangeTracker\\Model\\DataChangeRecord';
   UPDATE Permission SET Code = 'CMS_ACCESS_Symbiote\\DataChange\\Admin\\DataChangeAdmin'
       WHERE Code = 'CMS_ACCESS_DataChangeAdmin';
   UPDATE PermissionRoleCode SET Code = 'CMS_ACCESS_Symbiote\\DataChange\\Admin\\DataChangeAdmin'
       WHERE Code = 'CMS_ACCESS_DataChangeAdmin';
   UPDATE QueuedJobDescriptor SET Implementation = 'Symbiote\\DataChange\\Job\\PruneChangesBeforeJob'
       WHERE Implementation = 'Dynamic\\ChangeTracker\\Job\\PruneChangesBeforeJob';
   ```

4. Run the build again, which removes the new class name from the column. The `ClassName` query of the verification
   SQL then lists only `Symbiote\DataChange\Model\DataChangeRecord`.
5. If the reverse update does not give the baseline counts, restore the database snapshot instead. Rows written after
   the snapshot are lost in that case, unless they are re-inserted from a dump of the live database first.

Rehearse the rollback on a local copy before the production change.

## Notes on the property name

The module reads the list from the `trackedRelationships` property of `TrackedManyManyList`, which the Injector sets.
It is not a config key. A key named `tracked_relationships` would have no effect, so keep the `trackedRelationships`
property in the Injector block as shown.

## Silverstripe CMS 6 (line 2)

These notes were checked against `silverstripe/framework` 6.2.11 and `silverstripe/cms` 6.2.5, with
`silverstripe/versioned` 3.2.2, `silverstripe/admin` 3.2.5 and PHPUnit 11.5.

1. Change the constraint to line `2`: `^2.0`. The config keys and class names are the same.
2. Composer: the site's PHP is `^8.3`, and the framework and CMS packages move to `^6`. The rest of the stack follows
   the site's own upgrade plan.
3. **Hook renames.** On SS6 `MetaComponents` is called as `updateMetaComponents`. An extension that implements
   `MetaComponents(&$tags)` becomes `updateMetaComponents(&$tags)`; without the rename it silently stops firing. A page
   class that calls the module's `withLastModifiedMetaTags()` directly needs no rename.
4. **Duplicate-field audit.** On SS6, page types scaffold their own fields. A page class that adds the same field again
   inside a `FieldGroup` or `CompositeField` gets a "field appears twice" error on the edit form. For every page class:
   - list the fields added in `getCMSFields()` by `addFieldToTab`, `FieldGroup` and `CompositeField`, and the `$db`
     and `$has_one` names of the class;
   - for each name that is both scaffolded and added again, remove the scaffolded field before the group is built.
5. **PasswordValidator.** `SilverStripe\Security\PasswordValidator` no longer exists on SS6, and configuring it is
   fatal. The base class is `SilverStripe\Security\Validation\PasswordValidator`. Use
   `SilverStripe\Security\Validation\RulesPasswordValidator` with the same rules (length, digits, letters) as the
   site's current validator.
6. **TinyMCE** and the rest of the SS6 config changes follow the site's SS6 upgrade.
7. Run the build twice, as in Step 5 (`vendor/bin/sake db:build --flush` on Silverstripe 6), and run the verification
   SQL again after the second build.
8. **Copies of the site extension.** A copy of `DataChangeRecordDataExtension` that walks has_one relations with
   `foreach` finds no pages on SS6: a Silverstripe 6 model is not iterable, so a has_one relation yields nothing.
   Delete the copy (Step 3). The module's `AffectedPagesService` reads has_one relations as one-page lists. Check the
   Page URL column of a record changed through a has_one relation after the build.
9. **History cleanup.** The task is a Silverstripe 6 build task. Run it from the command line:
   `vendor/bin/sake tasks:Dynamic-ChangeTracker-Job-CleanupDataChangeHistoryTask --older="-6 months"`, and add `--run`
   to delete. Its `run` and `force` options are booleans on line `2`: `run=false` and `run=no` leave it as a dry run.
10. **Queued pruning jobs.** The build changes only the `Implementation` of a pending `PruneChangesBeforeJob` row. The
    job's `priorTo` and `pruneBefore` values stay in the row's stored job data, so the job keeps the date it was queued
    with. Check the row after the build.

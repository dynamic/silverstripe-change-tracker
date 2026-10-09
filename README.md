# Change Tracker for Silverstripe CMS

`dynamic/silverstripe-change-tracker` records changes and deletes of data objects, and adds and removes of items in
many_many relations. It finds the pages each change affects and shows the records in the CMS under Data Changes.

It continues [symbiote/silverstripe-datachange-tracker](https://github.com/symbiote/silverstripe-datachange-tracker)
(BSD-3-Clause) and the Dynamic fork of it. See [CHANGELOG.md](CHANGELOG.md) for what differs from the fork.

## Lines

| Line | Silverstripe CMS | PHP | Status |
|---|---|---|---|
| `1` | 5 (`silverstripe/framework` ^5.3, `silverstripe/cms` ^5.3) | ^8.1 | Current. Version `1.0.0` is not tagged yet; develop on `1.x-dev`. |
| `2` | 6 | 8.3 or later (to be confirmed when line `2` is cut) | Not released. Same class names as line `1`. |

Optional modules:

- `dnadesign/silverstripe-elemental`: the affected pages of content blocks are found through their page
  (`AffectedPagesService::$element_class` is set to `BaseElement` by `_config/elemental.yml`).
- `symbiote/silverstripe-queuedjobs`: the nightly job that prunes old records. Without it the pruning job is not
  defined.

## Install

The package is not on Packagist yet. Add the repository to `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/dynamic/silverstripe-change-tracker.git"
    }
]
```

No release is tagged yet, so require the development version of the line, which follows its branch:

```
composer require dynamic/silverstripe-change-tracker:1.x-dev
```

Use `2.x-dev` for line `2`. Once `1.0.0` or `2.0.0` is tagged, the constraint becomes `^1.0` or `^2.0`.

The module adds its configuration with the `Only` rules it needs; there is nothing to copy into the project.

To track a many_many relation, list its join table in `trackedRelationships` on the tracked list (see below). Nothing
is recorded for a relation that is not listed.

## Upgrading from symbiote/silverstripe-datachange-tracker or the Dynamic fork

1. In `composer.json`, remove the old package and its VCS repository, add the repository above, and require the
   line's development version: `dynamic/silverstripe-change-tracker:1.x-dev` on Silverstripe 5, `2.x-dev` on
   Silverstripe 6 (`^1.0` or `^2.0` once tagged). Then run a scoped update,
   `composer update dynamic/silverstripe-change-tracker --with-dependencies`, and check the lock diff. Do not run an
   unscoped update.
2. Delete the project's copy of `DataChangeRecordDataExtension`. The module applies `DataChangeRecordExtension` to
   `DataChangeRecord` itself.
3. Rename the class names in the project's config (the class map below).
4. Run `dev/build flush=1` twice. The first build does three things (see "Data migration"):
   - rewrites the `ClassName` of existing change records to the new class;
   - moves grants of the old admin permission code in groups and roles to `CMS_ACCESS_DataChangeAdmin`;
   - moves pending pruning jobs to the new job class.

   `ClassName` is an enum column, and the first build still allows the old class name in it. The second build
   removes it. A rollback needs one build on the old code before the class name is written back; see
   [docs/en/ADOPTING.md](docs/en/ADOPTING.md), "Rollback".

### Class map

| Old class | New class |
|---|---|
| `Symbiote\DataChange\Model\DataChangeRecord` | `Dynamic\ChangeTracker\Model\DataChangeRecord` |
| `Symbiote\DataChange\Admin\DataChangeAdmin` | `Dynamic\ChangeTracker\Admin\DataChangeAdmin` |
| `Symbiote\DataChange\Extension\ChangeRecordable` | `Dynamic\ChangeTracker\Extension\ChangeRecordable` |
| `Symbiote\DataChange\Extension\SiteTreeChangeRecordable` | `Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable` |
| `Symbiote\DataChange\Service\DataChangeTrackService` | `Dynamic\ChangeTracker\Service\DataChangeTrackService` |
| `Symbiote\DataChange\Model\TrackedManyManyList` | `Dynamic\ChangeTracker\ORM\TrackedManyManyList` |
| `Symbiote\DataChange\Job\PruneChangesBeforeJob` | `Dynamic\ChangeTracker\Job\PruneChangesBeforeJob` |
| `Symbiote\DataChange\Job\CleanupDataChangeHistoryTask` | `Dynamic\ChangeTracker\Job\CleanupDataChangeHistoryTask` |

Removed: `Symbiote\DataChange\Job\DataChangeConvertJsonTask` (it imported a class that does not exist) and
`Symbiote\DataChange\Extension\SignificantChangeRecordable` (no site used it). A site that applied the last one keeps
its own copy.

The table names `DataChangeRecord` and `DataChangeRecord_AffectedPages` are unchanged, so no table is renamed.

### Data migration

`_config/legacy.yml` maps the old class name for `classname_value_remapping` on both build keys (`DatabaseAdmin` for
Silverstripe 5, `DbBuild` for Silverstripe 6). `DataChangeRecord::requireDefaultRecords()` then:

- sets `ClassName` to `Dynamic\ChangeTracker\Model\DataChangeRecord` for rows that have the old class name or none;
- sets the code `CMS_ACCESS_Symbiote\DataChange\Admin\DataChangeAdmin` to `CMS_ACCESS_DataChangeAdmin` in
  `Permission` and `PermissionRoleCode`, only when the configured code is not the old one;
- sets the `Implementation` of pending `QueuedJobDescriptor` rows to the new pruning job class, when queuedjobs is
  installed.

### Deep links

Links to a single change record change from `Symbiote-DataChange-Model-DataChangeRecord` to
`Dynamic-ChangeTracker-Model-DataChangeRecord`. Bookmarks to single records need updating. The list at
`admin/datachanges` is unchanged.

## Configuration reference

All settings are plain config. Defaults are shown.

| Class | Setting | Default | Purpose |
|---|---|---|---|
| `Dynamic\ChangeTracker\Extension\ChangeRecordable` | `ignored_fields` | `[]` | Map of class name to fields that are not recorded |
| `Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable` | `published_state_permission` | `CMS_ACCESS_LeftAndMain` | Permission code for the Published States tab. Administrators always see it |
| `Dynamic\ChangeTracker\Model\DataChangeRecord` | `save_request_vars` | `false` | Store GET and POST values with the record |
| `Dynamic\ChangeTracker\Model\DataChangeRecord` | `field_blacklist` | `['Password', 'SearchContent']` | Field names never stored, for any class |
| `Dynamic\ChangeTracker\Model\DataChangeRecord` | `request_vars_blacklist` | `['url', 'SecurityID']` | Request variables not stored when `save_request_vars` is on |
| `Dynamic\ChangeTracker\Admin\DataChangeAdmin` | `url_segment` | `datachanges` | Admin URL segment |
| `Dynamic\ChangeTracker\Admin\DataChangeAdmin` | `required_permission_codes` | `CMS_ACCESS_DataChangeAdmin` | Permission code for the Data Changes admin |
| `Dynamic\ChangeTracker\Admin\DataChangeAdmin` | `menu_title` | `Data Changes` | Menu title |
| `Dynamic\ChangeTracker\Admin\DataChangeAdmin` | `managed_models` | `DataChangeRecord` | Models shown by the admin |
| `Dynamic\ChangeTracker\Service\AffectedPagesService` | `element_class` | `null` (`BaseElement` with elemental) | Base class of content blocks whose page is looked up |
| `Dynamic\ChangeTracker\Control\AuditListingExtension` | `allowed_actions` | `listpages: ADMIN`, `listdocs: ADMIN` | Permission for each listing |
| `Dynamic\ChangeTracker\Control\AuditListingExtension` | `document_extensions` | `pdf doc docx xls xlsx ppt pptx csv rtf txt` | File types in `listdocs` |
| `Dynamic\ChangeTracker\ORM\TrackedManyManyList` | `trackedRelationships` | `[]` (set through the Injector) | Join tables whose add and remove are recorded |

The module already installs `TrackedManyManyList` for every many_many relation, so the site only lists its join
tables:

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\ORM\ManyManyList:
    class: Dynamic\ChangeTracker\ORM\TrackedManyManyList
    properties:
      trackedRelationships:
        - Page_Regions
```

Pages and data objects get the extension in config:

```yaml
MyDataObject:
  extensions:
    - Dynamic\ChangeTracker\Extension\ChangeRecordable
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable
```

## Affected pages

Each record stores the pages it affects in `DataChangeRecord_AffectedPages`, and the Page URL column of the admin
lists them. The lookup (`AffectedPagesService`) works like this:

- A record that is a page affects itself.
- has_one, has_many and many_many relations of the changed record are followed when they point at a SiteTree subclass
  (or at the element class, with elemental). A relation declared to `SiteTree` itself is ignored.
- Every page is then scanned for a has_one or many_many relation declared to exactly the class of the changed record.
  Subclasses are not matched.

The lookup is the one the sites used, including its quirks, which are kept on purpose because changing them changes
stored rows. They are listed in [docs/en/DEFERRED.md](docs/en/DEFERRED.md).

## LastEdited propagation

When a record that is not a page is published, the live row of each published page it affects gets the time of that
change as `LastEdited`. The draft row is not changed. Adds and removes on a tracked many_many relation do the same for
published, versioned owners. Only the live table is written (`LiveLastEditedPropagator`). The statement uses query
parameters.

This is what makes a page show a fresh last-modified time when content it displays changes.

## Last-modified meta tags

`LastModifiedExtension` adds `last-modified` and `article:modified_time` meta tags for a page, with the newer of the
page's own `LastEdited` and the `LastEdited` of the records it owns (`EffectiveLastEdited()`).

It adds methods only, with no hook and no config flag. The site's `Page::MetaComponents()` calls it where the tags
belong:

```php
public function MetaComponents()
{
    $tags = parent::MetaComponents();
    // ... the page's own tags
    $tags = $this->withLastModifiedMetaTags($tags);
    return $tags;
}
```

Why methods only: the hook that `MetaComponents()` runs is named differently on each line (`MetaComponents` on
Silverstripe 5, `updateMetaComponents` on Silverstripe 6), and a hook would also move the tags to a different place in
the output. The site keeps its position in the list.

## Audit listing endpoints

`AuditListingExtension` adds two review listings to a page controller, as plain HTML tables, newest first:

- `listpages`: every page with its ID, title, URL and `LastEdited`.
- `listdocs`: every document file, from the extensions in `document_extensions`.

Apply it to the page controller, for example in `_config`:

```yaml
SilverStripe\CMS\Controllers\ContentController:
  extensions:
    - Dynamic\ChangeTracker\Control\AuditListingExtension
```

Both actions require ADMIN by default. The rule is in the extension's own `allowed_actions`, because actions that come
from an extension are checked against the extension's config and not the controller's. Call them below any page, for
example `/about-us/listpages`.

## many_many tracking and its limitations

- Set `trackedRelationships` to the join tables to record. Other relations behave as the stock list does.
- Recorded: `add()` and `remove()` of a tracked relation, with the item's name and the owner page. A numeric string id
  (from a checkbox or tag field) is treated as an id. Adding an item that is already linked records nothing, unless
  its extra fields changed.
- Not recorded: removals made with `setByIDList()` and the removal of all items. These are pinned as they are in
  [docs/en/DEFERRED.md](docs/en/DEFERRED.md).
- Adding from the child's side of a `belongs_many_many` records the child's id as the owning page. This is pinned too.
- Over-long values are cut to their column size before writing (`mb_substr`, without a marker). Silverstripe 6 rejects
  such values, so the cut is applied on both lines.

## Testing

The suite needs a Silverstripe 5 project around the module, and a database user that can create databases:

```
vendor/bin/phpunit
```

With the harness used for the 1.x line, `./run.sh` runs the same suite in a DDEV project, and `./gha-mode.sh` mirrors
the `silverstripe/gha-ci` job. Run `FLUSH=1` after adding or removing test files so the manifest is rebuilt.

`tests/Characterization/` pins what the module does now, including the behaviour that is known to be wrong. A change
that alters an observable result must change its assertion in the same commit and be listed in
`tests/Characterization/CHANGES.md`. `tests/Characterization/golden/` holds the recorded outputs for the golden
scenarios. Setting `CHANGETRACKER_UPDATE_GOLDEN=1` records them again; the switch is refused when `CI` or
`GITHUB_ACTIONS` is set. The golden files must not change across a refactor.

`ElementalAffectedPagesTest` is skipped unless `dnadesign/silverstripe-elemental` is installed.

## Pruning old records

`PruneChangesBeforeJob` keeps the newest record older than a date and deletes the records before it, with their
affected page rows. Queue it from the QueuedJobs admin with a constructor argument such as `-6 months`. It requeues
itself each night.

`CleanupDataChangeHistoryTask` does the same as a build task at
`dev/tasks/Dynamic-ChangeTracker-Job-CleanupDataChangeHistoryTask?older=<date>`. It is a dry run unless `run` is given.
A date within the last three months also needs `force`.

## Credits

- Marcus Nyeholt and Symbiote (`symbiote/silverstripe-datachange-tracker`), BSD-3-Clause. This package keeps the
  licence in [LICENSE.md](LICENSE.md).
- The line `2` approach is informed by `nswdpc/silverstripe-datachange-tracker`.
- Dynamic maintains this package. Contact: dev@dynamicagency.com.

## Documentation

- [Quick start](docs/en/quick-start.md)
- [Adopting the module on a site](docs/en/ADOPTING.md)
- [Deferred defects](docs/en/DEFERRED.md)
- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)

# Adopting the module on a site

This guide is for the four sites that carry the fork (`symbiote/silverstripe-datachange-tracker`, branch
`custom-changeDetection-5`) and its copies of the tracking code:

| Site | Repository | Relations in `trackedRelationships` | Other tracking code |
|---|---|---|---|
| MyWayToRepay (MWTR) | `mywaytorepay.com` | 0 | 6 PHP files with the `use` of `SiteTreeChangeRecordable`; `app/src/Page.php` last-modified meta |
| Ascendium Education (AE) | `ascendiumeducation.org` | 5 | 10 `SiteTreeChangeRecordable` lines in `changetracker.yml` |
| Ascendium Philanthropy (AP) | `ascendiumphilanthropy.org` | 12 | 11 PHP files; 4 `SiteTreeChangeRecordable` lines; `cmstheme.yml:20`; `PageSeoExtension` and `BlogPostDataExtension` `MetaComponents` |
| Law for Learners (LFL) | `lawyersforlearners.org` | 0 | 15 PHP files with the `use`; `app/src/Page.php` last-modified meta |

The counts were read from the site repositories with no changes to them.

Each site has the same steps. Line `1` is for Silverstripe CMS 5 and comes first. Line `2` is for Silverstripe CMS 6, and
it is not released yet; its section lists what is different on SS6.

## Version matrix

| Module line | Silverstripe CMS | PHP | PHPUnit |
|---|---|---|---|
| `1` (`^1.0`) | `^5.3` | `^8.1` | 9.6 |
| `2` (`^2.0`) | `^6` | `^8.3` | 11 |

Moving from line `1` to line `2` is the constraint change `^1` to `^2`. The config keys are the same.

### Constraint before a tagged release

No release of either line is tagged yet, so there is no stable version for `^1.0` or `^2.0` to select. Until a release
exists, require the development version of the line, which follows its branch:

| Module line | Until the release is tagged | After |
|---|---|---|
| `1` | `"dynamic/silverstripe-change-tracker": "1.x-dev"` | `"^1.0"` once `1.0.0` is tagged |
| `2` | `"dynamic/silverstripe-change-tracker": "2.x-dev"` | `"^2.0"` once `2.0.0` is tagged |

The lock file pins the commit, so a site gets a newer commit of the branch only through a scoped update of the
package.

## Before you start (every site, every line)

1. Start from a clean working tree. Make a database snapshot and a backup of the assets. Record the baseline with the
   queries in "Verification SQL" below.
2. Run the read-only checks:
   - `grep -rn 'Symbiote' app/_config app/src app/tests` lists every reference to be changed.
   - `SELECT COUNT(*) FROM Permission WHERE Code = 'CMS_ACCESS_DataChangeAdmin';` must be `0`. The build migrates the
     old code to this one, so a site that already holds it would not be migrated cleanly.
3. Have the new package reachable. Until the module is listed on Packagist, add a VCS repository for it:

   ```json
   {
       "type": "vcs",
       "url": "https://github.com/dynamic/silverstripe-change-tracker.git"
   }
   ```

   The repository is public, so the HTTPS URL needs no SSH key on the servers or in CI. Packagist publication is a
   separate step that is not part of this milestone.

## Step 1: composer.json

1. Remove the require line `"symbiote/silverstripe-datachange-tracker"` and the fork's VCS repository, whose URL is
   `git@github.com:dynamic/silverstripe-datachange-tracker.git`.
2. Add `"dynamic/silverstripe-change-tracker": "1.x-dev"`. Change it to `"^1.0"` once `1.0.0` is tagged (see
   "Constraint before a tagged release").
3. Run a scoped update, and do not run `composer remove` or an unscoped `composer update`:

   ```
   ddev composer update dynamic/silverstripe-change-tracker --with-dependencies
   ```

4. Check the lock diff. It must show the two change tracker packages and nothing else:

   ```
   git diff composer.lock | grep '^[-+]    "name"'
   ```

   `composer show symbiote/silverstripe-datachange-tracker` must fail.

The module conflicts with `symbiote/silverstripe-datachange-tracker`, `sheadawson/silverstripe-datachange-tracker` and
`silverstripe-australia/datachange-tracker`, so the old package must be out of `composer.json` before the update.

## Step 2: changetracker.yml

Each site's `app/_config/changetracker.yml` is replaced. The rules:

- Every `Symbiote\DataChange\Extension\SiteTreeChangeRecordable` becomes `Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable`.
- The `Injector` block for `SilverStripe\ORM\ManyManyList` keeps the same `trackedRelationships` property list. The
  module already installs `TrackedManyManyList` for every many_many relation, so the `class:` line is removed. The
  property is read from the Injector instance as `trackedRelationships`; it is not a config key (see "Notes on
  the property name" below).
- `Symbiote\DataChange\Model\DataChangeRecord` becomes `Dynamic\ChangeTracker\Model\DataChangeRecord`. Its extension
  is removed, because the module applies `DataChangeRecordExtension` itself. A site's
  `field_blacklist: [SearchContent]` is redundant: the module's default is `[Password, SearchContent]`, and a site's
  list is merged with it. Keeping or removing the entry changes nothing.

### MWTR

```yaml
---
name: mwtr-change-tracker-config
---
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable
```

The `MWTR\DataChangeRecordDataExtension` line goes, and so does the `field_blacklist` block, which the module default
covers. MWTR has no `trackedRelationships`, so none is set.

### AE

```yaml
---
name: ascendium-education-change-tracker-config
---
SilverStripe\Core\Injector\Injector:
  SilverStripe\ORM\ManyManyList:
    properties:
      trackedRelationships:
        - ElementPromos_Promos
        - ElementAwards_Awards
        - ElementLeadership_Leadership
        - ElementMediaDownload_MediaDownloads
        - ElementOrganizations_Organizations

Dynamic\ChangeTracker\Model\DataChangeRecord:
  field_blacklist:
    - SearchContent

SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

DNADesign\Elemental\Models\BaseElement:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

Dynamic\BaseObject\Model\BaseElementObject:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

Dynamic\Elements\Model\Testimonial:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

Dynamic\SiteTools\Model\HeaderImage:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

AscendiumEducation\Model\LeadershipMember:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

Dynamic\Elements\StatCounters\Model\StatCounter:
  extensions:
    - SilverStripe\Versioned\Versioned
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

AscendiumEducation\Model\PressKit:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

AscendiumEducation\Model\PanelSlide:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable

AscendiumEducation\Model\ContentCard:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable
```

The `DataChangeRecordDataExtension` line in `app/_config/changetracker.yml` goes; the 5 relations stay.

### AP

Keep the 12 relations, in the same order:

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\ORM\ManyManyList:
    properties:
      trackedRelationships:
        - ElementPromos_Promos
        - GrantsFilterPage_FocusAreaFilters
        - ProactiveGrantPage_FocusAreas
        - ElementMediaDownload_MediaDownloads
        - ElementMediaPartners_MediaPartners
        - ElementPostPicker_PostsList
        - ElementStaffList_StaffMembers
        - FocusAreaElement_FocusAreas
        - BlogPost_FocusAreas
        - BlogPost_NewsTopics
        - BlogPost_CommunicationTypes
        - BlogPost_GrantType
```

The 4 `SiteTreeChangeRecordable` entries (`SiteTree`, `DNADesign\Elemental\Models\BaseElement`,
`Dynamic\BaseObject\Model\BaseElementObject`, `Dynamic\SiteTools\Model\HeaderImage`) get the new FQCN. The
`Ascendium\Extension\DataChangeRecordDataExtension` line goes.

AP's `app/_config/cmstheme.yml:20` lists `Symbiote-DataChange-Admin-DataChangeAdmin` in the "More" menu group. Change it to
`Dynamic-ChangeTracker-Admin-DataChangeAdmin`. The menu item is the admin's ID, so it changes with the namespace. Check
the menu after the build.

### LFL

```yaml
---
name: law-for-learners-change-tracker-config
---
Dynamic\ChangeTracker\Model\DataChangeRecord:
  field_blacklist:
    - SearchContent

SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable
```

The `LFL\DataChangeRecordDataExtension` line goes. LFL has no `trackedRelationships`.

## Step 3: PHP code

### `use` lines

Replace `use Symbiote\DataChange\Extension\SiteTreeChangeRecordable;` with
`use Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable;`. The files:

- MWTR (6): `app/src/DocumentDownload.php`, `DocumentDownloadFile.php`, `MediaDownload.php`, `SupporterObject.php`,
  `ToolKitSection.php`, `ToolKitSubSection.php`.
- AP (11): `app/src/Model/ASCAccordionContent.php`, `AnnualReportObject.php`, `ContentPagerSlide.php`,
  `ExpandingTopic.php`, `GalleryImage.php`, `GrantPartner.php`, `MediaPartner.php`, `OnTheGoObject.php`,
  `PositionLinkObject.php`, `ProactiveGrantResource.php`, `SlickSlideObject.php`.
- LFL (15): `app/src/CommunityPartner.php`, `DocumentDownload.php`, `DocumentDownloadFile.php`, `FlyerDownload.php`,
  `LegalClinic.php`, `MappedPartner.php`, `MediaDownload.php`, `Resource.php`, `ResourceItem.php`,
  `SchoolParticipant.php`, `ServiceContent.php`, `SponsorSupporter.php`, `StudentToolObject.php`, `ToolKitSection.php`,
  `ToolKitSubSection.php`.
- AE: no PHP file. Its references are in YAML only.

### Delete the copy of the data change record extension

| Site | File to delete |
|---|---|
| MWTR | `app/src/DataChangeRecordDataExtension.php` |
| AE | `app/src/Extension/DataChangeRecordDataExtension.php` |
| AP | `app/src/Extension/DataChangeRecordDataExtension.php` |
| LFL | `app/src/DataChangeRecordDataExtension.php` |

The module's `DataChangeRecordExtension` does the same work: the Page URL and affected pages columns, and the live
`LastEdited` update.

### Move listpages and listdocs to AuditListingExtension

In `app/src/PageController.php` remove the two `allowed_actions` entries (`listpages`, `listdocs`) and both methods.

| Site | `allowed_actions` lines | Methods |
|---|---|---|
| MWTR | 34-35 | `listpages()` at 85, `listdocs()` at 115 |
| AE | 32-33 | `listpages()` at 76, `listdocs()` at 99 |
| AP | 36-37 | `listpages()` at 156, `listdocs()` at 179 |
| LFL | 15-16 | `listpages()` at 29, `listdocs()` at 52 |

Then register the extension on the site's page controller, in `app/_config/`:

```yaml
PageController:
  extensions:
    - Dynamic\ChangeTracker\Control\AuditListingExtension
```

The URLs (`about-us/listpages` and `about-us/listdocs` on MWTR) and the ADMIN permission do not change.

### Last-modified meta (MWTR, LFL)

MWTR and LFL have their own copy of the last-modified code in `app/src/Page.php`: the protected methods
`LastModifiedMetaContent()` and `EffectiveLastEdited()`, and the block in `MetaComponents()` that adds `lastModified`
and `articleModifiedTime`.

- Keep `public function MetaComponents()` as it is, and keep the call to `withLastModifiedMetaTags()` in the same
  position as the block it replaces:
  - MWTR: after the `og:description` block, before `return $tags;`.
  - LFL: after `unset($tags['title']);`, before the block it replaces.
- Replace the block with `$tags = $this->withLastModifiedMetaTags($tags);`.
- Delete `LastModifiedMetaContent()` and `EffectiveLastEdited()`. The module's `LastModifiedExtension` has public
  methods with the same behaviour, and the page class uses them through `Page`'s extension list:

  ```yaml
  Page:
    extensions:
      - Dynamic\ChangeTracker\Extension\LastModifiedExtension
  ```

  Add that to the site's `changetracker.yml`.

The module ships methods only, with no hook. The `Page::MetaComponents()` call stays in place, so the tags appear in the
same order.

### AE and AP: no page-level code change

AE has no last-modified code. AP's `Seo/PageSeoExtension.php` and `Extension/BlogPostDataExtension.php` have
`MetaComponents(&$tags)`; those stay as they are on SS5. The SS6 rename is covered in the SS6 section.

## Step 4: tests and README

- MWTR: `app/tests/Cms/AdminScreensTest.php` and `app/tests/Tracker/TrackerSchemaTest.php` name the old class and the old
  permission code (`CMS_ACCESS_Symbiote\DataChange\Admin\DataChangeAdmin`). Update them to the new names, and keep the
  assertion that the old code is migrated.
- Every site: the README names the fork. Replace it with the module and this guide.

## Step 5: build and verify

1. Commit the changes (see "Rules for commits" below). Commit before the build.
2. Run `ddev sake dev/build flush=1` (SS5 uses `sake`, no `vendor/bin/sake`).
3. Run the verification SQL below, and compare with the baseline.
4. Open Data Changes, a single change record, the Published States tab on one page, and `listpages` and `listdocs`
   as an administrator and as an editor. The Data Changes menu item, and records for a publish and a many_many change.

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

-- pruning jobs (AP has pending ones); the old class must not be listed
SELECT Implementation, COUNT(*) FROM QueuedJobDescriptor WHERE Implementation LIKE '%DataChange%' GROUP BY Implementation;

-- live LastEdited values, a checksum before and after the swap
SELECT COUNT(*), SUM(UNIX_TIMESTAMP(LastEdited)) FROM SiteTree_Live;
```

Expected after the build: the `ClassName` query returns one row, `Dynamic\ChangeTracker\Model\DataChangeRecord`; the
orphan queries return 0; the permission queries return only `CMS_ACCESS_DataChangeAdmin`; the job query returns only
`Dynamic\ChangeTracker\Job\PruneChangesBeforeJob`. The live checksum must equal the baseline (the build does not change
`SiteTree_Live`). The change-record counts must equal the baseline plus the rows written by the site in between.

Also confirm that `grep -rn 'Symbiote' app/_config app/src app/tests` lists only the references this guide keeps (none
for the module), and that `SELECT ... WHERE Code = 'CMS_ACCESS_Symbiote%'` returns 0 rows.

## Rollback

Keep the baseline DB snapshot and the commit before the swap.

1. Revert the commit. Run `ddev composer update symbiote/silverstripe-datachange-tracker --with-dependencies` after
   restoring the old `composer.json`, and check the lock diff.
2. Reverse the data migration, after the build on the old code. Run it only if `CMS_ACCESS_DataChangeAdmin` was not
   already granted before the swap (the pre-check above):

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

3. If the reverse update does not give the baseline counts, restore the database snapshot instead. Rows written after
   the snapshot are lost in that case, unless they are re-inserted from the dump of the live database first.

Rehearse the rollback on a local copy before the production change.

## Notes on the property name

The module reads the list from the `trackedRelationships` property of `TrackedManyManyList`, which the Injector sets. It is
not a config key. A key named `tracked_relationships` would have no effect, so keep the `trackedRelationships` property
in the Injector block as shown.

## Rules for commits

- Commit the change before any `dev/build` or restore, so a build does not churn the docblocks in the diff.
- Commit message and PR text follow the site's own conventions.

## Site notes

### MWTR

- Relations: none tracked. `SiteTreeChangeRecordable` on SiteTree only. The six model files of Step 3.
- HomePage: the duplicate-field audit (below) applies. The Card fields are removed before the FieldGroups on SS6.
- Regression suite: its `expected` and `known-diffs` files name the old permission code and class. Update them with the
  suite's own runner, as part of the same change.

### AE

- Relations: 5 (listed in Step 2). `SiteTreeChangeRecordable` on 10 classes. On SS6, wrap the entries for
  `BaseElement`, `BaseElementObject` and `HeaderImage` in `Only: classexists:` if a site may not have the class.
- `Page.php:21` removes the `PublishedState` field. This is kept. Check the Published States tab after the swap, because
  visibility is set by `published_state_permission`, which defaults to `CMS_ACCESS_LeftAndMain`.
- Commit `eccc816` on AE's `2.1` branch should be reworded before it is pushed.

### AP

- Relations: 12. `SiteTreeChangeRecordable` on 4 classes. `cmstheme.yml:20` menu item.
- The BlogPost tag fields go through `setByIDList()`. Removals made that way are not recorded, and that stays the default
  (see [DEFERRED.md](DEFERRED.md), `setByIDList` removals). Turning removal tracking on would create new Remove rows,
  which the client would see.
- The build migrates the pruning jobs in `QueuedJobDescriptor`. Check that the verification query returns only the new
  class.
- After the swap the null-user warnings stop, which the log shows as a large number of warnings from the tracker.

### LFL

- Relations: none. `SiteTreeChangeRecordable` on SiteTree only. Fifteen model files.
- `Page::MetaComponents()` keeps `unset($tags['title'])` before the call to `withLastModifiedMetaTags()`.

## Silverstripe CMS 6 (line 2)

Line `2` is not cut yet. These notes apply once it is released.

1. Change the constraint to line `2`: `2.x-dev` until `2.0.0` is tagged, `^2.0` after (see "Constraint before a tagged
   release"). The config keys and class names are the same.
2. Composer: the site's PHP is `^8.3`, and the framework and CMS packages move to `^6`. The rest of the stack follows
   the site's own SS6 upgrade plan.
3. **Hook renames.** On SS6 `MetaComponents` is called as `updateMetaComponents`.
   - AP: `Seo/PageSeoExtension::MetaComponents(&$tags)` and `Extension/BlogPostDataExtension::MetaComponents(&$tags)`
     become `updateMetaComponents(&$tags)`. Without the rename they silently stop firing.
   - MWTR and LFL: the page class calls the module's method directly, so no rename applies to them.
4. **Duplicate-field audit.** On SS6, page types scaffold some fields (for example `Card1Title`, `Card1Content`,
   `Card1Link`). A site that adds the same name again in a FieldGroup gets a "field appears twice" error on the edit
   form. Do this for every page class of every site before the SS6 build:
   - list the fields added in `getCMSFields()` by `addFieldToTab`, `FieldGroup` and `CompositeField`, and the `$db`
     and `$has_one` names of the class;
   - for each name that is both scaffolded and added again, `removeByName` it before the group is built.
   - MWTR's HomePage (the nine Card fields) is the known case.
5. **PasswordValidator.** The class is removed on SS6 and is fatal if configured. Use `RulesPasswordValidator` with the
   same rules (length, digits, letters) as the site's current validator.
6. **TinyMCE 6** and the rest of the SS6 config changes follow the SS6 upgrade for the site.
7. Run the verification SQL again after `dev/build flush=1`.

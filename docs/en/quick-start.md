# Quick start

## Install

```
composer require dynamic/silverstripe-change-tracker:^1.0
```

Line `1` is for Silverstripe CMS 5. The module is applied by its own configuration, so no file in the project needs
to change for it to run.

## Track a data object

Add the ChangeRecordable extension to any data object you want recorded:

```yaml
MyDataObject:
  extensions:
    - Dynamic\ChangeTracker\Extension\ChangeRecordable
```

For a page, use the SiteTreeChangeRecordable extension instead. It also records publish and unpublish:

```yaml
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable
```

Published States is a tab on the page's edit form. Its visibility is set by `published_state_permission`, which
defaults to `CMS_ACCESS_LeftAndMain`.

## Track a many_many relation

The module already installs `TrackedManyManyList` for every many_many relation. Nothing is recorded until a join
table is listed in `trackedRelationships`:

```yaml
SilverStripe\Core\Injector\Injector:
  SilverStripe\ORM\ManyManyList:
    properties:
      trackedRelationships:
        - Page_Regions
```

This records adds and removes of the "Regions" relation of the Page class.

## Ignore fields

```yaml
Dynamic\ChangeTracker\Extension\ChangeRecordable:
  ignored_fields:
    NameOfObjectClass:
      - NameOfField
```

Fields named in `Dynamic\ChangeTracker\Model\DataChangeRecord.field_blacklist` are never stored, for any class.
The default blacklist is `Password` and `SearchContent`.

## View the changes

Log in as an administrator (or as a member of a group with `CMS_ACCESS_DataChangeAdmin`) and open **Data Changes**.

## Review pages and documents

Add `Dynamic\ChangeTracker\Control\AuditListingExtension` to the page controller to get `listpages` and `listdocs`
for administrators. See the README for the configuration.

## Prune old records

Queue `PruneChangesBeforeJob` from the QueuedJobs admin with a constructor argument such as `-6 months`. It keeps the
newest record older than that date and requeues itself each night. The README has the one-off build task.

## Next steps

- [README](../../README.md) for the configuration reference, the affected pages lookup and the upgrade steps.
- [Deferred defects](DEFERRED.md) for behaviour that is kept on purpose.

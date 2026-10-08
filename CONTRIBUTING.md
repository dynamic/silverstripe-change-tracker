# Contributing

Issues and pull requests are welcome at the repository of `dynamic/silverstripe-change-tracker`.

## Branches

- `1` is the line for Silverstripe CMS 5. Feature branches are cut from it and merged back with `--no-ff`.
- `2`, when it exists, is the line for Silverstripe CMS 6. Fixes that apply to both lines are merged into `2` from `1`.

Name feature branches for the issue they address, for example `feature/12-slug` or `fix/12-slug`.

## Before a change is merged

- The test suite passes (see the Testing section of the [README](README.md)).
- The golden files in `tests/Characterization/golden/` are unchanged, unless the change is meant to change recorded
  output. Such a change needs an updated assertion, a line in `tests/Characterization/CHANGES.md`, and an entry in the
  CHANGELOG under "Intentional behaviour changes".
- Coding standard: `phpcs.xml.dist` (PSR-12, with the exceptions listed in that file).
- The CHANGELOG has an entry under the next version for each user-visible change.

## Commit messages

Use a conventional prefix and the issue number: `fix(#23): ...`, `feat(#71): ...`, `test: ...`, `docs: ...`.

## Reporting a defect

Give the Silverstripe CMS and PHP versions, the module line, the class and setting involved, and what was stored or
shown. If the defect changes output that sites already rely on, check [docs/en/DEFERRED.md](docs/en/DEFERRED.md)
first; it may already be listed there with the test that pins it.

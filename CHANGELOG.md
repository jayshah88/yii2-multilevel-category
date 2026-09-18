The yii2-multilevel-category extension for the Yii2 Framework Change Log
========================================================================

2.0.0 under development
-----------------------

- Fix: PSR-4 autoloading now works on every platform. The class moved from
  `multilevel.php` to `src/Multilevel.php` (the previous layout only worked on
  case-insensitive filesystems such as Windows). Both `Jay\Multilevel` and the
  historic lowercase `jay\Multilevel` references are supported.
- Enh: the dropdown is now built from a single query instead of one query per
  node (N+1 removed).
- Enh: removed the `global $data` state; results are computed per call.
- Enh: added cycle protection and an optional `maxDepth` guard so corrupt
  adjacency data can no longer cause infinite recursion.
- Enh: new configuration options: `idAttribute`, `parentAttribute`,
  `titleAttribute`, `rootValue`, `rootLabel`, `indent`, `orderAttribute`,
  `orderDirection`, `maxDepth` and `queryCallback`.
- Enh: `makeDropDown()` now also accepts `null` (auto-detect roots), an
  `ActiveQuery`, a model class name, raw ids or `asArray()` rows.
- Enh: new `Multilevel::buildTree()` returning a nested tree for custom
  rendering (e.g. `<ul>` menus).
- Chg: `Multilevel::subDropDown()` was removed; it depended on global state
  and was never meant to be called directly.
- Chg: `composer.json` modernized: PHP `^7.4 || ^8.0`, `yiisoft/yii2`
  `^2.0.46`, `minimum-stability: dev` removed.
- Add: deprecated backward-compatibility shim kept at `multilevel.php` for
  consumers that require the file manually.
- Add: PHPUnit test suite, GitHub Actions CI workflow, `LICENSE` file and a
  modernized `sample-data.sql` (utf8mb4 + index on the `root` column).

1.0.0-alpha under development
-----------------------------

- Initial release: `Jay\Multilevel::makeDropDown()` with recursive per-node
  queries.

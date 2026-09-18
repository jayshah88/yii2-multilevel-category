yii2-multilevel-category extension for Yii2
===========================================

[![CI](https://github.com/jayshah88/yii2-multilevel-category/actions/workflows/ci.yml/badge.svg)](https://github.com/jayshah88/yii2-multilevel-category/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E7.4%20%7C%7C%20%5E8.0-777BB4.svg)](https://www.php.net)
[![Yii2](https://img.shields.io/badge/Yii2-%5E2.0.46-88CCFF.svg)](https://www.yiiframework.com)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE)

`yii2-multilevel-category` builds n-level hierarchical category dropdowns for
Yii2 from an adjacency-list table (a single column, `root` by default, holds
the id of the parent row).

Features
========

- Renders `id => indented label` option lists ready for `dropDownList()` at
  any nesting depth.
- Loads the whole tree with a **single query** (the 1.x version issued one
  query per node).
- Accepts active record instances, `ActiveQuery` objects, model class names,
  raw ids, `asArray()` rows or `null` (auto-detects root rows).
- Configurable attribute names, root label, indentation and ordering.
- Cycle-safe: corrupt adjacency data can no longer cause infinite recursion.
- `Multilevel::buildTree()` returns a nested tree for custom rendering
  (`<ul>` menus, `<optgroup>`s, etc.).
- Backward compatible: the 1.x call signature still works.

Requirements
============

- PHP 7.4 or newer (7.4 - 8.x).
- Yii2 2.0.46 or newer.

Installation
============

```
composer require jayshah88/yii2-multilevel-category
```

Usage
=====

Classic, 1.x compatible
-----------------------

```php
use Jay\Multilevel;
use common\models\base\Category;

$category = new Category();
$parents = Category::find()->where(['root' => 0])->all();

echo $form->field($model, 'root')
    ->dropDownList(Multilevel::makeDropDown($parents, $category));
```

Recommended
-----------

Root rows are detected automatically, `$model` can be a class name, an active
record instance or an `ActiveQuery`:

```php
use Jay\Multilevel;
use common\models\base\Category;

echo $form->field($model, 'root')
    ->dropDownList(Multilevel::makeDropDown(null, Category::class));
```

Options
-------

Pass any of the public properties as the third argument:

```php
$items = Multilevel::makeDropDown(null, Category::class, [
    'idAttribute' => 'id',            // primary-key attribute
    'parentAttribute' => 'root',      // attribute holding the parent id
    'titleAttribute' => 'title',      // attribute rendered as the label
    'rootValue' => 0,                 // value identifying root rows
    'rootLabel' => '-- ROOT --',      // null to omit the root option
    'indent' => '---',                // indentation per level
    'orderAttribute' => 'title',      // order siblings by (null = by id)
    'orderDirection' => SORT_ASC,     // SORT_ASC or SORT_DESC
    'maxDepth' => 5,                  // safety net against runaway trees
    'queryCallback' => function ($query) {
        // scope the rows loaded for very large tables
        $query->andWhere(['status' => 1]);
    },
]);
```

The same options can also be set per instance or application-wide via the
DI container:

```php
$ml = new Multilevel(['titleAttribute' => 'name']);
echo $form->field($model, 'root')->dropDownList($ml->getItems(null, Category::class));
```

Nested rendering
----------------

`buildTree()` returns the hierarchy as a nested array, ideal for menus:

```php
use Jay\Multilevel;
use yii\helpers\Html;

$tree = Multilevel::buildTree(null, Category::class, ['orderAttribute' => 'title']);

function renderList(array $nodes): void
{
    echo '<ul>';
    foreach ($nodes as $node) {
        echo '<li>' . Html::encode($node['title']);
        renderList($node['children']);
        echo '</li>';
    }
    echo '</ul>';
}

renderList($tree);
```

Each branch contains `node` (the active record or array row), `id`, `title`
and `children`.

How it works
============

All category rows are loaded with one query and linked in memory by their
parent attribute, so the cost is `O(n)` database work regardless of the tree
depth. Rows whose parent cannot be resolved are treated as orphans and are
hidden, exactly like in 1.x. Cyclic rows (a parent chain that loops back on
itself) are skipped instead of recursing forever. For very large tables use
`queryCallback` to limit the loaded rows.

Database schema
===============

See `sample-data.sql` for a ready-to-use schema: the table needs a primary
key (`id`), the parent column (`root`, `0` = top level) and a label column
(`title`). An index on the parent column is recommended.

Testing
=======

```
composer install
composer test
```

The test suite runs against an in-memory SQLite database, so no external
services are required.

Upgrading from 1.x
==================

- The class now lives in `src/Multilevel.php` and is autoloaded properly on
  case-sensitive filesystems (the old layout only worked on Windows).
- `use jay\Multilevel;` and `use Jay\Multilevel;` are both supported.
- `Multilevel::makeDropDown($parents, $model)` keeps working unchanged.
- `subDropDown()` was removed (it relied on `global $data`).

See `CHANGELOG.md` for details.

License
=======

BSD-3-Clause. See `LICENSE`.

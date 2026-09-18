<?php
/**
 * @link https://github.com/jayshah88/yii2-multilevel-category
 * @copyright Copyright (c) Jay Shah <shahjay88@gmail.com>
 * @license BSD-3-Clause
 */

declare(strict_types=1);

namespace Jay;

use Traversable;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecordInterface;
use yii\db\Query;

/**
 * Multilevel builds an n-level hierarchical dropdown list from an
 * adjacency-list category table (each row stores the id of its parent row in
 * a single column, `root` by default).
 *
 * Basic usage, backward compatible with the 1.x API:
 *
 * ```php
 * use Jay\Multilevel;
 *
 * $category = new Category();
 * $parents = Category::find()->where(['root' => 0])->all();
 *
 * echo $form->field($model, 'root')
 *     ->dropDownList(Multilevel::makeDropDown($parents, $category));
 * ```
 *
 * Modern usage — root rows are detected automatically and the whole tree is
 * fetched with a single query:
 *
 * ```php
 * echo $form->field($model, 'root')
 *     ->dropDownList(Multilevel::makeDropDown(null, Category::class));
 * ```
 *
 * The same data can be consumed as a nested tree via [[buildTree()]] in order
 * to render `<ul>` menus, grouped `<optgroup>`s, etc.
 *
 * @author Jay Shah <shahjay88@gmail.com>
 * @since 1.0
 */
class Multilevel extends Component
{
    /**
     * @var string name of the attribute that holds the primary key.
     */
    public string $idAttribute = 'id';

    /**
     * @var string name of the attribute that holds the parent category id.
     */
    public string $parentAttribute = 'root';

    /**
     * @var string name of the attribute rendered as the option label.
     */
    public string $titleAttribute = 'title';

    /**
     * @var mixed value that identifies a top-level (root) row. Rows whose
     * [[parentAttribute]] does not match this value and whose parent cannot
     * be found are treated as orphans and are not rendered unless they are
     * passed explicitly as parents.
     */
    public $rootValue = 0;

    /**
     * @var string|null label prepended to the list as the "no parent" option.
     * The option key is [[rootValue]]. Set to `null` to omit the option.
     */
    public ?string $rootLabel = '-- ROOT --';

    /**
     * @var string string repeated once per depth level before the label.
     */
    public string $indent = '---';

    /**
     * @var string|null optional attribute used to order the items (e.g.
     * `title` or a `position` sort column). When `null`, items are ordered by
     * [[idAttribute]] ascending, matching the 1.x behaviour.
     */
    public ?string $orderAttribute = null;

    /**
     * @var int sort direction used together with [[orderAttribute]]
     * (`SORT_ASC` or `SORT_DESC`).
     */
    public int $orderDirection = SORT_ASC;

    /**
     * @var int|null maximum number of levels rendered. `null` means
     * unlimited. Acts as a safety net against runaway trees.
     */
    public ?int $maxDepth = null;

    /**
     * @var callable|null optional `function (\yii\db\ActiveQueryInterface $query): void`
     * callback applied to the query before it is executed, e.g. to scope the
     * loaded rows for very large tables.
     */
    public $queryCallback = null;

    /**
     * Builds the flat list accepted by `Html::dropDownList()` and
     * `ActiveForm::dropDownList()`.
     *
     * @param mixed $parents records, arrays (e.g. from `asArray()` queries),
     * scalar ids — or a single one of those, or `null` to auto-detect all
     * root rows.
     * @param ActiveRecordInterface|ActiveQueryInterface|string $model active
     * record instance, active query or active record class name the
     * categories are read from.
     * @param array $config name-value pairs used to configure the instance.
     * @return array list of options in `id => label` format.
     * @throws InvalidConfigException when `$model` cannot be resolved.
     * @throws InvalidArgumentException when a parent entry is not supported.
     * @since 1.0
     */
    public static function makeDropDown($parents, $model, array $config = []): array
    {
        return (new static($config))->getItems($parents, $model);
    }

    /**
     * Builds a nested tree in the form:
     *
     * ```php
     * [
     *     [
     *         'node' => $record,
     *         'id' => 1,
     *         'title' => 'Test 1',
     *         'children' => [ ... ],
     *     ],
     *     ...
     * ]
     * ```
     *
     * @param mixed $parents see [[makeDropDown()]].
     * @param ActiveRecordInterface|ActiveQueryInterface|string $model see [[makeDropDown()]].
     * @param array $config see [[makeDropDown()]].
     * @return array nested tree.
     * @throws InvalidConfigException when `$model` cannot be resolved.
     * @throws InvalidArgumentException when a parent entry is not supported.
     * @since 2.0
     */
    public static function buildTree($parents, $model, array $config = []): array
    {
        return (new static($config))->getTree($parents, $model);
    }

    /**
     * Instance version of [[makeDropDown()]].
     *
     * @param mixed $parents see [[makeDropDown()]].
     * @param ActiveRecordInterface|ActiveQueryInterface|string $model see [[makeDropDown()]].
     * @return array list of options in `id => label` format.
     * @since 2.0
     */
    public function getItems($parents, $model): array
    {
        $items = [];
        if ($this->rootLabel !== null) {
            $items[self::normalizeKey($this->rootValue)] = $this->rootLabel;
        }

        foreach ($this->getTree($parents, $model) as $branch) {
            $this->collectItems($branch, '', [], $items);
        }

        return $items;
    }

    /**
     * Instance version of [[buildTree()]].
     *
     * @param mixed $parents see [[makeDropDown()]].
     * @param ActiveRecordInterface|ActiveQueryInterface|string $model see [[makeDropDown()]].
     * @return array nested tree.
     * @since 2.0
     */
    public function getTree($parents, $model): array
    {
        // An active record implements Traversable (its attributes), so it
        // must be wrapped as a single-parent list instead of iterated.
        if (
            $parents !== null
            && !is_array($parents)
            && !($parents instanceof Traversable && !$parents instanceof ActiveRecordInterface)
        ) {
            $parents = [$parents];
        }

        $nodes = $this->loadNodes($model);
        $startIds = $parents === null
            ? $this->detectRootIds($nodes)
            : $this->extractIds($parents);

        $tree = [];
        foreach ($startIds as $id) {
            $branch = $this->buildBranch(self::normalizeKey($id), $nodes, []);
            if ($branch !== null) {
                $tree[] = $branch;
            }
        }

        return $tree;
    }

    /**
     * Loads every category row with a single query and indexes it by parent
     * id.
     *
     * @param ActiveRecordInterface|ActiveQueryInterface|string $model see [[makeDropDown()]].
     * @return array map of `id => ['node', 'id', 'parent', 'title', 'childIds']`.
     * @throws InvalidConfigException when `$model` cannot be resolved.
     */
    private function loadNodes($model): array
    {
        $query = $this->resolveQuery($model);

        if ($this->orderAttribute !== null) {
            $query->orderBy([$this->orderAttribute => $this->orderDirection]);
        } elseif ($query instanceof Query && empty($query->orderBy)) {
            // deterministic order, mirrors the natural 1.x behaviour
            $query->orderBy([$this->idAttribute => SORT_ASC]);
        }

        if ($this->queryCallback !== null) {
            call_user_func($this->queryCallback, $query);
        }

        $nodes = [];
        foreach ($query->all() as $record) {
            $id = self::normalizeKey(self::readAttribute($record, $this->idAttribute));
            if ($id === null) {
                continue;
            }
            $nodes[$id] = [
                'node' => $record,
                'id' => $id,
                'parent' => self::normalizeKey(self::readAttribute($record, $this->parentAttribute)),
                'title' => (string) self::readAttribute($record, $this->titleAttribute),
                'childIds' => [],
            ];
        }

        $rootKey = self::normalizeKey($this->rootValue);
        foreach ($nodes as $id => $entry) {
            $parent = $entry['parent'];
            if (self::sameKey($parent, $rootKey)) {
                continue;
            }
            if (isset($nodes[$parent])) {
                $nodes[$parent]['childIds'][] = $id;
            }
            // rows whose parent is missing (orphans) are not attached and
            // therefore never rendered, matching the 1.x behaviour
        }

        return $nodes;
    }

    /**
     * @return ActiveQueryInterface the query used to load the rows.
     * @throws InvalidConfigException when `$model` cannot be resolved.
     */
    private function resolveQuery($model): ActiveQueryInterface
    {
        if ($model instanceof ActiveQueryInterface) {
            return clone $model;
        }

        if ($model instanceof ActiveRecordInterface) {
            return $model->find();
        }

        if (is_string($model) && $model !== '' && class_exists($model)) {
            if (is_subclass_of($model, ActiveRecordInterface::class)) {
                return $model::find();
            }
            throw new InvalidConfigException(sprintf(
                'Class "%s" does not implement %s.',
                $model,
                ActiveRecordInterface::class
            ));
        }

        throw new InvalidConfigException(sprintf(
            'Unsupported $model of type "%s". Expected an active record instance, an active query or a class name.',
            is_object($model) ? get_class($model) : gettype($model)
        ));
    }

    /**
     * @return array ids of the rows to start the tree from.
     * @throws InvalidArgumentException when a parent entry is not supported.
     */
    private function extractIds($parents): array
    {
        if ($parents instanceof Traversable) {
            $parents = iterator_to_array($parents);
        }

        $ids = [];
        foreach ($parents as $parent) {
            if (is_object($parent) || is_array($parent)) {
                $ids[] = self::readAttribute($parent, $this->idAttribute);
            } elseif (is_scalar($parent) || $parent === null) {
                $ids[] = $parent;
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Unsupported parent item of type "%s". Expected an active record, an array, a scalar id or null.',
                    gettype($parent)
                ));
            }
        }

        return $ids;
    }

    /**
     * @return array ids of all rows whose parent matches [[rootValue]].
     */
    private function detectRootIds(array $nodes): array
    {
        $rootKey = self::normalizeKey($this->rootValue);
        $ids = [];
        foreach ($nodes as $id => $entry) {
            if (self::sameKey($entry['parent'], $rootKey)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Recursively builds one tree branch.
     *
     * @param mixed $id id of the branch root.
     * @param array $nodes map produced by [[loadNodes()]].
     * @param array $seen ids on the current path, used as cycle guard.
     * @return array|null the branch or `null` when the node is unknown or
     * already visited (cyclic data).
     */
    private function buildBranch($id, array $nodes, array $seen): ?array
    {
        if (!isset($nodes[$id]) || in_array($id, $seen, true)) {
            return null;
        }
        if ($this->maxDepth !== null && count($seen) >= $this->maxDepth) {
            return null;
        }

        $seen[] = $id;
        $entry = $nodes[$id];

        $children = [];
        foreach ($entry['childIds'] as $childId) {
            $child = $this->buildBranch($childId, $nodes, $seen);
            if ($child !== null) {
                $children[] = $child;
            }
        }

        return [
            'node' => $entry['node'],
            'id' => $id,
            'title' => $entry['title'],
            'children' => $children,
        ];
    }

    /**
     * Recursively flattens a tree branch into the dropdown items.
     *
     * @param array $branch branch produced by [[buildBranch()]].
     * @param string $prefix indentation accumulated so far.
     * @param array $seen ids on the current path, used as cycle guard.
     * @param array $items output list, passed by reference.
     */
    private function collectItems(array $branch, string $prefix, array $seen, array &$items): void
    {
        if (in_array($branch['id'], $seen, true)) {
            return;
        }
        if ($this->maxDepth !== null && count($seen) >= $this->maxDepth) {
            return;
        }

        $items[$branch['id']] = $prefix . $branch['title'];
        $seen[] = $branch['id'];

        foreach ($branch['children'] as $child) {
            $this->collectItems($child, $prefix . $this->indent, $seen, $items);
        }
    }

    /**
     * Compares two ids after normalization, so that `0`, `'0'` and `null`
     * configurations behave consistently.
     *
     * @param mixed $a first value.
     * @param mixed $b second value.
     * @return bool whether both values refer to the same key.
     */
    private static function sameKey($a, $b): bool
    {
        return self::normalizeKey($a) === self::normalizeKey($b);
    }

    /**
     * Normalizes a value so it can safely be used as an array key for node
     * lookups. Numeric strings are converted to integers, everything else is
     * returned unchanged (PHP applies the same coercion for array keys).
     *
     * @param mixed $value value to normalize.
     * @return mixed the normalized key.
     */
    private static function normalizeKey($value)
    {
        if (is_string($value) && preg_match('/^-?(0|[1-9]\d*)$/', $value)) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * Reads an attribute from an active record instance or an array row.
     *
     * @param ActiveRecordInterface|array $record record to read from.
     * @param string $attribute attribute name.
     * @return mixed the attribute value or `null` when it is not available.
     */
    private static function readAttribute($record, string $attribute)
    {
        if (is_array($record)) {
            return array_key_exists($attribute, $record) ? $record[$attribute] : null;
        }

        return isset($record->{$attribute}) ? $record->{$attribute} : null;
    }
}

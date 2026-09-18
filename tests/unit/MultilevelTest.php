<?php

declare(strict_types=1);

namespace tests\unit;

use tests\models\Category;
use tests\models\CategoryAlt;
use tests\support\CountingConnection;
use Jay\Multilevel;
use PHPUnit\Framework\TestCase;
use yii\base\InvalidConfigException;
use yii\db\ActiveQuery;
use Yii;

/**
 * Test suite for [[Jay\Multilevel]].
 */
class MultilevelTest extends TestCase
{
    /**
     * Fixture:
     *
     * - 1 "Test 1" (root)
     *   - 2 "Child 1"
     *     - 3 "Grandchild 1"
     *       - 6 "Great Grandchild 1"
     *   - 5 "Child 2"
     * - 4 "Test 2" (root)
     */
    protected function setUp(): void
    {
        parent::setUp();

        $db = Yii::$app->getDb();
        $db->createCommand('DROP TABLE IF EXISTS [[category]]')->execute();
        $db->createCommand('DROP TABLE IF EXISTS [[category_alt]]')->execute();
        $db->createCommand(
            'CREATE TABLE [[category]] ('
            . '[[id]] INTEGER PRIMARY KEY AUTOINCREMENT, '
            . '[[root]] INTEGER NOT NULL DEFAULT 0, '
            . '[[title]] VARCHAR(255) NOT NULL)'
        )->execute();
        $db->createCommand()->batchInsert('{{%category}}', ['id', 'root', 'title'], [
            [1, 0, 'Test 1'],
            [2, 1, 'Child 1'],
            [3, 2, 'Grandchild 1'],
            [4, 0, 'Test 2'],
            [5, 1, 'Child 2'],
            [6, 3, 'Great Grandchild 1'],
        ])->execute();

        CountingConnection::$selectCount = 0;
    }

    public function testMakeDropDownProducesIndentedHierarchy(): void
    {
        $items = Multilevel::makeDropDown(null, Category::class);

        $this->assertSame([
            0 => '-- ROOT --',
            1 => 'Test 1',
            2 => '---Child 1',
            3 => '------Grandchild 1',
            6 => '---------Great Grandchild 1',
            5 => '---Child 2',
            4 => 'Test 2',
        ], $items);
    }

    public function testBackwardCompatibleInstanceUsage(): void
    {
        $category = new Category();
        $parents = Category::find()->where(['root' => 0])->all();

        $items = (new Multilevel())->makeDropDown($parents, $category);

        $this->assertSame([
            0 => '-- ROOT --',
            1 => 'Test 1',
            2 => '---Child 1',
            3 => '------Grandchild 1',
            6 => '---------Great Grandchild 1',
            5 => '---Child 2',
            4 => 'Test 2',
        ], $items);
    }

    public function testSupportsRawIdsAndRecords(): void
    {
        $byRecords = Multilevel::makeDropDown(Category::find()->where(['root' => 0])->all(), Category::class);
        $byIds = Multilevel::makeDropDown([1, 4], Category::class);
        $bySingleId = Multilevel::makeDropDown(4, Category::class);

        $this->assertSame($byRecords, $byIds);
        $this->assertSame(4, array_search('Test 2', $byIds, true));
        $this->assertSame([0 => '-- ROOT --', 4 => 'Test 2'], $bySingleId);
    }

    public function testSubtreeFromSingleRecord(): void
    {
        $items = Multilevel::makeDropDown(Category::findOne(2), Category::class);

        $this->assertSame([
            0 => '-- ROOT --',
            2 => 'Child 1',
            3 => '---Grandchild 1',
            6 => '------Great Grandchild 1',
        ], $items);
    }

    public function testWholeTreeBuiltWithSingleQuery(): void
    {
        Multilevel::makeDropDown(null, Category::class);

        $this->assertSame(1, CountingConnection::$selectCount);
    }

    public function testCustomAttributes(): void
    {
        $db = Yii::$app->getDb();
        $db->createCommand(
            'CREATE TABLE [[category_alt]] ('
            . '[[id]] INTEGER PRIMARY KEY AUTOINCREMENT, '
            . '[[parent_id]] INTEGER NOT NULL DEFAULT 0, '
            . '[[name]] VARCHAR(255) NOT NULL)'
        )->execute();
        $db->createCommand()->batchInsert('{{%category_alt}}', ['id', 'parent_id', 'name'], [
            [1, 0, 'Animals'],
            [2, 1, 'Cats'],
            [3, 2, 'Siamese'],
            [4, 0, 'Plants'],
        ])->execute();

        $items = Multilevel::makeDropDown(null, CategoryAlt::class, [
            'parentAttribute' => 'parent_id',
            'titleAttribute' => 'name',
        ]);

        $this->assertSame([
            0 => '-- ROOT --',
            1 => 'Animals',
            2 => '---Cats',
            3 => '------Siamese',
            4 => 'Plants',
        ], $items);
    }

    public function testRootLabelCanBeChangedOrRemoved(): void
    {
        $items = Multilevel::makeDropDown(null, Category::class, ['rootLabel' => 'Choose a category…']);
        $this->assertSame('Choose a category…', $items[0]);

        $items = Multilevel::makeDropDown(null, Category::class, ['rootLabel' => null]);
        $this->assertArrayNotHasKey(0, $items);
    }

    public function testCustomIndent(): void
    {
        $items = Multilevel::makeDropDown(null, Category::class, ['indent' => '· ']);

        $this->assertSame('· Child 1', $items[2]);
        $this->assertSame('· · Grandchild 1', $items[3]);
    }

    public function testOrderAttributeSortsSiblings(): void
    {
        Yii::$app->getDb()->createCommand()
            ->insert('{{%category}}', ['id' => 7, 'root' => 1, 'title' => 'Aardvark'])
            ->execute();

        $items = Multilevel::makeDropDown(null, Category::class, ['orderAttribute' => 'title']);

        $this->assertSame([
            0 => '-- ROOT --',
            1 => 'Test 1',
            7 => '---Aardvark',
            2 => '---Child 1',
            3 => '------Grandchild 1',
            6 => '---------Great Grandchild 1',
            5 => '---Child 2',
            4 => 'Test 2',
        ], $items);
    }

    public function testMaxDepthLimitsRenderedLevels(): void
    {
        $items = Multilevel::makeDropDown(null, Category::class, ['maxDepth' => 2]);

        $this->assertSame([
            0 => '-- ROOT --',
            1 => 'Test 1',
            2 => '---Child 1',
            5 => '---Child 2',
            4 => 'Test 2',
        ], $items);
    }

    public function testCycleDoesNotCauseInfiniteRecursion(): void
    {
        Yii::$app->getDb()->createCommand()->batchInsert('{{%category}}', ['id', 'root', 'title'], [
            [10, 11, 'Cycle A'],
            [11, 10, 'Cycle B'],
        ])->execute();

        $items = Multilevel::makeDropDown(Category::findOne(10), Category::class);

        $this->assertSame([
            0 => '-- ROOT --',
            10 => 'Cycle A',
            11 => '---Cycle B',
        ], $items);
    }

    public function testOrphanRowsAreNotRenderedAsRoots(): void
    {
        Yii::$app->getDb()->createCommand()
            ->insert('{{%category}}', ['id' => 12, 'root' => 99, 'title' => 'Orphan'])
            ->execute();

        $items = Multilevel::makeDropDown(null, Category::class);
        $this->assertArrayNotHasKey(12, $items);

        // orphans can still be rendered when passed explicitly
        $items = Multilevel::makeDropDown(12, Category::class);
        $this->assertSame([0 => '-- ROOT --', 12 => 'Orphan'], $items);
    }

    public function testEmptyTable(): void
    {
        Yii::$app->getDb()->createCommand('DELETE FROM [[category]]')->execute();

        $items = Multilevel::makeDropDown(null, Category::class);

        $this->assertSame([0 => '-- ROOT --'], $items);
    }

    public function testQueryCallbackScopesLoadedRows(): void
    {
        $items = Multilevel::makeDropDown(null, Category::class, [
            'queryCallback' => static function (ActiveQuery $query): void {
                $query->andWhere(['<', 'id', 4]);
            },
        ]);

        $this->assertSame([
            0 => '-- ROOT --',
            1 => 'Test 1',
            2 => '---Child 1',
            3 => '------Grandchild 1',
        ], $items);
    }

    public function testSupportsAsArrayQueries(): void
    {
        $items = Multilevel::makeDropDown(null, Category::find()->asArray());

        $this->assertSame('---Child 1', $items[2]);
        $this->assertArrayHasKey(4, $items);
    }

    public function testBuildTreeReturnsNestedStructure(): void
    {
        $tree = Multilevel::buildTree(null, Category::class);

        $this->assertCount(2, $tree);
        $this->assertSame(1, $tree[0]['id']);
        $this->assertSame('Test 1', $tree[0]['title']);
        $this->assertInstanceOf(Category::class, $tree[0]['node']);
        $this->assertSame(2, $tree[0]['children'][0]['id']);
        $this->assertSame(3, $tree[0]['children'][0]['children'][0]['id']);
        $this->assertSame(6, $tree[0]['children'][0]['children'][0]['children'][0]['id']);
        $this->assertSame(5, $tree[0]['children'][1]['id']);
        $this->assertSame(4, $tree[1]['id']);
        $this->assertSame([], $tree[1]['children']);
    }

    public function testUnknownParentsAreSkipped(): void
    {
        $items = Multilevel::makeDropDown([999], Category::class);

        $this->assertSame([0 => '-- ROOT --'], $items);
    }

    public function testInvalidModelIsRejected(): void
    {
        $this->expectException(InvalidConfigException::class);

        Multilevel::makeDropDown(null, \stdClass::class);
    }
}

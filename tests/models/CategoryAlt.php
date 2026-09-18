<?php

declare(strict_types=1);

namespace tests\models;

use yii\db\ActiveRecord;

/**
 * Category fixture model using custom attribute names (parent_id / name),
 * used to prove the attribute names are fully configurable.
 */
class CategoryAlt extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%category_alt}}';
    }
}

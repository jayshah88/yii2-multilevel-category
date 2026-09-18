<?php

declare(strict_types=1);

namespace tests\models;

use yii\db\ActiveRecord;

/**
 * Category fixture model using the default attribute names (id / root / title).
 */
class Category extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%category}}';
    }
}

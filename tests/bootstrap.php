<?php
/**
 * PHPUnit bootstrap for jayshah88/yii2-multilevel-category.
 *
 * Creates a minimal console application backed by an in-memory SQLite
 * database so the active record fixtures run without any external service.
 */

declare(strict_types=1);

defined('YII_DEBUG') || define('YII_DEBUG', true);
defined('YII_ENV') || define('YII_ENV', 'test');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

new \yii\console\Application([
    'id' => 'yii2-multilevel-category-tests',
    'basePath' => __DIR__,
    'components' => [
        'db' => [
            'class' => \tests\support\CountingConnection::class,
            'dsn' => 'sqlite::memory:',
        ],
    ],
]);

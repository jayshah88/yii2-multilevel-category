<?php

declare(strict_types=1);

namespace tests\support;

use yii\db\Connection;

/**
 * Connection that counts the SELECT statements created through it, so the
 * tests can verify the extension builds the whole tree with a single query.
 */
class CountingConnection extends Connection
{
    /**
     * @var int number of SELECT statements created through this connection.
     */
    public static int $selectCount = 0;

    public function createCommand($sql = null, $params = [])
    {
        if (is_string($sql) && stripos(ltrim($sql), 'select') === 0) {
            self::$selectCount++;
        }

        return parent::createCommand($sql, $params);
    }
}

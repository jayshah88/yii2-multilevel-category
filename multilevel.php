<?php
/**
 * Deprecated backward-compatibility shim for jayshah88/yii2-multilevel-category.
 *
 * Since 2.0.0 the class lives in `src/Multilevel.php` and is loaded through
 * Composer's PSR-4 autoloader:
 *
 * ```php
 * use Jay\Multilevel;
 * ```
 *
 * Requiring this file manually keeps very old integrations working, but the
 * Composer autoloader is the recommended way to load the class.
 *
 * @deprecated since 2.0.0. Use the Composer autoloader instead.
 * @author Jay Shah <shahjay88@gmail.com>
 */

if (!class_exists('Jay\\Multilevel', false)) {
    require_once __DIR__ . '/src/Multilevel.php';
}


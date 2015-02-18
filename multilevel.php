<?php

/**
 * File multilevel.php.
 *
 * @author Anish Madhu <anish.adm.madhu@gmail.com>
 * @see https://github.com/paypal/rest-api-sdk-php/blob/master/sample/
 * @see https://developer.paypal.com/webapps/developer/applications/accounts
 */

namespace Jay;

use yii\base\Component;

/**
 * Class Multilevel.
 *
 * @package Jay
 * @author Jay Shah <shahjaya.94@gmail.com>
 */
class Multilevel extends Component {

    public static function makeDropDown($parents, $model) {
        global $data;
        $data = array();
        $data['0'] = '-- ROOT --';
        foreach ($parents as $parent) {
            $data[$parent->id] = $parent->title;
            self::subDropDown($parent->id, $space = '---', $model);
        }
        return $data;
    }

    public static function subDropDown($children, $space = '---', $model) {
        global $data;
        $childrens = $model->findAll(['root' => $children]);
        foreach ($childrens as $child) {
            $data[$child->id] = $space . $child->title;
            self::subDropDown($child->id, $space . '---', $model);
        }
    }

}

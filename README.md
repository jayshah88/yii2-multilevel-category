yii2-multilevel-category extension for the Yii2
===========

yii2-multilevel-category allows user to create multilevel dropdown from their desire table for the Yii2.

Installation
====

Add to the composer.json file following section:

```
"jayshah88/yii2-multilevel-category": "dev-master"
```

Usage
====
add following line at the top of your php file.

```
use jay\Multilevel;
```

Example of usage
====

```
$cat = new common\models\base\Category();
$ml = new Multilevel();
echo $form->field($model, 'root')->dropDownList($ml->makeDropDown($parents,$cat));
```
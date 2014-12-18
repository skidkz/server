<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\CouponSearch */
/* @var $form yii\widgets\ActiveForm */

use yii\helpers\ArrayHelper;
use app\models\City;

?>

<div class="coupon-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?php // echo $form->field($model, 'id') ?>

    <?php // echo $form->field($model, 'sourceServiceId') ?>

    <?php echo $form->field($model, 'cityId')->dropDownList(ArrayHelper::map(City::find()->all(), 'id', 'cityName')) ?>
    <?php echo $form->field($model, 'fullTextStr') ?>

    <?php // echo $form->field($model, 'createTimestamp') ?>

    <?php // echo $form->field($model, 'lastUpdateDateTime') ?>

    <?php // echo $form->field($model, 'recordHash') ?>

    <?php // echo $form->field($model, 'title') ?>

    <?php // echo $form->field($model, 'shortDescription') ?>

    <?php // echo $form->field($model, 'longDescription') ?>

    <?php // echo $form->field($model, 'conditions') ?>

    <?php // echo $form->field($model, 'features') ?>

    <?php // echo $form->field($model, 'imagesLinks') ?>

    <?php // echo $form->field($model, 'timeToCompletion') ?>

    <?php // echo $form->field($model, 'mainImageLink') ?>

    <?php // echo $form->field($model, 'originalPrice') ?>

    <?php // echo $form->field($model, 'discountPercent') ?>

    <?php // echo $form->field($model, 'discountPrice') ?>

    <?php // echo $form->field($model, 'boughtCount') ?>

    <?php // echo $form->field($model, 'sourceServiceCategories') ?>

    <?php // echo $form->field($model, 'pageLink') ?>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('app', 'Поиск'), ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton(Yii::t('app', 'Сброс'), ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php
/**
 * Created by PhpStorm.
 * User: Виталий
 * Date: 09.12.2014
 * Time: 19:35
 */

use yii\helpers\Html;
use yii\db\Query;
use app\components\Tools;
use yii\helpers\Url;

$query = new Query;
$serviceBaseUrl = $query->select('serviceBaseUrl')
    ->from('sourceService')
    ->where('id=:id', [':id' => $model->sourceServiceId])
    ->createCommand()
    ->queryScalar();

$serviceName = $query->select('serviceName')
    ->from('sourceService')
    ->where('id=:id', [':id' => $model->sourceServiceId])
    ->createCommand()
    ->queryScalar();

$cityName = $query->select('cityName')
    ->from('city')
    ->where('id=:id', [':id' => $model->cityId])
    ->createCommand()
    ->queryScalar();

$phpDate = strtotime( $model->createTimestamp );
$createDate = date( 'd.m.y', $phpDate );

$strippedBoughtCount = $model->boughtCount;
$strippedBoughtCount = trim(str_replace("Уже купили", "", $strippedBoughtCount));
$strippedBoughtCount = trim(str_replace("человек", "", $strippedBoughtCount));

$originalCouponPrice = $model->originalCouponPrice;
if (trim($originalCouponPrice) == "") { $originalCouponPrice = "?"; }

$originalPrice = $model->originalPrice;
if (trim($originalPrice) == "") { $originalPrice = "?"; }

$discountPrice = $model->discountPrice;
if (trim($discountPrice) == "") { $discountPrice = "?"; }

$discountPercent = $model->discountPercent;
$discountPercent = str_replace('—', '-', $discountPercent);
if (trim($discountPercent) == "") { $discountPercent = "?"; }

//Дополнительная пост обработка и актуализация данных в БД.
if ($strippedBoughtCount != $model->boughtCount) {
    $model->boughtCount = $strippedBoughtCount;
    $model->save();
}

$certText = '. Сертификат: ';
if (\Yii::$app->devicedetect->isMobile()) {
    if (\Yii::$app->devicedetect->isTablet()) {

    } else {
        $certText = '. Серт.: ';
    }
}

?>

    

    <div itemscope itemtype="http://schema.org/Product" class="thumbnail image-ratio-base" style="background-image:url('/img/skid_bg_2.jpg'); background-size: cover;">
        <div class="image-ratio" itemprop="image" style="background-image:url('<?= (substr_count($model->mainImageLink, 'http') > 0 ? ($model->mainImageLink) :($serviceBaseUrl . '/' . $model->mainImageLink)); ?>')">
            <span itemprop="brand" itemscope itemtype="http://schema.org/Brand"><span itemprop="name" class="label label-info span-right"><?= $serviceName . '<br/>' . $cityName; ?></span></span>
            <span itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating"><!-- <span itemprop="ratingCount" >100</span> --><span class="label label-warning span-left "><?= 'Купили: <!-- <span itemprop="ratingValue" >-->' . ($strippedBoughtCount > '' ? $strippedBoughtCount : '?') . '<!-- </span>--><br/> ' . $createDate; ?></span>
            <div class="coupon-content" style="display:block">
                <p class="coupon-caption"><span itemprop="name"><?= Html::encode($model->title) ?></span><br/></p>
                <div itemprop="description" class="coupon-description">
                    <?= Html::encode($model->shortDescription) ?>
                    <div class="span-bottomright">
                        <a target="_BLANK" hreflang="ru" href="<?= Url::toRoute(['view', 'id' => $model->id]); ?>" class="btn btn-success btn-sm">i</a>

                        <?php if (Yii::$app->controller->action->id == 'actual'): ?>
                            <a hreflang="ru" target="_BLANK" href="<?php 
                            if(Tools::startsWith($model->pageLink, 'http://') || Tools::startsWith($model->pageLink, 'https://')) {
                                echo $model->pageLink;
                            } else {
                                echo $serviceBaseUrl . $model->pageLink;
                            }
                            ?>" class="btn btn-info btn-sm">Купить</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <span class="label label-success span-full-down">
                    <?= ((($model->discountType == 'coupon') || ($model->discountType == 'freeCoupon') )
                        ? 'Купон: ' . Html::encode($originalCouponPrice)
                        : 'Цена: ' . (Html::encode($originalPrice) . $certText . Html::encode($discountPrice))) 
                    . '. Скидка: ' . str_replace('%', '', Html::encode($discountPercent)) . '%'
                    ?>
            </span>
        </div>
    </div>
<!--    <?//= Html::a(Html::encode($model->title), ['view', 'id' => $model->id]) ?>-->

<!--    <div class="col-md-6">-->
<!--        <img src="http://placekitten.com/300/300" class="img-responsive portfolio_frontpage" alt="">-->
<!--        <div class="portfolio_description">-->
<!--            <h2>Heading</h2>-->
<!---->
<!--            <p>Some random tekst blablabla</p> <span class="read_more"></span>-->
<!---->
<!--        </div>-->
<!--    </div>-->

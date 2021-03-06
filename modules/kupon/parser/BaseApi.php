<?php

namespace app\modules\kupon\parser;

use app\models\Coupon;
use app\models\Statistics;
use SleepingOwl\Apist\Apist;
use app\components\Tools;
use yii\db\Query;
use yii\db\QueryBuilder;


abstract class BaseApi extends Apist
{
    abstract protected function cities();
    abstract protected function categories();
    abstract protected function couponsByCityId($cityId);
    abstract protected function couponAdvancedById($couponId);

    public function testConnection()
    {
        //\Yii::info('run testCities '.get_class($this), 'kupon');
        $cities = $this->cities();
        //\Yii::info(serialize($cities), 'kupon');

        if (isset($cities['error'])) {
            if (isset($cities['error']['status'])) {
                echo "Error: " . $cities['error']['reason'];
                return false;
            }
        } else {
            return true;
        }
    }

    public function testCities()
    {
        \Yii::info('run testCities '.get_class($this), 'kupon');
        $cities = $this->cities();
        \Yii::info(serialize($cities), 'kupon');
        Tools::print_array('Cities', $cities);
    }

    public function testCategories()
    {
        \Yii::info('run testCategories '.get_class($this), 'kupon');
        $categories = $this->categories();
        \Yii::info(serialize($categories), 'kupon');
        Tools::print_array('Categories', $categories);
    }

    public function testCoupons($cityId, $write = false)
    {
        \Yii::info('run testCoupons '.get_class($this), 'kupon');
        $coupons = $this->couponsByCityId($cityId);
        \Yii::info(serialize($coupons), 'kupon');
        Tools::print_array('Coupons', $coupons);

        if ($write) {
            $this->fetchKuponsByCityId($cityId);
        }
    }

    public function testAdvancedCoupon($couponId, $runUpdate = false)
    {
        \Yii::info('run testAdvancedCoupon '.get_class($this), 'kupon');
        $advancedCoupon = $this->couponAdvancedById($couponId);
        \Yii::info(serialize($advancedCoupon), 'kupon');
        Tools::print_array('Advanced coupon', $advancedCoupon);
        if ($runUpdate) $this->updateCouponById($couponId);
    }

    public function getBaseUrl()
    {
        return '';
    }

    protected function getSourceServiceCode()
    {
        return '';
    }

    protected function getCountryName()
    {
        return '';
    }


    protected function getCountryCode()
    {
        return '';
    }

    protected function getCountryId()
    {
        return \Yii::$app->db->createCommand('SELECT id FROM country WHERE countryCode=\''.$this->getCountryCode().'\'')->queryScalar();
    }

    public function getSourceServiceId()
    {
        return \Yii::$app->db->createCommand('SELECT id FROM sourceService WHERE serviceCode=\''.$this->getSourceServiceCode().'\'')->queryScalar();
    }

    protected function getSourceServiceName()
    {
        return '';
    }

    public function fetchAllCities()
    {
        \Yii::info('run fetchAllCities '.get_class($this), 'kupon');
        $query = new Query;
        $res = $query->select('cityId')
            ->from('cityUrl')
            ->where('sourceServiceId=:sourceServiceId',
                [
                    ':sourceServiceId' => $this->getSourceServiceId()
                ]
            )
            ->createCommand()
            ->queryColumn();

        foreach($res as $key => $value) {
            $this->fetchKuponsByCityId($value);
        }
    }

    public function initData()
    {
        //first we need to check all parameters and if they wrong - exit from program
        \Yii::info('run initData '.get_class($this), 'kupon');
		$baseUrl = $this->getBaseUrl();
		$sourceServiceCode = $this->getSourceServiceCode();
		$sourceServiceName = $this->getSourceServiceName();
		$countryName = $this->getCountryName();
		$countryCode = $this->getCountryCode();
        if (
            empty($baseUrl) 
            || empty($sourceServiceCode) 
            || empty($sourceServiceName)
            || empty($countryName)
            || empty($countryCode)
        ) {
            throw new \yii\web\HttpException(400, 'empty parameters', 405);
            return;
        }

        //Than - we checking last data init call. Recall only after 4 hours!
        $query = new Query;
        $res = $query->select('lastUpdateDateTime')
            ->from('sourceService')
            ->where('id=:sourceServiceId',
                [
                    ':sourceServiceId' => $this->getSourceServiceId(),
                ]
            )
            ->createCommand()
            ->queryScalar();

        if ( !is_null ($res) ) {
            $time = time();
            $diff = $time - strtotime ($res);
            //update every 4 hours (14400 unix seconds)
            if ($diff <= 14400) {
                return;
            }
        }

        //check source service is alredy available in database
        $connection=\Yii::$app->db;
        $query = new Query;
        $res = $query->select('id')
            ->from('sourceService')
            ->where('serviceCode=:serviceCode', [':serviceCode' => $this->getSourceServiceCode()])
            ->createCommand()
            ->queryScalar();

        //if service does not exists - we must to add it
        if (empty($res)) {
            $connection->createCommand()->insert('sourceService', [
                'serviceName' => $this->getSourceServiceName(),
                'serviceCode' => $this->getSourceServiceCode(),
                'serviceBaseUrl' => $this->getBaseUrl(),
            ])->execute();
        }

        //check service country
        $connection=\Yii::$app->db;
        $query = new Query;
        $res = $query->select('id')
            ->from('country')
            ->where('countryCode=:countryCode', [':countryCode' => $this->getCountryCode()])
            ->createCommand()
            ->queryScalar();

        //if country does not exists - we add it
        if (empty($res)) {
            $connection->createCommand()->insert('country', [
                'countryName' => $this->getCountryName(),
                'countryCode' => $this->getCountryCode(),
            ])->execute();
        }

        //parse and fill cities table
        //this function scan and add all new cities from this services
        $this->fillInCityTable();
        
        //parse and fill categories table
        //this function auto detect new categories for each service and add them to database
        $this->fillInCategoriesTable();

        //update lastUpdateDateTime in sourceService
        $connection->createCommand()->update('sourceService', [
            'lastUpdateDateTime' => date('Y.m.d H:i:s', time()),
        ],['id' => $this->getSourceServiceId()])->execute();
    }

    private function fillInCityTable()
    {
        //get allcities from target service
        \Yii::info('run fillInCityTable '.get_class($this), 'kupon');
        $cities = $this->cities()['cities'];
        \Yii::info(serialize($cities), 'kupon');

        $connection=\Yii::$app->db;

        //step by step for each city we check exists it in database or not. if exists - check for link changed.
        //If not exists - add them to database
        foreach ($cities as $key => $value) {

            //city exists?
            $query = new Query;
            $res = $query->select('id')
                ->from('city')
                ->where('cityCode=:cityCode', [':cityCode' => Tools::ru2lat($value['city'])])
                ->createCommand()
                ->queryScalar();

            //add if not exists
            if (empty($res)) {
                $connection->createCommand()->insert('city', [
                    'cityName' => $value['city'],
                    'cityCode' => Tools::ru2lat($value['city']),
                    'countryId' => $this->getCountryId(),
                ])->execute();
            }

            //then - check cityUrl for current service
            $cityId = $connection->createCommand('SELECT id FROM city WHERE cityCode=\''.Tools::ru2lat($value['city']).'\'')->queryScalar();

            //cityUrl exists?
            $query = new Query;
            $res = $query->select('id')
                ->from('cityUrl')
                ->where('cityId=:cityId AND sourceServiceId=:sourceServiceId', [':cityId' => $cityId, ':sourceServiceId' => $this->getSourceServiceId()])
                ->createCommand()
                ->queryScalar();

            //if not - add them. If yes - check for link or path changed and update it!!!
            if (empty($res)) {
                $connection->createCommand()->insert('cityUrl', [
                    'cityId' => $cityId,
                    'url' => ($value['link'] == '#' ? '/' : $value['link']),
                    'path' => ($value['path'] == '#' ? '/' : $value['path']),
                    'sourceServiceId' => $this->getSourceServiceId(),
                ])->execute();
            } else {
                $cityUrlRow = $query->select('id, url, path')
                ->from('cityUrl')
                ->where('cityId=:cityId AND sourceServiceId=:sourceServiceId', [':cityId' => $cityId, ':sourceServiceId' => $this->getSourceServiceId()])
                ->createCommand()
                ->queryOne();
                
                if (($cityUrlRow['url'] != $value['link']) || ($cityUrlRow['path'] != $value['path']) ) {
                    $connection->createCommand()->update('cityUrl', 
                    [
                        'url'=> $value['link'],
                        'path'=> $value['path'],
                    ],
                    'id=:id',
                    [':id'=>$cityUrlRow['id']])->execute();
                }
            }
        }
    }

    private function fillInCategoriesTable()
    {
        \Yii::info('run fillInCityTable '.get_class($this), 'kupon');
        $categories = $this->categories()['categories'];
        \Yii::info(serialize($categories), 'kupon');

        $connection=\Yii::$app->db;

        foreach ($categories as $key => $value) {

            //category exists?
            $query = new Query;
            $res = $query->select('id')
                ->from('categories')
                ->where('categoryCode=:categoryCode AND sourceServiceId=:sourceServiceId', 
                    [
                    ':categoryCode' => Tools::ru2lat($value['categoryName']), 
                    ':sourceServiceId' => $this->getSourceServiceId()
                    ]
                )
                ->createCommand()
                ->queryScalar();

            //if not - add them. If yes - check for categoryAdditionalInfo changed and update it!!!
            if (empty($res)) {
                $connection->createCommand()->insert('categories', [
                    'sourceServiceId' => $this->getSourceServiceId(),
                    'categoryName' => $value['categoryName'],
                    'categoryCode' => Tools::ru2lat($value['categoryName']),
                    'categoryIdentifier' => $value['categoryId'],
                    'parentCategoryIdentifier' => $value['parentCategoryId'],
                    'categoryAdditionalInfo' => $value['categoryAdditionalInfo'],
                ])->execute();
            } else {
                $categoryRow = $query->select('id, categoryAdditionalInfo')
                ->from('categories')
                ->where('categoryCode=:categoryCode AND sourceServiceId=:sourceServiceId', 
                    [':categoryCode' => Tools::ru2lat($value['categoryName']), 
                    //':categoryIdentifier' => $value['categoryId'],
                    ':sourceServiceId' => $this->getSourceServiceId()])
                ->createCommand()
                ->queryOne();
                
                if ( $categoryRow['categoryAdditionalInfo'] != $value['categoryAdditionalInfo'] ) {
                    $connection->createCommand()->update('categories', 
                    [
                        'categoryAdditionalInfo'=> $value['categoryAdditionalInfo'],
                    ],
                    'id=:id',
                    [':id'=>$categoryRow['id']])->execute();
                }
                //echo Tools::ru2lat($value['categoryName']) . '<br/>';
            }
        }
    }

    private function fetchKuponsByCityId($cityId)
    {
        \Yii::info('run fetchKuponsByCityId '. $cityId . ' ' .get_class($this), 'kupon');

        $connection=\Yii::$app->db;

        $query = new Query;
        $res = $query->select('lastUpdateDateTime')
            ->from('cityUrl')
            ->where('cityId=:cityId AND sourceServiceId=:sourceServiceId',
                [
                    ':cityId' => $cityId,
                    ':sourceServiceId' => $this->getSourceServiceId(),
                ]
            )
            ->createCommand()
            ->queryScalar();

        if ( !is_null ($res) ) {
            $time = time();
            $diff = $time - strtotime ($res);
            //update every 4 hours (14400 unix seconds)
            if ($diff <= 14400) {
                return;
            }
        }
        $newKuponsCount = 0;

        $result = $this->couponsByCityId($cityId);
        $cityCode = $result['cityCode'];
        $kupons = $result['coupons'];

        foreach ($kupons as $key => $value) {
            \Yii::info(serialize($value), 'kupon');
            $recordHashSrc = $cityCode.$value['sourceServiceId'].$value['pageLink'];
            $recordHash = md5($recordHashSrc);

            $query = new Query;
            $res = $query->select('id')
                ->from('coupon')
                ->where('recordHash=:recordHash',
                    [
                        ':recordHash' => $recordHash,
                    ]
                )
                ->createCommand()
                ->queryScalar();
            if (empty($res)) {
                $connection->createCommand()->insert('coupon', [
                    'sourceServiceId' => $this->getSourceServiceId(),
                    'cityId' => $cityId,
                    'lastUpdateDateTime' => '0000-00-00 00:00:00',
                    'createTimestamp' => date('Y.m.d H:i:s', time()),

                    'recordHash' => $recordHash,

                    'title' => $value['title'],
                    'shortDescription' => $value['shortDescription'],
                    'longDescription' => $value['longDescription'],
                    'conditions' => $value['conditions'],
                    'features' => $value['features'],
                    'timeToCompletion' => $value['timeToCompletion'],

                    'originalCouponPrice' => $value['originalCouponPrice'],
					'originalPrice' => $value['originalPrice'],
                    'discountPercent' => ($value['discountPercent'] > '' ? $value['discountPercent'] : '0%'),
                    'discountPrice' => $value['discountPrice'],

					'discountType' => ( ( isset($value['discountType']) && ($value['discountType'] > ''))
                        ? $value['discountType']
                        : ((($value['originalPrice'] > '') && ($value['discountPrice'] > ''))
                            ? 'full'
                            : ($value['originalCouponPrice'] > ''
                                ? ($value['originalCouponPrice'] == '0'
                                    ? 'freeCoupon'
                                    : 'coupon')
                                : 'undefined' ))),
					
                    'boughtCount' => $value['boughtCount'],
                    'sourceServiceCategories' => $value['sourceServiceCategories'],
                    'imagesLinks' => $value['imagesLinks'],
                    'pageLink' => $value['pageLink'],
                    'mainImageLink' => $value['mainImageLink'],
                ])->execute();
                $newKuponsCount++;
            }
        }

        //update lastUpdateDateTime in cityUrl
        $connection->createCommand()->update('cityUrl', [
            'lastUpdateDateTime' => date('Y.m.d H:i:s', time()),
        ],['cityId' => $cityId, 'sourceServiceId' => $this->getSourceServiceId()])->execute();
        if ($newKuponsCount !== 0) {
            $this->createUpdateStatistics($this->getSourceServiceId(), static::getSourceServiceName(),
                'new', date('Y-m-d'), $newKuponsCount);
        }
    }

    private function createUpdateStatistics($sourceId, $sourceAlias, $codeType, $createDate, $countKupons)
    {
        $statistics = Statistics::find()->where('sourceId=:sId AND codeType=:cT AND createDate=:cD',[
            ':sId' => $sourceId,
            ':cT' => $codeType,
            ':cD' => $createDate
        ])->one();
        if ($statistics) {
            $count = (int)$statistics->count + $countKupons;
        } else {
            $statistics = new Statistics();
            $count = $countKupons;
        }
        $statistics->sourceId = $sourceId;
        $statistics->alias = $sourceAlias;
        $statistics->count = $count;
        $statistics->createDate = $createDate;
        $statistics->codeType = $codeType;
        $statistics->save();
    }

    public function updateAllCoupons()
    {
        \Yii::info('run updateAllCoupons '.get_class($this), 'kupon');

        $today = date_create(date('Y-m-d',time()));
        date_sub($today, date_interval_create_from_date_string('3 days'));

        $query = new Query;
        $res = $query->select('id')
            ->from('coupon')
            ->where('sourceServiceId=:sourceServiceId AND isArchive=:isArchive AND lastUpdateDateTime < :lastUpdateDateTime',
                [
                    ':sourceServiceId' => $this->getSourceServiceId(),
                    ':isArchive' => 0,
                    ':lastUpdateDateTime' => $today->format('Y-m-d H:i:s'),
                ]
            )
            ->orderBy('lastUpdateDateTime')
            ->limit(10) //only 5 coupons every cron call
            ->createCommand()
            ->queryColumn();

        foreach($res as $key => $value) {
            //sleep ( rand(1,2) );
            $this->updateCouponById($value);
        }
    }

    public function updateCouponById($couponId)
    {
        \Yii::info('run updateCouponById '. $couponId . ' ' .get_class($this), 'kupon');
        $connection=\Yii::$app->db;

        $query = new Query;
        $res = $query->select('lastUpdateDateTime')
            ->from('coupon')
            ->where('id=:couponId',
                [
                    ':couponId' => $couponId,
                ]
            )
            ->createCommand()
            ->queryScalar();

        //echo "Last update date time: " . $res;

        //в случае успешного обновления, мы считаем, что следующее обновление нужно выполнить не раньше чем через 4 часа.
        if ( !is_null ($res) ) {
            $time = time();
            $diff = $time - strtotime ($res);
            //update every 4 hours (14400 unix seconds)
            if ($diff <= 14400) {
                \Yii::info('skip update by time '. $couponId . ' ' .get_class($this), 'kupon');
                return;
            }
        }

        $result = $this->couponAdvancedById($couponId);

        if ($result == -1) {
            //TODO it means, the coupon update for this service is unavailable (e.g. AutoKupon.kz)
            return;
        }

        \Yii::info(serialize($result), 'kupon');

        //echo "try '" . trim($result['longDescription']) . "', '" . trim($result['features']) . "', '" . trim($result['conditions']) . "', '" . trim($result['boughtCount']) . "', '" . $couponId . "'!'";

        //Если данные пусты, то скорее всего запись обновить не удалось и она является архивной. Пробуем 5 раз для каждой записи. Если так и не получилось - архивируем запись.
        if (empty(trim($result['longDescription'])) && empty(trim($result['features']))
        && empty(trim($result['conditions'])) && empty(trim($result['boughtCount']))) {
            echo $couponId;

            //обновить одну запись пробуем максимум пять раз после чего считаем её архивной.
            $tryToUpdateCount = $query->select('tryToUpdateCount')
                ->from('coupon')
                ->where('id=:couponId',
                    [
                        ':couponId' => $couponId,
                    ]
                )
                ->createCommand()
                ->queryScalar();

            //echo $tryToUpdateCount;

            \Yii::info('tryToUpdateCoupon '. $couponId . ' ' .get_class($this) . ' UPDATE COUNT ' . $tryToUpdateCount, 'kupon');

            //если количество неудачныхз попыток обновления превысило 5 - переносим запись в архив.
            if ($tryToUpdateCount >= 5) {
                $connection->createCommand()->update('coupon', [
                    'isArchive' => 1,
                    'lastUpdateDateTime' => date('Y.m.d H:i:s', time()),
                ], ['id' => $couponId])->execute();
                //и прибавляем количество архивных записей
                $this->createUpdateStatistics($this->getSourceServiceId(), static::getSourceServiceName(),
                    'archive', date('Y-m-d'), 1);
            } else {
                $connection->createCommand()->update('coupon', [
                    'tryToUpdateCount' => $tryToUpdateCount + 1,
                ], ['id' => $couponId])->execute();
            }

            return;
        } else {
            
            \Yii::info('tryToUpdateCoupon '. $couponId . ' ' .get_class($this) . ' UPDATE COMPLETED!', 'kupon');

            //если нам удалось успешно обновить запись - обнуляем количество неудачных попыток обновления и заносим в запись новую инфомрацию
            $connection->createCommand()->update('coupon', [
                'lastUpdateDateTime' => date('Y.m.d H:i:s', time()),
                'longDescription' => $result['longDescription'],
                'discountPrice' => $result['discountPrice'],
                'conditions' => $result['conditions'],
                'features' => $result['features'],
                'timeToCompletion' => $result['timeToCompletion'],
                'boughtCount' => $result['boughtCount'],
                'imagesLinks' => implode(', ', $result['imageLinks']),
                'tryToUpdateCount' => 0,
            ], ['id' => $couponId])->execute();

            //Если на странице записи мы нашли текст сообщающий о том что акция официально считается завершённой - переносим её в архив
            if ($result['isOfficialCompleted']) {
                $connection->createCommand()->update('coupon', [
                    'isArchive' => 1,
                ], ['id' => $couponId])->execute();
                //также добавляем в архивную статистику
                $this->createUpdateStatistics($this->getSourceServiceId(), static::getSourceServiceName(),
                    'archive', date('Y-m-d'), 1);
            }

            //$coupon = Coupon::findOne($couponId);
        }
    }
    
    public static function getApiObject($serviceId) {
        $api = NULL;
        switch ($serviceId) {
            case 1:
                $api = new ChocolifeApi();
                break;
            case 2:
                $api = new BlizzardApi();
                break;
            case 3:
                $api = new KupiKuponApi();
                break;
            case 4:
                $api = new MirKuponovApi();
                break;
            case 5:
                $api = new AutoKuponApi();
                break;
            case 6:
            case 8:
                $api = new BeSmartApi();
                break;
            default:
                echo "Not found!";
                return;
        }
        return $api;
    }
}

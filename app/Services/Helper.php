<?php
namespace App\Services;
use Illuminate\Support\Collection;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/10/31
 * Time: 12:39
 */
class Helper
{
    //计算两个坐标之间的距离
    public static function getDistance($lat1, $lng1, $lat2, $lng2){

        //将角度转为狐度
        $radLat1=deg2rad($lat1);//deg2rad()函数将角度转换为弧度

        $radLat2=deg2rad($lat2);

        $radLng1=deg2rad($lng1);

        $radLng2=deg2rad($lng2);

        $a=$radLat1-$radLat2;

        $b=$radLng1-$radLng2;

        $s=2*asin(sqrt(pow(sin($a/2),2)+cos($radLat1)*cos($radLat2)*pow(sin($b/2),2)))*6378.137;

        return $s;
    }

    //取出Eloqument中的两个值作为键值对
    public static function transformToKeyValue(Collection $collection, $key, $value)
    {
        $configCollection = collect($collection);
        $keys = $configCollection->pluck($key)->toArray();
        $values = $configCollection->pluck($value)->toArray();
        $result =  array_combine($keys, $values);
        return $result;
    }


}
<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;
use App\Models\Region;
use App\Services\Helper;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2018/1/16
 * Time: 19:18
 */

class RegionController extends BaseController
{
    public function provinces()
    {
        $provinces = Region::where('PID','0' )->where('Id', '!=', 0)->get();

        $provinceArray = Helper::transformToKeyValue($provinces, 'Id', 'Name' );

        return self::success($provinceArray);
    }

    public function cities($provinceId)
    {
        $cities = Region::where('PID',$provinceId )->get();

        $cityArray = Helper::transformToKeyValue($cities, 'Id', 'Name' );

        return self::success($cityArray);
    }

    public function areas($areaId)
    {
        $areas = Region::where('PID',$areaId )->get();

        $areaArray = Helper::transformToKeyValue($areas, 'Id', 'Name' );

        return self::success($areaArray);
    }

}
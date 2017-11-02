<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;
use App\Models\AssignmentClassification;
use App\Models\GlobalConfig;
use App\Services\Helper;
use function foo\func;
use Illuminate\Http\Request;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/9/14
 * Time: 1:50
 */

class AssignmentController extends BaseController
{
    //输出所有的委托类目
    public function classifications()
    {
        $categories = collect(AssignmentClassification::all());
        return self::success($categories);
    }

    //发起委托
    public function publish(Request $request)
    {
        $user = $this->user;
        $inputs = $request->only('title', 'classification', 'introduction', 'province_id', 'city_id', 'area_id',
            'lng', 'lat', 'detail_address', 'reward', 'expired_at', 'deadline', 'status', 'comment');

    }
}
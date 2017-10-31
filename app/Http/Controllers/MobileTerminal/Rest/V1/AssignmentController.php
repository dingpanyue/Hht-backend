<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;
use App\Models\AssignmentClassification;

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
    public function assignment()
    {
        $user = $this->user;
        dd ($user);
    }
}
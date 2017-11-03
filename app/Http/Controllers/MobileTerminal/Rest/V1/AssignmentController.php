<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;
use App\Models\Assignment;
use App\Models\AssignmentClassification;
use App\Models\GlobalConfig;
use App\Services\AssignmentService;
use App\Services\Helper;
use Dotenv\Validator;
use Exception;
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
    protected $assignmentService;

    public function __construct(AssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;

        parent::__construct();
    }

    //输出所有的委托类目
    public function classifications()
    {
        $categories = collect(app('assignment_classifications'));
        return self::success($categories);
    }

    //获取所有委托列表
    /**
     * @param Request $request
     */
    public function index(Request $request)
    {
        $inputs = $request->only('classification', 'keyword', 'order_by', 'order');
        $assignments = $this->assignmentService->getList($inputs);
        return self::success($assignments);
    }

    //发起委托
    public function publish(Request $request)
    {
        //当前登陆用户
        $user = $this->user;
        //用户输入
        $inputs = $request->only('title', 'classification', 'introduction', 'province_id', 'city_id', 'area_id',
            'lng', 'lat', 'detail_address', 'reward', 'expired_at', 'deadline', 'comment');
        //保存在数据库中的全局配置
        $globalConfigs = app('global_configs');
        //分类数组(字符串)
        $classificationArray = collect(app('assignment_classifications'))->pluck('id')->toArray();
        $classificationString = implode(',', $classificationArray);

        //这两个字段是可以选择性的填写的
        foreach (['reward', 'deadline'] as $key) {
            if ($inputs[$key] == '')
            {
                unset($inputs[$key]);
            }
        }

        $validator =  $validator = app('validator')->make($inputs, [
            "title" => "required|max:{$globalConfigs['assignment_title_limit']}",
            "classification" => "required|integer|in:$classificationString",
            "province_id" => "required|integer",
            "city_id" => "required|integer",
            "area_id" => "required|integer",
            "lng" => "required|numeric|min:-180|max:180",
            "lat" => "required|numeric|min:-90|max:90",
            "detail_address" => "required",
            "reward" => "numeric|min:0",
            "expired_at" => "date|after:now",
        ], [
            "title.required" => "委托标题必须填写",
            "title.max" => "委托标题必须在{$globalConfigs['assignment_title_limit']}以内",
            "classification.required" => "委托分类必须填写",
            "classification.in" => "请选择正确的委托分类",
            "province_id.required"=> "省份必须选择",
            "province_id.integer" => "请选择正确的省份",
            "city_id.required" => "城市必须选择",
            "city_id.integer" => "请选择正确的城市",
            "area_id.required" => "区域信息必须选择",
            "area_id.integer" => "请先择正确的区域",
            "lng.required" => "经度必须选择",
            "lng.numeric" => "经度必须为数字类型",
            "lng.min" => "经度必须在-180到180之间",
            "lng.max" => "经度必须在-180到180之间",
            "lat.required" => "纬度必须选择",
            "lat.numeric" => "纬度必须为数字",
            "lat.min" => "纬度必须在-90到90之间",
            "lat.max" => "纬度必须在-90到90之间",
            "detail_address.required" => "详细地址必须填写",
            "reward.min" => "委托报酬必须大于0",
            "reward.numeric" => "委托报酬必须为数字",
            "expired_at.date" => "委托过期时间格式不正确",
            "expired_at.after" => "委托过期时间不合理"
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        try {
            $assignment = $this->assignmentService->create($user->id, $inputs);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return self::success($assignment);
    }

    //获取单个委托详情
    public function detail($assignmentId)
    {
        $assignment = $this->assignmentService->getAssignmentById($assignmentId);
        if (!$assignment) {
           return self::resourceNotFound();
        }
        return self::success($assignment);
    }

    //接受委托
    public function accept($assignmentId, Request $request)
    {
        $user = $this->user;
        $assignment = $this->assignmentService->getAssignmentById($assignmentId);
        if (!$assignment) {
            return self::resourceNotFound();
        }
        $inputs = $request->only('reward', 'deadline');

        //只有待接受状态的委托可以接受
        if (!($assignment->status == Assignment::STATUS_WAIT_ACCEPT && $assignment->expired_at > date('Y-m-d H:i:s'))) {
            return self::encodeResult(self::CODE_ASSIGNMENT_STATUS_NOT_ALLOWED, '该委托不允许这样操作');
        }

        //接下来分两种状况

        //第一种情况  用户发布的时候已经把reward报酬  和 deadline 期限填写完了
        if($assignment->reward && $assignment->deadline){
            $acceptedAssignment = $this->assignmentService->acceptAssignment($user->id, $assignment->id);
        } else {
            $reward = $deadline = null;
            if (!$assignment->reward) {
                if ($inputs['reward'] == '') {
                    return self::parametersIllegal('对方未设置报酬金额，请填写您的期望值后提交');
                }
                $reward = $inputs['reward'];
            }
            if (!$assignment->deadline) {
                if ($inputs['deadline'] == '') {
                    return self::parametersIllegal('对方未设置委托完成期限，请填写您的期望值后提交');
                }
                $deadline = $inputs['deadline'];
            }
            $acceptedAssignment = $this->assignmentService->acceptAssignment($user->id, $assignment->id, $reward, $deadline);
        }
        //todo 这里应该找acceptedAssignment的时候把assignment的属性也找出来返回
        return self::success($acceptedAssignment);
    }
}
<?php

namespace App\Http\Controllers\MobileTerminal\Rest\V1;

use App\Models\AcceptedAssignment;
use App\Models\Assignment;
use App\Models\AssignmentClassification;
use App\Models\GlobalConfig;
use App\Models\User;
use App\Models\UserTalent;
use App\Services\AcceptedAssignmentService;
use App\Services\AssignmentService;
use App\Services\Helper;
use App\Transformers\AcceptedAssignmentTransformer;
use App\Transformers\AssignmentTransformer;
use Dotenv\Validator;
use Exception;
use function foo\func;
use GatewayWorker\Lib\Gateway;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/9/14
 * Time: 1:50
 */
class AssignmentController extends BaseController
{
    protected $assignmentService;
    protected $acceptedAssignmentService;

    public function __construct(AssignmentService $assignmentService, AcceptedAssignmentService $acceptedAssignmentService)
    {
        $this->assignmentService = $assignmentService;

        $this->acceptedAssignmentService = $acceptedAssignmentService;

        parent::__construct();
    }

    //输出所有的委托类目
    public function classifications()
    {
        $categories = collect(app('assignment_classifications'));
        return self::success($categories);
    }

    //获取所有委托列表 near_by为true且包含lng，lat的时候，会得到5公里以内的

    /**
     * @param Request $request
     */
    public function index(Request $request)
    {
        $inputs = $request->only('classification', 'keyword', 'order_by', 'order', 'near_by', 'lng', 'lat');
        $assignments = $this->assignmentService->getList($inputs);
        $assignments = AssignmentTransformer::transformList($assignments);
        return self::success($assignments);
    }


    //发起委托
    public function publishAssignment(Request $request)
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


        $validator = $validator = app('validator')->make($inputs, [
            "title" => "required|max:{$globalConfigs['assignment_title_limit']}",
            "classification" => "required|integer|in:$classificationString",
            "province_id" => "required|integer",
            "city_id" => "required|integer",
            "area_id" => "required|integer",
            "lng" => "required|numeric|min:-180|max:180",
            "lat" => "required|numeric|min:-90|max:90",
            "detail_address" => "required",
            "reward" => "required|numeric|min:0",
            "expired_at" => "required|date|after:now",
            'deadline' => "required|date|after:now",
        ], [
            "title.required" => "委托标题必须填写",
            "title.max" => "委托标题必须在{$globalConfigs['assignment_title_limit']}以内",
            "classification.required" => "委托分类必须填写",
            "classification.in" => "请选择正确的委托分类",
            "province_id.required" => "省份必须选择",
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

        if ($inputs['deadline'] < $inputs['expired_at']) {
            return self::parametersIllegal("委托期限必须大于截止时间");
        }

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        try {
            $assignment = $this->assignmentService->create($user->id, $inputs);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        $user = new User();

        //get       第一次用laravel 1对多条件查询，和yii还有tp一点都不像、、、 玩不来，自己构建吧
        $recommendUsers = $user->join('user_talents', 'user_talents.user_id', 'users.id')
                               ->select('users.id', 'users.name', 'users.image')->where('users.id','!=', $user->id)
                               ->where('user_talents.classification', $assignment->classification)
                               ->limit(3)->get();

        $assignment->recommand_users = $recommendUsers;

        return self::success(AssignmentTransformer::transform($assignment));
    }

    //取消委托
    public function cancelAssignment($assignmentId)
    {
        $user = $this->user;

        /**
         * @var $assignment Assignment
         */
        $assignment = $this->assignmentService->getAssignmentById($assignmentId);

        if (!$assignment) {
            return self::resourceNotFound();
        }

        if ($assignment->user_id != $user->id) {
            return self::notAllowed('你不能取消不是自己发布的委托');
        }

        if ($assignment->status != Assignment::STATUS_UNPAID) {
            return self::notAllowed('当前委托不允许取消操作');
        }

        try {
            $this->assignmentService->cancelAssignment($assignment, $user->id);
        } catch (Exception $e) {
            return self::error($e->getCode(), $e->getMessage());
        }

        return self::success();
    }

    //获取单个委托详情
    public function detail($assignmentId)
    {
        /**
         * @var $assignment Assignment
         */
        $assignment = $this->assignmentService->getAssignmentById($assignmentId);

        if (!$assignment) {
            return self::resourceNotFound();
        }

        $operations = $this->assignmentService->getAssignmentOperationLog($assignment);
        $assignment->operations = $operations;

        return self::success(AssignmentTransformer::transform($assignment));
    }

    //接受委托 assign_user
    public function acceptAssignment($assignmentId, Request $request)
    {
        $user = $this->user;

        /**
         * @var $assignment Assignment
         */
        $assignment = $this->assignmentService->getAssignmentById($assignmentId);

        if (!$assignment) {
            return self::resourceNotFound();
        }

        //自己不能接受自己发布的委托
        if ($assignment->user_id == $user->id) {
            return self::notAllowed('你不能接受自己发布的委托');
        }

        //不能反复接同一个委托
        $acceptedAssignments = AcceptedAssignment::where('serve_user_id', $user->id)
            ->where('parent_id', $assignmentId)
            ->get();

        if (count($acceptedAssignments)) {
            return self::notAllowed('你不能重复接受该委托');
        }

        $inputs = $request->only('reward', 'deadline');

        //只有待接受状态的委托可以接受
        if (!($assignment->status == Assignment::STATUS_WAIT_ACCEPT && $assignment->expired_at > date('Y-m-d H:i:s'))) {
            return self::encodeResult(self::CODE_ASSIGNMENT_STATUS_NOT_ALLOWED, '该委托不允许这样操作');
        }

        //接下来分两种状况

        //第一种情况  用户发布的时候已经把reward报酬  和 deadline 期限填写完了
        if ($assignment->reward && $assignment->deadline) {
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
        return self::success(AcceptedAssignmentTransformer::transform($acceptedAssignment, false));
    }

    //采纳被接受的委托 assign_user
    public function adaptAcceptedAssignment($id)
    {
        $user = $this->user;
        /**
         * @var $acceptedAssignment AcceptedAssignment
         */
        $acceptedAssignment = $this->acceptedAssignmentService->getAcceptedAssignmentById($id);

        if (!$acceptedAssignment) {
            return self::resourceNotFound();
        }

        $assignmentId = $acceptedAssignment->parent_id;

        $adaptedAcceptedAssignments = $this->acceptedAssignmentService->getAcceptedAssignments(
            null, null, $assignmentId, AcceptedAssignment::STATUS_ADAPTED
        );

        //已经采纳过其他委托的情况
        if ($adaptedAcceptedAssignments->count() != 0) {
            return self::notAllowed();
        }

        if ($acceptedAssignment->status != AcceptedAssignment::STATUS_SUBMITTED) {
            return self::notAllowed();
        }

        if ($acceptedAssignment->assign_user_id != $user->id) {
            return self::notAllowed();
        } else {
            $acceptedAssignment = $this->assignmentService->adaptAcceptedAssignment($acceptedAssignment);
            return self::success(AcceptedAssignmentTransformer::transform($acceptedAssignment, false));
        }
    }

    //告知完成被接收的委托 serve_user
    public function dealAcceptedAssignment($id)
    {
        $user = $this->user;

        /**
         * @var $acceptedAssignment AcceptedAssignment
         */
        $acceptedAssignment = $this->acceptedAssignmentService->getAcceptedAssignmentById($id);

        if (!$acceptedAssignment) {
            return self::resourceNotFound();
        }

        if ($acceptedAssignment->status != AcceptedAssignment::STATUS_ADAPTED) {
            return self::notAllowed();
        }

        if ($acceptedAssignment->serve_user_id != $user->id) {
            return self::notAllowed();
        } else {
            $acceptedAssignment = $this->assignmentService->dealAcceptedAssignment($acceptedAssignment, $user->id);
            return self::success(AcceptedAssignmentTransformer::transform($acceptedAssignment, false));
        }
    }

    //拒绝完成 被告知完成的委托  assign_user
    public function refuseFinishingAcceptedAssignment($id)
    {
        $user = $this->user;

        /**
         * @var $acceptedAssignment AcceptedAssignment
         */
        $acceptedAssignment = $this->acceptedAssignmentService->getAcceptedAssignmentById($id);

        if (!$acceptedAssignment) {
            return self::resourceNotFound();
        }

        if ($acceptedAssignment->status != AcceptedAssignment::STATUS_DEALT) {
            return self::notAllowed();
        }

        if ($acceptedAssignment->assign_user_id != $user->id) {
            return self::notAllowed();
        } else {
            $acceptedAssignment = $this->assignmentService->refuseFinishingAcceptedAssignment($acceptedAssignment, $user->id);
            return self::success(AcceptedAssignmentTransformer::transform($acceptedAssignment, false));
        }

    }

    //确认委托成功 assign_user
    public function finishAcceptedAssignment($id)
    {
        $user = $this->user;

        /**
         * @var $acceptedAssignment AcceptedAssignment
         */
        $acceptedAssignment = $this->acceptedAssignmentService->getAcceptedAssignmentById($id);

        if (!$acceptedAssignment) {
            return self::resourceNotFound();
        }

        if ($acceptedAssignment->status != AcceptedAssignment::STATUS_ADAPTED && $acceptedAssignment->status != AcceptedAssignment::STATUS_DEALT) {
            return self::notAllowed('该委托的状态不允许您这么操作');
        }

        if ($acceptedAssignment->assign_user_id != $user->id) {
            return self::notAllowed('您不是该委托的发布人');
        } else {
            $acceptedAssignment = $this->assignmentService->finishAcceptedAssignment($acceptedAssignment, $user->id);
            return self::success(AcceptedAssignmentTransformer::transform($acceptedAssignment, false));
        }
    }

    //我发布的委托 请求参数中有 status，可以过滤状态 (用于我的  列表)
    public function myAssignments(Request $request)
    {
        $user = $this->user;

        $inputs = $request->all();

        if (isset($inputs['status'])) {
            $assignments = $this->assignmentService->getAssignmentsByUser($user, $inputs['status']);
        } else {
            $assignments = $this->assignmentService->getAssignmentsByUser($user);
        }

        return self::success(AssignmentTransformer::transformList($assignments));
    }

    //我作为服务方接受的委托     请求参数中有status,可以过滤状态 （用于我的  列表）
    public function myAcceptedAssignments(Request $request)
    {
        $user = $this->user;

        $inputs = $request->all();

        if (isset($inputs['status'])) {
            $acceptedAssignments = $this->acceptedAssignmentService->getAcceptedAssignmentsByUser($user, $inputs['status']);
        } else {
            $acceptedAssignments = $this->acceptedAssignmentService->getAcceptedAssignmentsByUser($user);
        }

        return self::success(AcceptedAssignmentTransformer::transformList($acceptedAssignments, false));
    }

    //上传图片
    public function upload($id, Request $request)
    {
        $user = $this->user;

        $assignment = $this->assignmentService->getAssignmentById($id);

        if (!$assignment) {
            return self::resourceNotFound();
        }

        if ($assignment->status != Assignment::STATUS_UNPAID && $assignment->status != Assignment::STATUS_WAIT_ACCEPT) {
            return self::notAllowed();
        }

        if (!$assignment->user_id == $user->id) {
            return self::notAllowed();
        }

        $inputs = $request->all();
        $imageArray = [];

        /**
         * @var $image UploadedFile
         */
        foreach ($inputs as $image) {

            $size = $image->getSize();
            //这里可根据配置文件的设置，做得更灵活一点
            if ($size > 2 * 1024 * 1024) {
                return self::parametersIllegal('上传文件不能超过2M');
            }
            //文件类型
            $mimeType = $image->getMimeType();

            //这里根据自己的需求进行修改
            if ($mimeType != 'image/png' && $mimeType != 'image/jpeg') {
                return self::parametersIllegal('只能上传png格式的图片');
            }
            //扩展文件名
            $ext = $image->getClientOriginalExtension();
            //判断文件是否是通过HTTP POST上传的
            $realPath = $image->getRealPath();

            if (!$realPath) {
                return self::notAllowed('非法操作');
            }

            //创建以当前日期命名的文件夹
            $today = date('Y-m-d');
            //storage_path().'/app/uploads/' 这里根据 /config/filesystems.php 文件里面的配置而定
            //$dir = str_replace('\\','/',storage_path().'/app/uploads/'.$today);
            $dir = storage_path() . '/app/public/images/assignments/' . $today;
            if (!is_dir($dir)) {
                mkdir($dir);
            }

            //上传文件
            $filename = uniqid() . '.' . $ext;//新文件名
            if (Storage::disk('public')->put('/images/assignments/' . $today . '/' . $filename, file_get_contents($realPath))) {
                $image = "/storage/images/assignments/$today/$filename";
                $imageArray[] = URL::asset($image);
            } else {
                return self::error(self::CODE_FAIL_TO_SAVE_IMAGE, "图片保存出错");
            }
        }

        $assignment->images = json_encode($imageArray);
        $assignment->save();

        return self::success($imageArray);
    }
}
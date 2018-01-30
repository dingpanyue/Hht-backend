<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;
/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/10
 * Time: 4:49
 */

use App\Models\AcceptedService;
use App\Models\Service;
use App\Services\AcceptedServiceService;
use App\Services\GatewayWorkerService;
use App\Services\ServiceService;
use App\Transformers\AcceptedServiceTransformer;
use App\Transformers\ServiceTransformer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ServiceController extends BaseController
{
    protected $serviceService;

    protected $acceptedServiceService;
    
    public function __construct(ServiceService $serviceService, AcceptedServiceService $acceptedServiceService)
    {
        $this->serviceService = $serviceService;

        $this->acceptedServiceService = $acceptedServiceService;
        
        parent::__construct();
    }

    //输出所有的服务类目
    public function classifications()
    {
        $categories = collect(app('assignment_classifications'));
        return self::success($categories);
    }

    //获取所有服务列表
    public function index(Request $request)
    {
        $inputs = $request->only('classification', 'keyword', 'order_by', 'order', 'near_by', 'lng', 'lat');
        $services = $this->serviceService->getList($inputs);
        return self::success(ServiceTransformer::transformList($services));
    }

    //获取单个service详情  主体是service
    public function detail($id)
    {
        $service = $this->serviceService->getServiceDetailById($id);
        return self::success(ServiceTransformer::transform($service));

    }

    //获取单个 acceptedService 详情  主体是acceptedService
    public function acceptedServiceDetail($id)
    {
        $acceptedService = $this->acceptedServiceService->getAcceptedServiceDetailById($id);
        return self::success(AcceptedServiceTransformer::transform($acceptedService));
    }

    //获取我发布的服务列表
    public function myServices(Request $request)
    {
        $user = $this->user;
        $inputs = $request->all();

        if (isset($inputs['status'])) {
            $services = $this->serviceService->getServicesByUser($user, $inputs['status']);
        } else {
            $services = $this->serviceService->getServicesByUser($user);
        }

        return self::success(ServiceTransformer::transformList($services));
    }

    //获取我作为委托人 购买或准备购买的服务 acceptedServices
    public function myAcceptedServices(Request $request)
    {
        $user = $this->user;
        $inputs = $request->all();

        if (isset($inputs['status'])) {
            $acceptedServices = $this->acceptedServiceService->getAcceptedServicesByUser($user, $inputs['status']);
        } else {
            $acceptedServices = $this->acceptedServiceService->getAcceptedServicesByUser($user);
        }

        return self::success(AcceptedServiceTransformer::transformList($acceptedServices, false));
    }

    //获取我的  被确认  或者购买了的所有服务列表
    public function myBoughtServices(Request $request)
    {
        $user = $this->user;
        $inputs = $request->all();

        if (isset($inputs['status'])) {
            $acceptedServices = $this->acceptedServiceService->getAcceptedServicesByServeUser($user, $inputs['status']);
        } else {
            $acceptedServices = $this->acceptedServiceService->getAcceptedServicesByServeUser($user);
        }
        return self::success(AcceptedServiceTransformer::transformList($acceptedServices, false));
    }

    //发布服务 服务没有deadline 由购买服务的人来决定（因为service好比队列任务，自己不应该决定什么时候完成，也就说会有单个服务很快能完成，但手头大量积压的情况）   reward根据情况可填也可不填
    public function publishService(Request $request)
    {
        //当前登陆用户
        $user = $this->user;
        //用户输入
        $inputs = $request->only('title', 'classification', 'introduction', 'province_id', 'city_id', 'area_id',
            'lng', 'lat', 'detail_address',  'expired_at', 'comment');
        //保存在数据库中的全局配置
        $globalConfigs = app('global_configs');
        //分类数组(字符串)
        $classificationArray = collect(app('assignment_classifications'))->pluck('id')->toArray();
        $classificationString = implode(',', $classificationArray);


        $validator = app('validator')->make($inputs, [
            "title" => "required|max:{$globalConfigs['assignment_title_limit']}",
            "classification" => "required|integer|in:$classificationString",
            "province_id" => "required|integer",
            "city_id" => "required|integer",
            "area_id" => "required|integer",
            "lng" => "required|numeric|min:-180|max:180",
            "lat" => "required|numeric|min:-90|max:90",
            "detail_address" => "required",
            "expired_at" => "date|after:now",
        ], [
            "title.required" => "服务标题必须填写",
            "title.max" => "服务标题必须在{$globalConfigs['assignment_title_limit']}以内",
            "classification.required" => "服务分类必须填写",
            "classification.in" => "请选择正确的服务分类",
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
            "reward.min" => "服务报酬必须大于0",
            "reward.numeric" => "服务报酬必须为数字",
            "expired_at.date" => "服务过期时间格式不正确",
            "expired_at.after" => "服务过期时间不合理"
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        try {
            $service = $this->serviceService->create($user->id, $inputs);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return self::success(ServiceTransformer::transform($service));
    }

    //购买服务
    public function buyService($serviceId, Request $request)
    {
        //当前登陆用户
        $user = $this->user;

        /**
         * @var $service Service
         */
        $service = $this->serviceService->getServiceById($serviceId);

        if (!$service) {
            return self::resourceNotFound();
        }

        if ($service->status != Service::STATUS_PUBLISHED) {
            return self::notAllowed('无效的服务');
        }

        //自己不能购买自己发布的服务
        if ($service->user_id == $user->id) {
            return self::notAllowed('你不能购买自己发布的服务');
        }

        //用户输入
        $inputs = $request->only('reward', 'deadline');

        $validator = app('validator')->make($inputs, [
            "reward" => "numeric|min:0",
            "deadline" => "date|after:now|required",
        ], [
            "reward.min" => "服务报酬必须大于0",
            "reward.numeric" => "服务报酬必须为数字",
            "deadline.date" => "服务期限格式不正确",
            "deadline.after" => "服务期限不合理",
            "deadline.required" => '服务期限必须填写'
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        /**
         * @var $service Service
         */

        if ($service->reward) {
            $reward = $service->reward;
        } else {
            if (!$inputs['reward']) {
                return self::parametersIllegal('由于该服务未填写报酬，您需要先填写报酬再尝试');
            }
            $reward = $inputs['reward'];
        }

        $deadline = $inputs['deadline'];

        if ($service->status != Service::STATUS_PUBLISHED) {
            return self::notAllowed('无效的服务');
        }

        $acceptedService= $this->serviceService->buyService($user->id, $service->id, $reward, $deadline);

        return self::success(AcceptedServiceTransformer::transform($acceptedService));
    }

    //同意 购买者 购买服务
    public function acceptBoughtService($acceptedServiceId)
    {
        $user = $this->user;
        /**
         * @var $acceptedService AcceptedService
         */
        $acceptedService = $this->acceptedServiceService->getAcceptedServiceById($acceptedServiceId);

        if (!$acceptedService) {
            return self::resourceNotFound();
        }

        if ($acceptedService->status != AcceptedService::STATUS_SUBMITTED) {
            return self::notAllowed();
        }

        if ($acceptedService->serve_user_id != $user->id) {
            return self::notAllowed("您不是该服务的发布人");
        } else {
            $acceptedService = $this->serviceService->acceptBoughtService($acceptedService);
            return self::success(AcceptedServiceTransformer::transform($acceptedService));
        }
    }

    //拒绝 购买者 购买服务
    public function refuseBoughtService($acceptedServiceId)
    {
        $user = $this->user;
        /**
         * @var $acceptedService AcceptedService
         */
        $acceptedService = $this->acceptedServiceService->getAcceptedServiceById($acceptedServiceId);

        if (!$acceptedService) {
            return self::resourceNotFound();
        }

        if ($acceptedService->status != AcceptedService::STATUS_SUBMITTED) {
            return self::notAllowed();
        }

        if ($acceptedService->serve_user_id != $user->id) {
            return self::notAllowed("您不是该服务的发布人");
        } else {
            $acceptedService = $this->serviceService->refuseBoughtService($acceptedService);
            return self::success(AcceptedServiceTransformer::transform($acceptedService));
        }
    }

    //告知完成被接收的委托 serve_user
    public function dealAcceptedService($acceptedServiceId)
    {
        $user = $this->user;

        /**
         * @var $acceptedService AcceptedService
         */
        $acceptedService = $this->acceptedServiceService->getAcceptedServiceById($acceptedServiceId);

        if (!$acceptedService) {
            return self::resourceNotFound();
        }

        if ($acceptedService->status != AcceptedService::STATUS_ADAPTED) {
            return self::notAllowed();
        }

        if ($acceptedService->serve_user_id != $user->id) {
            return self::notAllowed();
        } else {
            $acceptedService = $this->serviceService->dealAcceptedService($acceptedService);
            return self::success(AcceptedServiceTransformer::transform($acceptedService));
        }
    }

    //确认完成被接受的服务
    public function finishAcceptedService($acceptedServiceId)
    {
        $user = $this->user;

        /**
         * @var $acceptedService AcceptedService
         */
        $acceptedService = $this->acceptedServiceService->getAcceptedServiceById($acceptedServiceId);

        if (!$acceptedService) {
            return self::resourceNotFound();
        }

        if ($acceptedService->status != AcceptedService::STATUS_ADAPTED && $acceptedService->status != AcceptedService::STATUS_DEALT) {
            return self::notAllowed();
        }

        if ($acceptedService->assign_user_id != $user->id) {
            return self::notAllowed();
        } else {
            $acceptedService = $this->serviceService->finishAcceptedService($acceptedService);
            return self::success(AcceptedServiceTransformer::transform($acceptedService));
        }
    }

    //拒绝完成被接受的服务
    public function refuseToFinishAcceptedService($acceptedServiceId)
    {
        $user = $this->user;

        /**
         * @var $acceptedService AcceptedService
         */
        $acceptedService = $this->acceptedServiceService->getAcceptedServiceById($acceptedServiceId);

        if (!$acceptedService) {
            return self::resourceNotFound();
        }

        if ($acceptedService->status != AcceptedService::STATUS_DEALT) {
            return self::notAllowed();
        }

        if ($acceptedService->assign_user_id != $user->id) {
            return self::notAllowed();
        } else {
            $acceptedService = $this->serviceService->refuseToFinishAcceptedService($acceptedService);
            return self::success(AcceptedServiceTransformer::transform($acceptedService));
        }

    }

    //上传图片
    public function upload($id, Request $request)
    {
        $user = $this->user;

        $service = $this->serviceService->getServiceById($id);

        if(!$service) {
            return self::resourceNotFound();
        }

        if (!$service->user_id == $user->id) {
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
            $dir = storage_path() . '/app/public/images/services/' . $today;
            if (!is_dir($dir)) {
                mkdir($dir);
            }

            //上传文件
            $filename = uniqid() . '.' . $ext;//新文件名
            if (Storage::disk('public')->put('/images/services/' . $today . '/' . $filename, file_get_contents($realPath))) {
                $image = "/storage/images/services/$today/$filename";
                $imageArray[] = URL::asset($image);
            } else {
                return self::error(self::CODE_FAIL_TO_SAVE_IMAGE, "图片保存出错");
            }
        }

        $service->images = json_encode($imageArray);
        $service->save();

        return self::success($imageArray);
    }

    public function arbitratedServices()
    {
        $acceptedServices  = AcceptedService::where('status', AcceptedService::STATUS_ARBITRATED)->with('service')->get();
        return self::success(AcceptedServiceTransformer::transformList($acceptedServices));
    }
}
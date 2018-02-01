<?php

namespace App\Http\Controllers\MobileTerminal\Rest\V1;

use App\Models\Message;
use App\Models\User;
use App\Models\UserAccount;
use App\Models\UserAddress;
use App\Models\UserCenter;
use App\Models\UserInfo;
use App\Models\UserTalent;
use App\Services\AddressService;
use App\Services\GatewayWorkerService;
use App\Services\Helper;
use App\Traits\VerifyCardNo;
use GatewayWorker\Lib\Gateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class UserController extends BaseController
{
    use VerifyCardNo;

    protected $addressService;

    public function __construct(AddressService $addressService)
    {
        $this->addressService = $addressService;
        parent::__construct();
    }

    public function authentication(Request $request)
    {
        $user = $this->user;

        $validator = Validator::make($request->all(), [
            'real_name' => 'required',
            'card_no' => 'required',
            'mobile' => 'required',
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        $inputs = $request->only('real_name', 'card_no', 'mobile');

        //验证身份证号码合法性
        if (!$this->validation_filter_id_card($inputs['card_no'])) {
            return self::parametersIllegal('身份证号码不合法');
        }

        $userInfo = $user->userInfo;

        if (!$userInfo) {
            $userInfo = new UserInfo();
        } else {
            if ($userInfo->status == UserInfo::STATUS_AUTHENTICATED) {
                return self::notAllowed('您的身份已经认证过，无需再次认证');
            }
        }

        //未认证用户创建用户信息， 认证过但失败了的用户更新信息
        $userInfo->user_id = $user->id;
        $userInfo->real_name = $inputs['real_name'];
        $userInfo->card_no = $inputs['card_no'];
        $userInfo->status = UserInfo::STATUS_UNAUTHENTICATED;
        $userInfo->save();

        //todo 调用认证接口对用户进行认证 改变状态


        return self::success($user);
    }

    //绑定客户端id 和 用户id
    public function bindUserIdAndClientId($clientId)
    {
        $user = $this->user;

        Gateway::unbindUid($clientId, $user->id);
        Gateway::bindUid($clientId, $user->id);
        return self::success();
    }


    public function sendMessage($toUserId, Request $request)
    {
        $fromUserId = $this->user->id;
        $message = $request->get('message');
        GatewayWorkerService::sendMessageFromUser($message, $fromUserId, $toUserId);
        return self::success();
    }

    //获取用户
    public function info($id)
    {
        //获取用户
        $visitUser = $this->user;

        if ($id == 'self') {
            $user = $visitUser;
        } else {
            $user = User::find($id);
        }

        $userInfo = $user->userInfo;
        if (!$userInfo) {
            $userInfo = null;
        }

        $user->user_info = $userInfo;

        if ($id == 'self') {
            //获取余额
            $balance = $userInfo->balance;
            $user->balance = $balance;

            //所有未读消息 数量
            $Messages = Message::select('from_user_id', DB::raw('count(*) as total'))
                ->where('to_user_id', $user->id)->where('from_user_id', '!=', $user->id)
                ->where('status', '!=', Message::STATUS_SEEN)
                ->with('fromUser')
                ->groupBy('from_user_id')
                ->get();

            $user->messages = $Messages;

            $userAccount = $user->userAccount;
            $user->userccount = $userAccount;
        }

        return self::success($user);
    }

    public function offlineMessages()
    {
        $user = $this->user;

        $userModel = new User();
        $users = $userModel->select('users.id', 'users.name', 'users.image')->join('messages', 'to_user_id','id')->where('to_user_id', $user->id)->where('messages.status', Message::STATUS_UNSENT)->get();
        $messages = Message::where('to_user_id', $user->id)->where('status', Message::STATUS_UNSENT)->orderBy('from_user_id', 'asc')
            ->orderBy('created_at', 'asc')->get();

        return self::success([$users, $messages]);
    }

    public function offlineMessagesDealt()
    {
        $user = $this->user;

        Message::where('to_user_id', $user->id)->update(['status' => Message::STATUS_SENT]);

        return self::success();
    }


    //获取和其他某用的聊天记录
    public function getMessages($id, Request $request)
    {
        $user = $this->user;
        $inputs = $request->all();
        $perPage = $inputs['per_page'];

        if ($user->id == $id) {
            return self::parametersIllegal('您自己无法和自己聊天');
        }

        $userMessages = Message::where(function ($query) use ($user, $id) {
            $query->where(function ($query) use ($user, $id) {
                $query->where('from_user_id', $user->id)->where('to_user_id', $id);
            })->orWhere(function ($query) use ($user, $id) {
                $query->where('from_user_id', $id)->where('to_user_id', $user->id);
            });
        })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        Message::where('from_user_id', $id)->where('to_user_id', $user->id)->update(
            ['status' => Message::STATUS_SEEN]
        );

        return self::success($userMessages);
    }

    //用户添加地址
    public function addAddress(Request $request)
    {
        $user = $this->user;

        $input = $request->only('province_id', 'city_id', 'area_id', 'detail_address', 'mobile', 'receiver', 'postcode');
        $rules = [
            'province_id' => 'required|integer',
            'city_id' => 'required|integer',
            'area_id' => 'required|integer',
            'detail_address' => 'required',
            'receiver' => 'required',
            'mobile' => 'required|regex:/^1[34578][0-9]{9}$/'
        ];
        $messages = [
            'province_id.required' => '请选择省份',
            'province_id.integer' => '省份格式不正确',
            'city_id.required' => '请选择城市',
            'city_id.integer' => '城市格式不正确',
            'area_id.required' => '请选择地区',
            'area_id.integer' => '地区格式不正确',
            'detail_address.required' => '请填写详细地址',
            'receiver.required' => '请填写收货人',
            'mobile.required' => '请填写收货人电话号码',
            'mobile.regex' => '电话号码格式不正确'
        ];

        $validator = Validator::make($input, $rules, $messages);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        $address = $this->addressService->create($user->id, $input);
        $address->address = $this->addressService->getAddress($address);

        if ($address) {
            return self::success($address);
        } else {
            return self::error(self::CODE_FAIL_TO_CREATE_ADDRESS, '创建地址失败');
        }
    }

    //获取用户地址列表
    public function getUserAddresses()
    {
        $user = $this->user;

        $userAddress = new UserAddress();
        $addresses = $userAddress->where('user_id', $user->id)->get();

        foreach ($addresses as $k => $address) {
            $addresses[$k]->address = $this->addressService->getAddress($address);
        }

        return self::success($addresses);
    }

    public function setDefaultAddress($id)
    {
        $user = $this->user;

        $userAddress = new UserAddress();
        $address = $userAddress->find($id);
        if ($address->user_id != $user->id) {
            return self::notAllowed('该收货地址不属于您');
        } else {
            $userAddress->where('user_id', $user->id)->update(['is_default' => 0]);
            $userAddress->where('id', $id)->update(['is_default' => 1]);
            return self::success();
        }
    }

    public function upload(Request $request)
    {
        $all = $request->all();
        $rules = [
            'upFile' => 'required',
        ];
        $messages = [
            'upFile.required' => '请选择要上传的文件'
        ];
        $validator = Validator::make($all, $rules, $messages);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        //获取上传文件的大小
        $size = $request->file('upFile')->getSize();
        //这里可根据配置文件的设置，做得更灵活一点
        if ($size > 2 * 1024 * 1024) {
            return self::parametersIllegal('上传文件不能超过2M');
        }
        //文件类型
        $mimeType = $request->file('upFile')->getMimeType();

        //这里根据自己的需求进行修改
        if ($mimeType != 'image/png' && $mimeType != 'image/jpeg') {
            return self::parametersIllegal('只能上传png格式的图片');
        }
        //扩展文件名
        $ext = $request->file('upFile')->getClientOriginalExtension();
        //判断文件是否是通过HTTP POST上传的
        $realPath = $request->file('upFile')->getRealPath();

        if (!$realPath) {
            return self::notAllowed('非法操作');
        }

        //创建以当前日期命名的文件夹
        $today = date('Y-m-d');
        //storage_path().'/app/uploads/' 这里根据 /config/filesystems.php 文件里面的配置而定
        //$dir = str_replace('\\','/',storage_path().'/app/uploads/'.$today);
        $dir = storage_path() . '/app/public/images/' . $today;
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        //上传文件
        $filename = uniqid() . '.' . $ext;//新文件名
        if (Storage::disk('public')->put('/images/' . $today . '/' . $filename, file_get_contents($realPath))) {

            $user = $this->user;

            $user->image = "/storage/images/$today/$filename";
            $user->save();

            return URL::asset($user->image);
        } else {
            return self::error(self::CODE_FAIL_TO_SAVE_IMAGE, "图片保存出错");
        }
    }

    //设置支付宝账户
    public function setUserAlipayAccount(Request $request)
    {
        $user = $this->user;

        $all = $request->all();
        $rules = [
            'account' => 'required',
        ];
        $messages = [
            'account.required' => '请填写支付宝账户'
        ];
        $validator = Validator::make($all, $rules, $messages);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        $userAccount = $user->userAccount;

        if (!$userAccount) {
            $userAccount = new UserAccount();
            $userAccount->user_id = $user->id;
        }

        $userAccount->alipay = $all['account'];

        if ($userAccount->save()) {
            return self::success($userAccount);
        } else {
            return self::parametersIllegal();
        }
    }

    //设置支付密码
    public function setUserPaymentPassword(Request $request)
    {
        $user = $this->user;

        $all = $request->all();
        $rules = [
            'password' => 'required',
        ];
        $messages = [
            'account.required' => '请填写支付密码'
        ];
        $validator = Validator::make($all, $rules, $messages);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        $userAccount = $user->userAccount;

        if (!$userAccount) {
            $userAccount = new UserAccount();
            $userAccount->user_id = $user->id;
        }

        $userAccount->password = $all['password'];

        if ($userAccount->save()) {
            return self::success($userAccount);
        } else {
            return self::parametersIllegal();
        }
    }

    //获取用户微信 授权回调地址
    public function getUserAuthAddress()
    {

    }

    //接收用户网页授权回调
    public function verify()
    {

    }

    public function systemMessage(Request $request)
    {
        $inputs = $request->all();
        $message = $inputs['message'];
        $userId = $request['user_id'];
        GatewayWorkerService::sendSystemMessage($message,$userId);
    }

    //设置个人中心展示图片
    public function uploadUserCenterImages(Request $request)
    {
        $user = $this->user;
        $userCenter = $user->userCenter;

        if (!$userCenter) {
            $userCenter = new UserCenter();
            $userCenter->user_id = $user->id;
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
            $dir = storage_path() . '/app/public/images/users/' . $today;
            if (!is_dir($dir)) {
                mkdir($dir);
            }

            //上传文件
            $filename = uniqid() . '.' . $ext;//新文件名
            if (Storage::disk('public')->put('/images/users/' . $today . '/' . $filename, file_get_contents($realPath))) {
                $image = "/storage/images/users/$today/$filename";
                $imageArray[] = URL::asset($image);
            } else {
                return self::error(self::CODE_FAIL_TO_SAVE_IMAGE, "图片保存出错");
            }
        }

        $userCenter->images = json_encode($imageArray);
        $userCenter->save();

        return self::success($imageArray);

    }

    //设置用户专长
    public function setUserTalents(Request $request)
    {
        $user = $this->user;

        $inputs = $request->all();

        UserTalent::where('user_id', $user->id)->delete();

        foreach ($inputs as $k => $input) {
            $userTalent = new UserTalent();
            $userTalent->user_id = $user->id;
            $userTalent->classification = $input;
            $userTalent->save();
        }

        return self::success($user->userTalents);
    }

    //填写用户个人描述
    public function setUserDescription(Request $request)
    {
        $user = $this->user;
        $userCenter = $user->userCenter;

        if (!$userCenter) {
            $userCenter = new UserCenter();
            $userCenter->user_id = $user->id;
        }

        $inputs = $request->all();

        if (!isset($inputs['description'])) {
            return self::parametersIllegal('请填写个人说明');
        }

        $description = $inputs['description'];

        $userCenter->description = $description;
        $userCenter->save();

        return self::success($user->userCenter);
    }

    //进入个人中心
    public function userCenter($id)
    {
        $user = $this->user;
        $User = User::where('id', $id)->with('userInfo')->with('userCenter')->with('userTalents')->first();

        if($user->id == $id) {
            $editable = true;
        } else {
            $editable = false;
        }

        $User->editable = $editable;

        if (count($user->userTalents)) {
            $classifications = Helper::transformToKeyValue(app('assignment_classifications'), 'id', 'name');
            $User->userTalents = $User->userTalents()->pluck('classification');

            foreach ($User->userTalents as $k => $talent) {
                $User->userTalents[$k] = $classifications[$talent];
            }
        }
        return self::success($User);
    }
}
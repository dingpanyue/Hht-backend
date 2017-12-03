<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;
/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/3
 * Time: 3:36
 */
use App\Models\Assignment;
use App\Models\Order;
use App\Services\AssignmentService;
use Yansongda\Pay\Pay;
use Illuminate\Http\Request;

class PayController extends BaseController
{
    protected $assignmentService;

    public function __construct(AssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;

        parent::__construct();
    }

    protected $config = [
        'alipay' => [
            'app_id' => '2017072807930919',
            'notify_url' => 'http://yansongda.cn/alipay_notify.php',
            'return_url' => 'http://yansongda.cn/return.php',
            'ali_public_key' => 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC3ktzC/yM+nbs67uWfMZ2hc31gIXLOZ9I0IH/q7L9/PduneT98eTa8o6uoWqxLwW5pb9D5aGU/yICNZxDLNdfjAsvrCaA2f0BkqYkxgdd4FTM7Er6qz/eR/RiPLVRyVbrSz3IcE9P5PQOa4UKiWjcLPvjJPxGcBs6G3Oh8riSpoQIDAQAB',
            'private_key' => 'MIICXQIBAAKBgQC3ktzC/yM+nbs67uWfMZ2hc31gIXLOZ9I0IH/q7L9/PduneT98eTa8o6uoWqxLwW5pb9D5aGU/yICNZxDLNdfjAsvrCaA2f0BkqYkxgdd4FTM7Er6qz/eR/RiPLVRyVbrSz3IcE9P5PQOa4UKiWjcLPvjJPxGcBs6G3Oh8riSpoQIDAQABAoGAS2p+X23J4POT88Ypd5k+lRGJNHEJZVqptNiVNMJGedPD5a2eM1jo796dqvB1UDoLTi2twIju76FDjtQExjc8lUh1wyma//N4cxnqA0dfRpxnrdHYXZ8BwuyAH0ZqdpjALzwFgNXRSHHaA+3PMsgTGjLLHoUKYd0HWTvePZRLyEECQQDZS+tUqoH+qXdYbBcoCx9R/bTYRbLqJIbHJ3M4JdceSPIkfNC3/gW/QIORCCWCvtfu6u136nNXFoJzhaYDYoO1AkEA2EVHjqPX4zSMnVI5QifMFOHUo08F5lzbYboT3uDRua5tHyRVQPbdvp95qxj5mMX0znaWvx6S6wnrNAfjTtHZvQJATqqrgbVQ5o8Xg81t/LM6HYbJ59oj0ZxzprnjfppEbNRfxVHihhnSntCOUP0wB0tsBTTLz7PzGb4ucAAcf/E0WQJBAIi1SmVlNmud5SDxP9aMt5mfoz1UD4OtwNOGv1bMwGXiV4IvAmEda9A6mLtJ/0TOJVB5cBMBrZc7Xt01+z7wsfUCQQC6KyDoKnSdXOJFai7fl96OmnZGBt1V9Q7WohlOJHaUJZ5T9RTSM3IC71momjReS9TtUQ2zbebc+e9oDk5RHfIg',
        ],
    ];

    /**
     * @param $method  string  支付方式 可选 ali wechat
     * @param $type  string  支付对象 assignment service
     * @param $pk   integer 主键  assignment 和 service 的id
     * @return mixed
     */
    public function index(Request $request)
    {
        $input = $request->only('type', 'method', 'pk');

        $method = $input['method'];
        $type = $input['type'];
        $pk = $input['pk'];

        $globalConfigs = app('global_configs');
        $rate = $globalConfigs['service_fee_rate'];

        if (!in_array($method, [Order::ALIPAY, Order::WECHAT])) {
            return self::parametersIllegal();
        }

        if (!in_array($type, [Order::TYPE_ASSIGNMENT, Order::TYPE_SERVICE])) {
            return self::parametersIllegal();
        }

        //如果是委托人的订单
        if ($type == Order::TYPE_ASSIGNMENT){
            /**
             * @var $assignment Assignment
             */
            $assignment = $this->assignmentService->getAssignmentById($pk);

            if (!$assignment) {
                return self::resourceNotFound();
            }

            if ($assignment->status != Assignment::STATUS_UNPAID) {
                return self::notAllowed();
            }

            if ($assignment->user_id != $this->user->id) {
                return self::notAllowed("你不是这条委托的发布人，无法支付");
            }

            //创建订单
            $order = new Order();
            $order->user_id = $this->user->id;
            $order->type = $type;
            $order->primaryKey = $pk;
            $order->method = $method;
            $order->fee = $assignment->reward;
            $order->out_trade_no = 'ASSIGN'.time();
            $order->status = Order::STATUS_PREPARING;
            $order->save();
        }

        //如果是服务单
        if ($type == Order::TYPE_SERVICE) {

            //todo 服务部分逻辑
            $order = new Order();
            $order->user_id = $this->user->id;
            $order->type = $type;
            $order->primaryKey = $pk;
            $order->method = $method;
//            $order->fee = $assignment->reward;
            $order->out_trade_no = 'ASSIGN'.time();
            $order->status = Order::STATUS_PREPARING;
            $order->save();
        }


        $config_biz = [
            'out_trade_no' => $order->out_trade_no,
            'total_amount' => $order->fee,
            'subject'      => 'test subject',
        ];

        $pay = new Pay($this->config);

        if ($method == Order::ALIPAY) {
            return $pay->driver('alipay')->gateway('wap')->pay($config_biz);
        } else {
            return $pay->driver('wechat')->gateway('wap')->pay($config_biz);
        }
    }

    public function notify(Request $request)
    {
        $pay = new Pay($this->config);

        if ($pay->driver('alipay')->gateway()->verify($request->all())) {
            file_put_contents(storage_path('notify.txt'), "收到来自支付宝的异步通知\r\n", FILE_APPEND);
            file_put_contents(storage_path('notify.txt'), '订单号：' . $request->out_trade_no . "\r\n", FILE_APPEND);
            file_put_contents(storage_path('notify.txt'), '订单金额：' . $request->total_amount . "\r\n\r\n", FILE_APPEND);
        } else {
            file_put_contents(storage_path('notify.txt'), "收到异步通知\r\n", FILE_APPEND);
        }

        echo "success";
    }
}

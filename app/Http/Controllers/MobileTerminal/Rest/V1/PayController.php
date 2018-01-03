<?php

namespace App\Http\Controllers\MobileTerminal\Rest\V1;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/3
 * Time: 3:36
 */
use App\Models\AcceptedService;
use App\Models\Assignment;
use App\Models\OperationLog;
use App\Models\Order;
use App\Models\User;
use App\Services\AcceptedServiceService;
use App\Services\AssignmentService;
use App\Services\FlowLogService;
use App\Services\OperationLogService;
use App\Services\OrderService;
use App\Services\ServiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use lyt8384\Pingpp\Facades\Pingpp;
use Yansongda\Pay\Pay;
use Illuminate\Http\Request;

class PayController extends BaseController
{
    protected $assignmentService;

    protected $acceptedServiceService;

    protected $orderService;

    protected $operationLogService;

    protected $flowLogService;

    public function __construct(AssignmentService $assignmentService, AcceptedServiceService $acceptedServiceService, OrderService $orderService, OperationLogService $operationLogService, FlowLogService $flowLogService)
    {
        $this->assignmentService = $assignmentService;

        $this->acceptedServiceService = $acceptedServiceService;

        $this->orderService = $orderService;

        $this->operationLogService = $operationLogService;

        $this->flowLogService = $flowLogService;

        parent::__construct();
    }

    //支付宝支付参数,由于换成了ping++
//    protected $config = [
//        'alipay' => [
//            'app_id' => '2017072807930919',
//            'notify_url' => 'http://yansongda.cn/alipay_notify.php',
//            'return_url' => 'http://yansongda.cn/return.php',
//            'ali_public_key' => 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC3ktzC/yM+nbs67uWfMZ2hc31gIXLOZ9I0IH/q7L9/PduneT98eTa8o6uoWqxLwW5pb9D5aGU/yICNZxDLNdfjAsvrCaA2f0BkqYkxgdd4FTM7Er6qz/eR/RiPLVRyVbrSz3IcE9P5PQOa4UKiWjcLPvjJPxGcBs6G3Oh8riSpoQIDAQAB',
//            'private_key' => 'MIICXQIBAAKBgQC3ktzC/yM+nbs67uWfMZ2hc31gIXLOZ9I0IH/q7L9/PduneT98eTa8o6uoWqxLwW5pb9D5aGU/yICNZxDLNdfjAsvrCaA2f0BkqYkxgdd4FTM7Er6qz/eR/RiPLVRyVbrSz3IcE9P5PQOa4UKiWjcLPvjJPxGcBs6G3Oh8riSpoQIDAQABAoGAS2p+X23J4POT88Ypd5k+lRGJNHEJZVqptNiVNMJGedPD5a2eM1jo796dqvB1UDoLTi2twIju76FDjtQExjc8lUh1wyma//N4cxnqA0dfRpxnrdHYXZ8BwuyAH0ZqdpjALzwFgNXRSHHaA+3PMsgTGjLLHoUKYd0HWTvePZRLyEECQQDZS+tUqoH+qXdYbBcoCx9R/bTYRbLqJIbHJ3M4JdceSPIkfNC3/gW/QIORCCWCvtfu6u136nNXFoJzhaYDYoO1AkEA2EVHjqPX4zSMnVI5QifMFOHUo08F5lzbYboT3uDRua5tHyRVQPbdvp95qxj5mMX0znaWvx6S6wnrNAfjTtHZvQJATqqrgbVQ5o8Xg81t/LM6HYbJ59oj0ZxzprnjfppEbNRfxVHihhnSntCOUP0wB0tsBTTLz7PzGb4ucAAcf/E0WQJBAIi1SmVlNmud5SDxP9aMt5mfoz1UD4OtwNOGv1bMwGXiV4IvAmEda9A6mLtJ/0TOJVB5cBMBrZc7Xt01+z7wsfUCQQC6KyDoKnSdXOJFai7fl96OmnZGBt1V9Q7WohlOJHaUJZ5T9RTSM3IC71momjReS9TtUQ2zbebc+e9oDk5RHfIg',
//        ],
//    ];

    /**
     * @param $method  string  支付方式 可选 ali wechat
     * @param $type  string  支付对象 assignment service
     * @param $pk   integer 主键  assignment 和 service 的id
     * @return mixed
     */
    public function pay(Request $request)
    {
        $input = $request->only('type', 'method', 'pk');

        $validator =  $validator = app('validator')->make($input, [
            'type' => 'required',
            'method' => 'required',
            'pk'=> 'required'
        ], [
            'type.required' => '订单类型必须填写',
            'method.required' => '请选择支付方式',
            'pk.required' => '主键必须填写'
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        $method = $input['method'];
        $type = $input['type'];
        $pk = $input['pk'];

        $this->user = User::find(1);

        $globalConfigs = app('global_configs');

        if (!in_array($method, [Order::ALIPAY, Order::WX, Order::UPACP])) {
            return self::parametersIllegal();
        }

        if (!in_array($type, [Order::TYPE_ASSIGNMENT, Order::TYPE_SERVICE])) {
            return self::parametersIllegal();
        }

        //如果是委托人的订单
        if ($type == Order::TYPE_ASSIGNMENT) {
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
            $order->primary_key = $pk;
            $order->method = $method;
            $order->fee = $assignment->reward;
            $order->out_trade_no = 'ASSIGN' . time();
            $order->status = Order::STATUS_PREPARING;
        } else {
            /**
             * @var $acceptedService AcceptedService
             */
            $acceptedService = $this->acceptedServiceService->getAcceptedServiceById($pk);

            if (!$acceptedService) {
                return self::resourceNotFound();
            }

            if ($acceptedService->status != AcceptedService::STATUS_UNPAID) {
                return self::notAllowed();
            }

            if ($acceptedService->assign_user_id != $this->user->id) {
                return self::notAllowed("你不是这条服务的购买，无法支付");
            }

            //创建订单
            $order = new Order();
            $order->user_id = $this->user->id;
            $order->type = $type;
            $order->primary_key = $pk;
            $order->method = $method;
            $order->fee = $acceptedService->reward;
            $order->out_trade_no = 'SERVICE' . time();
            $order->status = Order::STATUS_PREPARING;
        }

        \Pingpp\Pingpp::setApiKey('sk_test_KqTiHGvrnvPSnnPWPS0CaTKS');
        $charge = \Pingpp\Charge::create(array(
            'order_no' => $order->out_trade_no,
            'amount' => $order->fee,
            'app' => array('id' => 'app_f5OCi9P80q1OnXL4'),
            'channel' => $method,
            'currency' => 'cny',
            'client_ip' => '127.0.0.1',
            'subject' => 'Your Subject',
            'body' => 'Your Body',
        ));

        if ($charge) {
            $order->charge_id = $charge->id;
        }

        if ($order->save()) {
            return self::success($charge);
        } else {
            return self::error(self::CODE_ORDER_SAVE_ERROR, '订单信息保存失败');
        }
    }

    //异步通知
    public function notify()
    {
        $event = json_decode(file_get_contents("php://input"));
        if (!isset($event->type)) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
            exit("fail");
        }
        switch ($event->type) {
            case "charge.succeeded":
                // 开发者在此处加入对支付异步通知的处理代码
                header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
                $data = $event->data;
                $outTradeNo = $data->trade_no;
                $totalAmount = $data->amount;
                $method = $data->channel;

                $order = $this->orderService->getOrderByOutTradeNo($outTradeNo);

                DB::transaction(function () use ($order, $method, $totalAmount) {
                    //改变订单状态
                    $order->status = Order::STATUS_SUCCEED;
                    $order->save();

                    if ($order->type == Order::TYPE_ASSIGNMENT) {
                        /**
                         * @var $assignment Assignment
                         */
                        //改变委托状态
                        $assignment = $this->assignmentService->getAssignmentById($order->primary_key);
                        $assignment->status = Assignment::STATUS_WAIT_ACCEPT;
                        $assignment->save();

                        //记录委托操作日志
                        $this->operationLogService->log(
                            OperationLog::OPERATION_PAY,
                            OperationLog::TABLE_ASSIGNMENTS,
                            $assignment->id,
                            $assignment->user_id,
                            OperationLog::STATUS_UNPAID,
                            OperationLog::STATUS_WAIT_ACCEPT
                        );

                        //记录流水日志
                        $this->flowLogService->log(
                            $assignment->user_id,
                            'orders',
                            $method,
                            $order->id,
                            $totalAmount
                        );
                    } else {
                        /**
                         * @var $acceptedService AcceptedService
                         */
                        $acceptedService = $this->acceptedServiceService->getAcceptedServiceById($order->primary_key);
                        $acceptedService->status = AcceptedService::STATUS_ADAPTED;
                        $acceptedService->save();

                        $this->operationLogService->log(
                            OperationLog::OPERATION_PAY,
                            OperationLog::TABLE_ACCEPTED_SERVICES,
                            $acceptedService->id,
                            $acceptedService->assign_user_id,
                            OperationLog::STATUS_UNPAID,
                            OperationLog::STATUS_ADAPTED
                        );

                        //记录流水日志
                        $this->flowLogService->log(
                            $acceptedService->assign_user_id,
                            'orders',
                            $method,
                            $order->id,
                            $totalAmount
                        );
                    }
                });
                break;
            case "refund.succeeded":
                // 开发者在此处加入对退款异步通知的处理代码
                header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
                break;
            default:
                header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
                break;
        }
        return;
    }

    public function withdrawals()
    {
        \Pingpp\Pingpp::setApiKey('sk_test_KqTiHGvrnvPSnnPWPS0CaTKS');
        \Pingpp\Pingpp::setAppId('app_f5OCi9P80q1OnXL4');
        $withdrawals = \Pingpp\Withdrawal::create([

            "id" => "1701611150302360654",
            "object" => "withdrawal",
            "app" => "app_f5OCi9P80q1OnXL4",
            "amount" => 20000,
            "asset_transaction" => "",
            "balance_transaction" => "",
            "channel" => "unionpay",
            "created" => 1472648887,
            "description" => "test232description",
            "extra" => [
                "account" => "6225210207073918",
                "name" => "姓名",
                "open_bank_code" => "0102",
                "prov" => "上海",
                "city" => "上海"
            ],
            "fee" => 200,
            "livemode" => true,
            "metadata" => [],
            "order_no" => "20160829133002",
            "source" => null,
            "status" => "created",
            "time_canceled" => null,
            "time_succeeded" => null,
            "user" => "user_001",
            "user_fee" => 50]);

        return $withdrawals;


    }

    //
//    public function notify(Request $request)
//    {
//        $pay = new Pay($this->config);
//
//        //支付宝通知
//        if ($pay->driver('alipay')->gateway()->verify($request->all())) {
//
//            $outTradeNo = $request->out_trade_no;
//            $totalAmount = $request->total_amount;
//
//            file_put_contents(storage_path('notify.txt'), "收到来自支付宝的异步通知\r\n", FILE_APPEND);
//            file_put_contents(storage_path('notify.txt'), '订单号：' . $request->out_trade_no . "\r\n", FILE_APPEND);
//            file_put_contents(storage_path('notify.txt'), '订单金额：' . $request->total_amount . "\r\n\r\n", FILE_APPEND);
//
//            /**
//             * @var $order Order
//             */
//            $order = $this->orderService->getOrderByOutTradeNo($outTradeNo);
//
//            //如果回调的时候，订单已经是成功状态
//            if (!$order->status == Order::STATUS_SUCCEED) {
//                Log::warning("订单$order->id, 在回调时已经是已支付状态");
//            }
//
//
//            DB::transaction(function () use ($order) {
//                //改变订单状态
//                $order->status = Order::STATUS_SUCCEED;
//                $order->save();
//
//                if ($order->type == Order::TYPE_ASSIGNMENT) {
//                    /**
//                     * @var $assignment Assignment
//                     */
//                    //改变委托状态
//                    $assignment = $this->assignmentService->getAssignmentById($order->primary_key);
//                    $assignment->status = Assignment::STATUS_WAIT_ACCEPT;
//                    $assignment->save();
//
//                    //记录委托操作日志
//                    $this->operationLogService->log(
//                        OperationLog::OPERATION_PAY,
//                        OperationLog::TABLE_ASSIGNMENTS,
//                        $assignment->id,
//                        $assignment->user_id,
//                        OperationLog::STATUS_UNPAID,
//                        OperationLog::STATUS_WAIT_ACCEPT
//                    );
//
//                    //记录流水日志
//                    $this->flowLogService->log(
//                        $assignment->user_id,
//                        'orders',
//                        'alipay',
//                        $order->id,
//                        $assignment->reward
//                    );
//                }
//
//
//            });
//
//        } else {
//            file_put_contents(storage_path('notify.txt'), "收到异步通知\r\n", FILE_APPEND);
//        }
//
//        echo "success";
//    }


}

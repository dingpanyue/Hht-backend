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
use App\Models\TimedTask;
use App\Models\User;
use App\Models\UserInfo;
use App\Services\AcceptedServiceService;
use App\Services\AssignmentService;
use App\Services\FlowLogService;
use App\Services\GatewayWorkerService;
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
        $user = $this->user;

        $input = $request->only('type', 'method', 'pk');

        $validator = $validator = app('validator')->make($input, [
            'type' => 'required',
            'method' => 'required',
            'pk' => 'required'
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

        $globalConfigs = app('global_configs');

        if (!in_array($method, [Order::ALIPAY, Order::WX, Order::UPACP, Order::BALANCE])) {
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

        //判断余额支付 如果够  则 直接处理订单
        if ($method == Order::BALANCE) {
            $originBalance = $user->userInfo->balance;
            if ($originBalance >= $order->fee) {

                $balance = $originBalance - $order->fee;
                $totalAmount = $order->fee;
                DB::transaction(function () use ($order, $method, $totalAmount, $user) {
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

                        //添加定时任务，检查服务过期
                        $timedTask = new TimedTask();
                        $timedTask->name = "接受的服务 $acceptedService->id 达到deadline";
                        $timedTask->command = "outDate serve $acceptedService->id";
                        $timedTask->start_time = $acceptedService->deadline;
                        $timedTask->result = 0;
                        $timedTask->save();

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

                    //更新帐户余额
                    $balance = UserInfo::where('user_id', $user->id)->pluck('balance');
                    $originBalance = $balance[0];
                    if ($originBalance > $order->fee) {
                        $balance = $originBalance - $order->fee;
                        UserInfo::where('user_id', $user->id)->update(['balance' => $balance]);
                    } else {
                        throw new \Exception();
                    }
                });
                return self::success($order);
            } else {
                return self::error(self::CODE_BALANCE_NOTE_ENOUGH, "账户余额不足，请选择其他支付方式");
            }
        } else {

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

                        //添加定时任务，检查服务过期
                        $timedTask = new TimedTask();
                        $timedTask->name = "接受的服务 $acceptedService->id 达到deadline";
                        $timedTask->command = "outDate serve $acceptedService->id";
                        $timedTask->start_time = $acceptedService->deadline;
                        $timedTask->result = 0;
                        $timedTask->save();

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

                $data = $event->data;
                $refund_id = $data->id;

                /**
                 * @var $order Order
                 */
                if ($data->succeed == true) {
                    $order = Order::where('refund_id', $refund_id)->first();

                    if ($order->type == Order::TYPE_ASSIGNMENT) {
                        /**
                         * @var $assignment Assignment
                         */
                        $assignment = Assignment::where('id', $order->primary_key)->first();

                        if ($assignment->status == Assignment::STATUS_REFUNDING) {
                            DB::transaction(function () use ($order, $assignment) {
                                $order->status = Order::STATUS_REFUNDED;
                                $order->save();

                                $assignment->status = Assignment::STATUS_FAILED;
                                $assignment->save();

                                //添加操作日志
                                $this->operationLogService->log(
                                    OperationLog::OPERATION_REFUND,
                                    OperationLog::TABLE_ASSIGNMENTS,
                                    $assignment->id,
                                    $assignment->user_id,
                                    OperationLog::STATUS_REFUNDING,
                                    OperationLog::STATUS_FAILED
                                );

                                //流水日志 负数
                                $this->flowLogService->log(
                                    $assignment->user_id,
                                    'orders',
                                    $order->method,
                                    $order->id,
                                    -$order->fee
                                );
                            });
                            $message = "您接受委托 $assignment->title 的退款申请已处理成功，退款打入您的支付账户，委托取消";
                            GatewayWorkerService::sendSystemMessage($message, $assignment->user_id);
                        }
                    }

                    if ($order->type ==  Order::TYPE_SERVICE) {

                        $acceptedService = AcceptedService::where('id', $order->primary_key)->first();
                        DB::transaction(function () use ($order, $acceptedService) {

                            $order->status = Order::STATUS_REFUNDED;
                            $order->save();

                            $acceptedService->status = Assignment::STATUS_FAILED;
                            $acceptedService->save();

                            //添加操作日志
                            $this->operationLogService->log(
                                OperationLog::OPERATION_REFUND,
                                OperationLog::TABLE_ACCEPTED_SERVICES,
                                $acceptedService->id,
                                0,
                                OperationLog::STATUS_REFUNDING,
                                OperationLog::STATUS_FAILED
                            );

                            //流水日志 负数
                            $this->flowLogService->log(
                                $acceptedService->assign_user_id,
                                'orders',
                                $order->method,
                                $order->id,
                                -$order->fee
                            );
                        });
                        $message = "您购买的服务的退款已处理成功，退款打入您的支付账户，委托取消";
                        GatewayWorkerService::sendSystemMessage($message, $acceptedService->assign_user_id);
                    }
                }
                break;
            default:
                header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
                break;
        }
        return;
    }

    //退款
    public function refund($type, $pk)
    {
        $user = $this->user;

        if ($type == 'assignment') {
            /**
             * @var $assignment Assignment
             */
            $assignment = $this->assignmentService->getAssignmentById($pk);

            //只有未采纳过的委托可以主动退款
            if ($assignment->status != Assignment::STATUS_WAIT_ACCEPT) {
                return self::notAllowed();
            }

            if ($assignment->user_id != $user->id) {
                return self::notAllowed();
            }

            /**
             * @var $order Order
             */
            $order = Order::where('type', $type)->where('primary_key', $pk)->where('status', 'succeed')->first();

            if (!$order) {
                return self::error(self::CODE_ORDER_STATUS_ABNORMAL, '订单状态异常，无法完成退款');
            } else {
                if ($order->method == Order::BALANCE) {
                    DB::transaction(function () use ($order, $user, $assignment) {

                        //返回余额
                        $balance = UserInfo::where('user_id', $user->id)->pluck('balance');
                        $originBalance = $balance[0];
                        $finalBalance = $originBalance + $order->fee;

                        $userInfo = $user->userInfo;
                        $userInfo->balance = $finalBalance;
                        $userInfo->save();

                        //修改委托状态
                        $assignment->status = Assignment::STATUS_CANCELED;
                        $assignment->save();

                        //修改订单状态
                        $order->status = Order::STATUS_REFUNDED;
                        $order->save();

                        //添加操作日志
                        $this->operationLogService->log(
                            OperationLog::OPERATION_REFUND,
                            OperationLog::TABLE_ASSIGNMENTS,
                            $assignment->id,
                            $assignment->user_id,
                            OperationLog::STATUS_WAIT_ACCEPT,
                            OperationLog::STATUS_FAILED
                        );

                        //流水日志 负数
                        $this->flowLogService->log(
                            $assignment->user_id,
                            'orders',
                            $order->method,
                            $order->id,
                            -$order->fee
                        );
                    });

                    return self::success("退款成功，余额已返回您的账户，委托取消");
                } else {
                    $charge_id = $order->charge;

                    \Pingpp\Pingpp::setApiKey('sk_test_KqTiHGvrnvPSnnPWPS0CaTKS');
                    \Pingpp\Pingpp::setPrivateKeyPath(__DIR__ . '/your_rsa_private_key.pem');

                    $ch = \Pingpp\Charge::retrieve($charge_id);//ch_id 是已付款的订单号

                    //todo 扣除支付的手续费
                    $refund = $ch->refunds->create(
                        array(
                            'amount' => $order->fee,
                            'description' => 'Refund Description'
                        )
                    );

                    //todo 判断refund 对象


                    //把委托状态改为退款中   将退款id 写入order
                    DB::transaction(function () use ($order, $assignment, $refund) {
                        $assignment->status = Assignment::STATUS_REFUNDING;
                        $assignment->save();

                        $order->refund_id = $refund->id;
                        $order->save();
                    });
                }
            }
        }
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
}

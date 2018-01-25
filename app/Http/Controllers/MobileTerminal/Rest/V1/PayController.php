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
use App\Models\Withdrawal;
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
use Yansongda\Pay\Gateways\Alipay\Alipay;
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

        $validator = app('validator')->make($input, [
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
            $title = $assignment->title;

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
            $title = $acceptedService->service->title();

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
                        $timedTask->start_time = date('Y-m-d H:i', strtotime($acceptedService->deadline)) . ':00';
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

            \Pingpp\Pingpp::setApiKey(env('PINGPP_API_KEY'));
            \Pingpp\Pingpp::setPrivateKeyPath(storage_path('private.key'));
            $charge = \Pingpp\Charge::create(array(
                'order_no' => $order->out_trade_no,
                'amount' => $order->fee,
                'app' => array('id' => 'app_f5OCi9P80q1OnXL4'),
                'channel' => $method,
                'currency' => 'cny',
                'client_ip' => '120.132.30.39',
                'subject' => $title,
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
                $data = $event->all();
                $outTradeNo = $data->order_no;
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
                        $timedTask->start_time = date('Y-m-d H:i', strtotime($acceptedService->deadline)) . ':00';
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
                            $message = "您发布的委托 $assignment->title 的退款申请已处理成功，退款打入您的支付账户，委托取消";
                            GatewayWorkerService::sendSystemMessage($message, $assignment->user_id);
                        }
                    }

                    if ($order->type == Order::TYPE_SERVICE) {

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
                        $assignment->status = Assignment::STATUS_FAILED;
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

    //提现接口
//    public function withdrawals(Request $request)
//            {
//                //获取提现用户
//                $user = $this->user;
//                $userInfo = $user->userInfo;
//                $userAccount = $user->userAccount;
//                $balance = $userInfo->balance;
//                $inputs = $request->only('method', 'amount');
//
//                $validator = app('validator')->make($inputs, [
//                    'method' => 'required|in:alipay,ws',
//                    'amount' => 'required|numeric|min:0'
//                ], [
//                    'method.required' => '提现方式必须填写',
//                    'method.in' => '提现方式只能为微信或者支付宝',
//                    'amount.required' => '提现数额必须填写',
//                    'amount.numeric' => '提现数量必须为数字',
//                    'amount.min' => '提现金额必须大于0'
//                ]);
//
//                if ($validator->fails()) {
//                    return self::parametersIllegal($validator->messages()->first());
//                }
//
//                if ($balance < $inputs['amount']) {
//                    return self::error(self::CODE_BALANCE_NOTE_ENOUGH, '帐户余额不足');
//                }
//
//                $amount = $inputs['amount'];
//                $method = $inputs['method'];
//                $out_trade_no = time();
//
//                //支付宝提现
//                if ($inputs['method'] == 'alipay') {
//                    //必须有支付宝账户
//                    if (!($userAccount && $userAccount->alipay)) {
//                        return self::notAllowed('您尚没有在账户中填写支付宝账户，无法提现到支付宝');
//                    }
//
//                    $config = [
//                        'alipay' => [
//                            'app_id' => '2017072807930919',
//                            'ali_public_key' => 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC3ktzC/yM+nbs67uWfMZ2hc31gIXLOZ9I0IH/q7L9/PduneT98eTa8o6uoWqxLwW5pb9D5aGU/yICNZxDLNdfjAsvrCaA2f0BkqYkxgdd4FTM7Er6qz/eR/RiPLVRyVbrSz3IcE9P5PQOa4UKiWjcLPvjJPxGcBs6G3Oh8riSpoQIDAQAB',
//                            'private_key' => 'MIICXQIBAAKBgQC3ktzC/yM+nbs67uWfMZ2hc31gIXLOZ9I0IH/q7L9/PduneT98eTa8o6uoWqxLwW5pb9D5aGU/yICNZxDLNdfjAsvrCaA2f0BkqYkxgdd4FTM7Er6qz/eR/RiPLVRyVbrSz3IcE9P5PQOa4UKiWjcLPvjJPxGcBs6G3Oh8riSpoQIDAQABAoGAS2p+X23J4POT88Ypd5k+lRGJNHEJZVqptNiVNMJGedPD5a2eM1jo796dqvB1UDoLTi2twIju76FDjtQExjc8lUh1wyma//N4cxnqA0dfRpxnrdHYXZ8BwuyAH0ZqdpjALzwFgNXRSHHaA+3PMsgTGjLLHoUKYd0HWTvePZRLyEECQQDZS+tUqoH+qXdYbBcoCx9R/bTYRbLqJIbHJ3M4JdceSPIkfNC3/gW/QIORCCWCvtfu6u136nNXFoJzhaYDYoO1AkEA2EVHjqPX4zSMnVI5QifMFOHUo08F5lzbYboT3uDRua5tHyRVQPbdvp95qxj5mMX0znaWvx6S6wnrNAfjTtHZvQJATqqrgbVQ5o8Xg81t/LM6HYbJ59oj0ZxzprnjfppEbNRfxVHihhnSntCOUP0wB0tsBTTLz7PzGb4ucAAcf/E0WQJBAIi1SmVlNmud5SDxP9aMt5mfoz1UD4OtwNOGv1bMwGXiV4IvAmEda9A6mLtJ/0TOJVB5cBMBrZc7Xt01+z7wsfUCQQC6KyDoKnSdXOJFai7fl96OmnZGBt1V9Q7WohlOJHaUJZ5T9RTSM3IC71momjReS9TtUQ2zbebc+e9oDk5RHfIg',
//                        ]
//                    ];
//
//                    $config_biz = [
//                        'out_biz_no' => $out_trade_no ,                      // 订单号
//                        'payee_type' => 'ALIPAY_LOGONID',        // 收款方账户类型(ALIPAY_LOGONID | ALIPAY_USERID)
//                        'payee_account' =>  $userAccount->alipay,   // 收款方账户
//                        'amount' => $inputs['amount'],                        // 转账金额
//                    ];
//
//                    //执行转账
//                    $pay = new Pay($config);
//                    $result = $pay->driver('alipay')->gateway('transfer')->pay($config_biz);
//
//                    // todo 判断提现结果
//                    if (1) {
//                        DB::transaction(function () use ($userInfo, $amount, $method, $out_trade_no) {
//
//                            //创建withdrawl
//                            $withdrawal = new Withdrawal();
//                            $withdrawal->method = $method;
//                            $withdrawal->account = '';        //todo 用户的账户
//                            $withdrawal->fee = $amount;
//                            $withdrawal->out_trade_no = $out_trade_no;
//                            $withdrawal->user_id = $userInfo->user_id;
//                            $withdrawal->status = 'success';
//                            $withdrawal->save();
//
//                            //扣除余额
//                            $balance = UserInfo::where('id', $userInfo->id)->pluck('balance');
//                            $originBalance = $balance[0];
//                            $finalBalance = $originBalance - $amount;
//
//                            if ($finalBalance < 0){
//                                Log::info("用户 $userInfo->user_id 提现时发生错误，余额小于0");
//                            }
//
//                            $userInfo->balance = $finalBalance;
//                            $userInfo->save();
//                        });
//                    }
//                }
//
//                //微信提现
//                if ($inputs['method'] == 'ws') {
//                    //必须有微信openid
//                    if (!($userAccount && $userAccount->wechat)) {
//                        return self::notAllowed('您尚未给此app进行微信授权，无法提现到微信');
//                    }
//
//                    $config = [
//                        'wechat' => [
//                            'appid' => '',                    //微信分配的账号ID（企业号corpid即为此appId） 实际请求参数mch_appid
//                            'mch_id' => '',                        //商户号          实际参数mchid
//                            'nonce_str' => rand(1.99999).rand(1, 99999),            //请求随机数
//                            'key' => '',                                      //就是sign,整合其他接口，改名为key
//                            'cert_client' => './apiclient_cert.pem',
//                            'cert_key' => './apiclient_key.pem',
//                        ]
//                    ];
//
//                    $config_biz = [
//                        'partner_trade_no' => $out_trade_no,              //订单号
//                        'openid' => $userAccount->wechat,                         //提现用户在此开放平台的openid
//                        'check_name' => 'NO_CHECK',                     //是否验证姓名
//                        'amount' => $amount,                          //提现金额
//                        'desc' => '提现',                             //描述
//                        'spbill_create_ip' => '127.0.0.1'
//                    ];
//
//                    //执行转账
//                    $pay = new Pay($config);
//                    $pay->driver('wechat')->gateway('transfer')->pay($config_biz);
//
//                    // todo 判断提现结果
//                    if (1) {
//                        DB::transaction(function () use ($userInfo, $amount, $method, $out_trade_no) {
//
//                            //创建withdrawl
//                            $withdrawal = new Withdrawal();
//                            $withdrawal->method = $method;
//                            $withdrawal->account = '';        //todo 用户的账户
//                            $withdrawal->fee = $amount;
//                            $withdrawal->out_trade_no = $out_trade_no;
//                            $withdrawal->user_id = $userInfo->user_id;
//                            $withdrawal->status = 'success';
//                            $withdrawal->save();
//
//                            //扣除余额
//                            $balance = UserInfo::where('id', $userInfo->id)->pluck('balance');
//                            $originBalance = $balance[0];
//                            $finalBalance = $originBalance - $amount;
//
//                            if ($finalBalance < 0){
//                                Log::info("用户 $userInfo->user_id 提现时发生错误，余额小于0");
//                            }
//
//                            $userInfo->balance = $finalBalance;
//                            $userInfo->save();
//                        });
//                    }
//        }
//    }

     //Pingpp 提现接口

}

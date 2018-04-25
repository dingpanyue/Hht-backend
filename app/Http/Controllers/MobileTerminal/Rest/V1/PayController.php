<?php

namespace App\Http\Controllers\MobileTerminal\Rest\V1;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/3
 * Time: 3:36
 */
use App\Events\RefundSucceed;
use App\Events\TransferFailed;
use App\Events\TransferSucceed;
use App\Models\AcceptedAssignment;
use App\Models\AcceptedService;
use App\Models\Assignment;
use App\Models\OperationLog;
use App\Models\Order;
use App\Models\TimedTask;
use App\Models\UserInfo;
use App\Models\Withdrawal;
use App\Services\AcceptedServiceService;
use App\Services\AssignmentService;
use App\Services\FlowLogService;
use App\Services\GatewayWorkerService;
use App\Services\OperationLogService;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\CssSelector\Exception\InternalErrorException;
use EasyWeChat\Factory;

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


    /**
     * @param $method  string  支付方式 可选 ali wechat
     * @param $type  string  支付对象 assignment service
     * @param $pk   integer 主键  assignment 和 service 的id
     * @return mixed
     */
    public function pay(Request $request)
    {
        $user = $this->user;

        $input = $request->only('type', 'method', 'pk', 'code');

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
                return self::notAllowed("你不是这条需求的发布人，无法支付");
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
            $title = $acceptedService->service->title;

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
            if (!($user->userAccount && $user->userAccount->password)) {
                return self::notAllowed('请先去您的钱包设置支付密码');
            }
            if (!$input['code']) {
                return self::parametersIllegal("请输入您的支付密码");
            }
            $code = $input['code'];
            if ($code != $user->userAccount->password) {
                return self::parametersIllegal("请输入正确的支付密码");
            }
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

                    if ($order->type == Order::TYPE_ASSIGNMENT) {
                        //余额支付， 支付完成后同步推送
                        $timedTask = new TimedTask();
                        $timedTask->name = "发布的服务 $assignment->id 推送";
                        $timedTask->command = "push $assignment->id";
                        $timedTask->start_time = date('Y-m-d H:i', (strtotime('now') + 60)) . ':00';
                        $timedTask->result = 0;
                        $timedTask->save();
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
                'amount' => $order->fee * 100,
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

        //ping++ 收到的各种通知， 为了美观和提高重用性 接下来应该分布到listener里面去
        switch ($event->type) {
            case "charge.succeeded":
                // 开发者在此处加入对支付异步通知的处理代码
                header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
                $data = $event->data->object;
                \Log::info(json_encode($data));
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
                            $totalAmount*0.01
                        );

                        $timedTask = new TimedTask();
                        $timedTask->name = "发布的服务 $assignment->id 推送";
                        $timedTask->command = "push $assignment->id";
                        $timedTask->start_time = date('Y-m-d H:i', (strtotime('now') + 60)) . ':00';
                        $timedTask->result = 0;
                        $timedTask->save();
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
                            $totalAmount*0.01
                        );
                    }
                });
                break;

            case "refund.succeeded":
                // 开发者在此处加入对退款异步通知的处理代码
                header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
                $data = $event->data->object;
                try {
                    event(new RefundSucceed($data));
                } catch (\Exception $e) {
                    \Log::error('[' . $e->getCode() . '] ' . $e->getMessage());
                    throw new InternalErrorException();
                }
                break;

            case "transfer.succeeded":
                header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
                $data = $event->data->object;
                try {
                    event(new TransferSucceed($data, Withdrawal::STATUS_SUCCESS));
                } catch (\Exception $e) {
                    \Log::error('[' . $e->getCode() . '] ' . $e->getMessage());
                    throw new InternalErrorException();
                }
                break;

            case "transfer.failed":
                header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
                $data = $event->data->object;
                try {
                    event(new TransferFailed($data));
                } catch (\Exception $e) {
                    \Log::error('[' . $e->getCode() . '] ' . $e->getMessage());
                }
                break;
            default:
                header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
                break;

        }
        return;
    }

    //退款           只有未接受的委托允许主动退款
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
                //删除子对象
                $acceptedAssignments = AcceptedAssignment::where('parent_id', $assignment->id)->get();

                foreach ($acceptedAssignments as $invalidAcceptedAssignment) {
                    $message = "由于委托 $assignment->title 已经申请退款， 您接受该需求的申请已失效，系统帮您自动删除";
                    AcceptedAssignment::where('parent_id', $assignment->id)->delete();
                    GatewayWorkerService::sendSystemMessage($message, $invalidAcceptedAssignment->serve_user_id);
                }


                //处理余额退款
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

                    return self::success("退款成功，余额已返回您的账户，需求取消");
                } else {
                    $charge_id = $order->charge_id;

                    \Pingpp\Pingpp::setApiKey(env('PINGPP_API_KEY'));
                    \Pingpp\Pingpp::setPrivateKeyPath(storage_path('private.key'));

                    $ch = \Pingpp\Charge::retrieve($charge_id);//ch_id 是已付款的订单号

                    try {
                        $refund = $ch->refunds->create(
                            array(
                                'amount' => $order->fee * 100,
                                'description' => 'Refund Description'
                            )
                        );
                    } catch (\Exception $e) {
                        return self::error($e->getCode(), $e->getMessage());
                    }

                    //把委托状态改为退款中   将退款id 写入order
                    DB::transaction(function () use ($order, $assignment, $refund) {
                        $assignment->status = Assignment::STATUS_REFUNDING;
                        $assignment->save();

                        $order->refund_id = $refund->id;
                        $order->save();
                    });

                    return self::success("退款申请提交成功，您的退款正在处理中");
                }
            }
        }
    }


    //提现接口        从微信到银行卡是原生       从pingpp到支付宝是pingpp
    public function withdrawals(Request $request)
    {
        //获取提现用户
        $user = $this->user;
        $userInfo = $user->userInfo;
        $userAccount = $user->userAccount;
        $balance = $userInfo->balance;
        $inputs = $request->only('method', 'amount');

        $validator = app('validator')->make($inputs, [
            'method' => 'required|in:alipay,ws,bank',
            'amount' => 'required|numeric|min:100'
        ], [
            'method.required' => '提现方式必须填写',
            'method.in' => '提现方式只能为微信或者支付宝或者银行卡',
            'amount.required' => '提现数额必须填写',
            'amount.numeric' => '提现数量必须为数字',
            'amount.min' => '提现金额必须大于100元'
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        if ($balance < $inputs['amount']) {
            return self::error(self::CODE_BALANCE_NOTE_ENOUGH, '帐户余额不足');
        }

        $amount = $inputs['amount'];
        $method = $inputs['method'];
        $out_trade_no = 'Withdrawal' . time();

        //提现到支付宝
        if ($method == 'alipay') {
            //必须有支付宝账户
            if (!($userAccount && $userAccount->alipay)) {
                return self::notAllowed('您尚没有在账户中填写支付宝账户，无法提现到支付宝');
            }

            try {
                DB::transaction(function () use ($userInfo, $amount, $method, $out_trade_no, $userAccount) {
                    //创建withdrawl
                    $withdrawal = new Withdrawal();
                    $withdrawal->method = $method;
                    if ($method == 'alipay') {
                        $withdrawal->account = $userAccount->alipay;
                    }

                    if ($method == 'wx') {
                        $withdrawal->account = $userAccount->wechat;
                    }
                    $withdrawal->fee = $amount;
                    $withdrawal->out_trade_no = $out_trade_no;
                    $withdrawal->user_id = $userInfo->user_id;
                    $withdrawal->status = Withdrawal::STATUS_PROCESSING;
                    $withdrawal->save();

                    //扣除余额
                    $balance = UserInfo::where('id', $userInfo->id)->pluck('balance');
                    $originBalance = $balance[0];
                    $finalBalance = $originBalance - $amount;

                    if ($finalBalance < 0) {
                        throw new \Exception(self::MESSAGE_BALANCE_NOT_ENGOUGH, self::CODE_BALANCE_NOTE_ENOUGH);
                    }

                    $userInfo->balance = $finalBalance;
                    $userInfo->save();

                    \Pingpp\Pingpp::setApiKey(env('PINGPP_API_KEY'));
                    \Pingpp\Pingpp::setPrivateKeyPath(storage_path('private.key'));
                    \Pingpp\Transfer::create(
                        array(
                            'order_no' => $out_trade_no,
                            'app' => array('id' => 'app_f5OCi9P80q1OnXL4'),
                            'channel' => $method,
                            'amount' => $amount * 100,
                            'currency' => 'cny',
                            'type' => 'b2c',
                            'recipient' => $userAccount->alipay,
                            'description' => '行行通感谢您的使用'
                        )
                    );
                });
            } catch (\Exception $e) {
                return self::error($e->getCode(), $e->getMessage());
            }
            return self::success("提现请求提交成功，请耐心等待");
        }

        if ($method == 'wx') {
            //必须有微信openid
            if (!($userAccount && $userAccount->alipay)) {
                return self::notAllowed('您尚没有给微信进行授权，无法提现到微信');
            }
        }

        //微信提现到银行卡
        if ($method == 'bank') {

            if (!($user->userInfo && $user->userInfo->real_name && $user->userAccount && $user->userAccount->bank_type && $user->userAccount->bank_account)) {
                return self::notAllowed("请您完成实名认证，然后填写银行卡信息，才能提现到银行卡");
            }

            $config = [
                // 必要配置
                'app_id'             => 'xxxx',
                'mch_id'             => env('MCH_ID'),
                'key'                => env('API_KEY'),   // API 密钥

                // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
                'cert_path'          => storage_path('apiclient_cert.pem'), // XXX: 绝对路径！！！！
                'key_path'           => storage_path('apiclient_key.pem'),      // XXX: 绝对路径！！！！

                // 将上面得到的公钥存放路径填写在这里
                'rsa_public_key_path' => storage_path('public-1498230542.pem'), // <<<------------------------

                'notify_url'         => '默认的订单回调地址',     // 你也可以在下单时单独设置来想覆盖它
            ];

            $app = Factory::payment($config);

            try {
                DB::transaction(function () use ($userInfo, $amount, $method, $out_trade_no, $userAccount, $app, $user) {
                    //创建withdrawl
                    $withdrawal = new Withdrawal();
                    $withdrawal->method = $method;
                    $withdrawal->account = $userAccount->bank_account;
                    $withdrawal->fee = $amount;
                    $withdrawal->out_trade_no = $out_trade_no;
                    $withdrawal->user_id = $userInfo->user_id;
                    $withdrawal->status = Withdrawal::STATUS_PROCESSING;
                    $withdrawal->save();

                    //扣除余额
                    $balance = UserInfo::where('id', $userInfo->id)->pluck('balance');
                    $originBalance = $balance[0];
                    $finalBalance = $originBalance - $amount;

                    if ($finalBalance < 0) {
                        throw new \Exception(self::MESSAGE_BALANCE_NOT_ENGOUGH, self::CODE_BALANCE_NOTE_ENOUGH);
                    }

                    $userInfo->balance = $finalBalance;
                    $userInfo->save();

                    $result = $app->transfer->toBankCard([
                        'partner_trade_no' => $out_trade_no,
                        'enc_bank_no' => $user->userAccount->bank_account, // 银行卡号
                        'enc_true_name' => $user->userInfo->real_name,   // 银行卡对应的用户真实姓名
                        'bank_code' => $user->userAccount->bank_type, // 银行编号
                        'amount' => $amount * 100,  // 单位：分
                        'desc' => '提现',
                    ]);

                    if ($result['err_code'] != 'SUCCESS'){
                        throw new \Exception($result['err_code_des']);
                    }

                    //创建定时任务检验退款
                    $timedTask = new TimedTask();
                    $timedTask->name = "提现 $withdrawal->id 已经三天";
                    $timedTask->command = "check $withdrawal->id";
                    $timedTask->start_time = date('Y-m-d H:i', strtotime('now + 3 days')) . ':00';
                    $timedTask->result = 0;
                    $timedTask->save();
                });
            } catch (\Exception $e){
                return self::error(self::CODE_WECHAT_PAY_BANK_ERROR, $e->getMessage());
            }
            return self::success("提现申请成功，请耐心等待到账");
        }
    }

    public function queryWxBankPay($outTradeNo)
    {
        $config = [
            // 必要配置
            'app_id'             => 'xxxx',
            'mch_id'             => env('MCH_ID'),
            'key'                => env('API_KEY'),   // API 密钥

            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path'          => storage_path('apiclient_cert.pem'), // XXX: 绝对路径！！！！
            'key_path'           => storage_path('apiclient_key.pem'),      // XXX: 绝对路径！！！！

            // 将上面得到的公钥存放路径填写在这里
            'rsa_public_key_path' => storage_path('public-1498230542.pem'), // <<<------------------------

            'notify_url'         => '默认的订单回调地址',     // 你也可以在下单时单独设置来想覆盖它
        ];

        $app = Factory::payment($config);
    }
}


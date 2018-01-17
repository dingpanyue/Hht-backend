<?php

namespace App\Console\Commands;

use App\Models\AcceptedAssignment;
use App\Models\AcceptedService;
use App\Models\Assignment;
use App\Models\OperationLog;
use App\Models\Order;
use App\Models\UserInfo;
use App\Services\FlowLogService;
use App\Services\GatewayWorkerService;
use App\Services\OperationLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OutDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outDate {type} {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $operationLogService;

    protected $flowLogService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(OperationLogService $operationLogService, FlowLogService $flowLogService)
    {
        $this->operationLogService = $operationLogService;

        $this->flowLogService = $flowLogService;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $type = $this->argument('type');
        $id = $this->argument('id');

        //委托过期(指到达deadline)
        if ($type == 'assign') {
            $acceptedAssignment = AcceptedAssignment::find($id);

            //委托在被采纳之后  双方都没有后续处理   则判定委托失败   信用等级降低   退款     双方推送
            if ($acceptedAssignment->status == AcceptedAssignment::STATUS_ADAPTED) {
                DB::transaction(function () use ($acceptedAssignment) {
                    //修改 采纳的委托状态为失败
                    $acceptedAssignment->status = AcceptedAssignment::STATUS_FAILED;
                    $acceptedAssignment->save();

                    //修改委托状态为失败
                    $assignment = $acceptedAssignment->assignment;
                    $assignment->status = Assignment::STATUS_FAILED;
                    $assignment->save();

                    //serve_user 信用等级降低
                    $serveUser = $acceptedAssignment->serveUser;
                    $serveUserInfo = $serveUser->userInfo;
                    $serveUserInfo->serve_points = $serveUserInfo->serve_points - (int)$acceptedAssignment->reward;
                    $serveUserInfo->save();

                    //退款
                    $order = Order::where('type', 'assignment')->where('primary_key', $assignment->id)->where('status', 'succeed')->first();

                    if (!$order) {
                        Log::info("处理委托 $assignment->id outdate时出现错误，没有对应的订单");
                        throw new \Exception();
                    } else {
                        if ($order->method == Order::BALANCE) {
                            //返回余额
                            $user = $assignment->user;
                            $balance = UserInfo::where('user_id', $user->id)->pluck('balance');
                            $originBalance = $balance[0];
                            $finalBalance = $originBalance + $order->fee;

                            $userInfo = $user->userInfo;
                            $userInfo->balance = $finalBalance;
                            $userInfo->save();

                            //修改订单状态
                            $order->status = Order::STATUS_REFUNDED;
                            $order->save();

                            //添加操作日志
                            $this->operationLogService->log(
                                OperationLog::OPERATION_REFUND,
                                OperationLog::TABLE_ACCEPTED_ASSIGNMENTS,
                                $acceptedAssignment->id,
                                0,
                                OperationLog::STATUS_ADAPTED,
                                OperationLog::STATUS_FAILED,
                                '服务人逾期未提交完成，委托失败'
                            );

                            //流水日志 负数
                            $this->flowLogService->log(
                                $assignment->user_id,
                                'orders',
                                $order->method,
                                $order->id,
                                -$order->fee
                            );

                            $message = "您接受的委托由于超过期限未作处理，委托已失败";
                            GatewayWorkerService::sendSystemMessage($message, $acceptedAssignment->serve_user_id);

                            $message = "您采纳的委托由于处理人超过期限未作处理，委托已失败,已退款到您的余额";
                            GatewayWorkerService::sendSystemMessage($message, $acceptedAssignment->assign_user_id);

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
                            $assignment->status = Assignment::STATUS_REFUNDING;
                            $assignment->save();

                            $order->refund_id = $refund->id;
                            $order->save();

                            $message = "您接受的委托由于超过期限未作处理，委托已失败";
                            GatewayWorkerService::sendSystemMessage($message, $acceptedAssignment->serve_user_id);

                            $message = "您采纳的委托由于处理人超过期限未作处理，委托已失败,已退款到您的余额";
                            GatewayWorkerService::sendSystemMessage($message, $acceptedAssignment->assign_user_id);
                        }
                    }
                });
            }

            //委托在被确认完成之后，没有后续处理    则完成委托   信用等级增加   打款     双方推送
            if ($acceptedAssignment->status == AcceptedAssignment::STATUS_DEALT) {

                $globalConfigs = app('global_configs');
                $rate = $globalConfigs['service_fee_rate'];
                $assignment = $acceptedAssignment->assignment;
                $order = Order::where('type', 'assignment')->where('primary_key', $assignment->id)->where('status', 'succeed')->first();

                DB::transaction(function () use ($acceptedAssignment, $rate, $assignment, $order) {

                    $acceptedAssignment->status = AcceptedAssignment::STATUS_FINISHED;
                    $acceptedAssignment->save();


                    $assignment->status = Assignment::STATUS_FINISHED;
                    $assignment->save();

                    //把报酬打到serve_user 账户          增加服务星级
                    $serveUser = $acceptedAssignment->serveUser;
                    $serveUserInfo = $serveUser->userInfo;

                    //更新余额
                    $serveUserInfo->balance = $serveUserInfo->balance + $acceptedAssignment->reward * (1 - $rate);
                    //更新积分
                    $serveUserInfo->serve_points = $serveUserInfo->serve_points + (int)$acceptedAssignment->reward;
                    $serveUserInfo->save();

                    $assignUser = $acceptedAssignment->assignUser;
                    $assignUserInfo = $assignUser->userInfo;

                    //流水日志
                    $this->flowLogService->log(
                        $acceptedAssignment->serve_user_id,
                        'orders',
                        Order::BALANCE,
                        $order->id,
                        -$order->fee
                    );

                    //更新委托人积分
                    $assignUserInfo->assign_points = $assignUserInfo->assign_points + (int)$acceptedAssignment->reward;
                    $assignUserInfo->save();

                    //操作日志
                    $this->operationLogService->log(
                        OperationLog::OPERATION_FINISH,
                        OperationLog::TABLE_ACCEPTED_ASSIGNMENTS,
                        $acceptedAssignment->id,
                        0,
                        AcceptedAssignment::STATUS_DEALT,
                        OperationLog::STATUS_FINISHED,
                        '委托已被提交确认，购买人过期未确认，已自动完成'
                    );

                    $message = "您提交完成的委托已经自动完成,报酬已经打入您的余下额";
                    GatewayWorkerService::sendSystemMessage($message, $acceptedAssignment->serve_user_id);

                    $message = "您的委托已经被服务方提交完成，由于超过期限没有确认，系统已自动完成该委托";
                    GatewayWorkerService::sendSystemMessage($message, $acceptedAssignment->assign_user_id);
                });
            }

            //委托已经是完成的状态  do nothing
            if ($acceptedAssignment->status == AcceptedAssignment::STATUS_FINISHED) {
                exit();
            }
        }

        //服务过期(指到达deadline)
        if ($type == 'serve') {
            $acceptedService = AcceptedService::find($id);

            //服务在被购买之后  双方都没有后续处理   则判定服务失败   信用等级降低   退款     双方推送
            if ($acceptedService->status == AcceptedService::STATUS_ADAPTED) {

                echo 1;
                DB::transaction(function () use ($acceptedService) {
                    //修改 采纳的服务状态为失败
                    $acceptedService->status = AcceptedService::STATUS_FAILED;
                    $acceptedService->save();

                    //serve_user 信用等级降低
                    $serveUser = $acceptedService->serveUser;
                    $serveUserInfo = $serveUser->userInfo;
                    $serveUserInfo->serve_points = $serveUserInfo->serve_points - (int)$acceptedService->reward;
                    $serveUserInfo->save();

                    //退款
                    $order = Order::where('type', 'service')->where('primary_key', $acceptedService->id)->where('status', 'succeed')->first();

                    if (!$order) {
                        Log::info("处理接收的服务 $acceptedService->id 时出现错误，没有对应的订单");
                    } else {
                        if ($order->method == Order::BALANCE) {

                            //返回余额
                            $user = $acceptedService->assign_user;
                            $balance = UserInfo::where('user_id', $user->id)->pluck('balance');
                            $originBalance = $balance[0];
                            $finalBalance = $originBalance + $order->fee;

                            $userInfo = $user->userInfo;
                            $userInfo->balance = $finalBalance;
                            $userInfo->save();

                            //修改订单状态
                            $order->status = Order::STATUS_REFUNDED;
                            $order->save();

                            //添加操作日志
                            $this->operationLogService->log(
                                OperationLog::OPERATION_REFUND,
                                OperationLog::TABLE_ACCEPTED_SERVICES,
                                $acceptedService->id,
                                0,
                                OperationLog::STATUS_ADAPTED,
                                OperationLog::STATUS_FAILED,
                                '服务人逾期未提交完成确认，服务失败'
                            );

                            //流水日志 负数
                            $this->flowLogService->log(
                                $acceptedService->assign_user_id,
                                'orders',
                                $order->method,
                                $order->id,
                                -$order->fee
                            );


                            $message = "您提供的服务由于超过期限没有申请完成，服务已失败";
                            GatewayWorkerService::sendSystemMessage($message, $acceptedService->serve_user_id);

                            $message = "您购买的服务由于服务方超过期限未提交完成，服务已失败,已退款到您的余额";
                            GatewayWorkerService::sendSystemMessage($message, $acceptedService->assign_user_id);

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

                            //把服务状态改为退款中   将退款id 写入order

                            $acceptedService->status = AcceptedService::STATUS_REFUNDING;
                            $acceptedService->save();

                            $order->refund_id = $refund->id;
                            $order->save();


                            $message = "您提供的服务由于超过期限没有申请完成，服务已失败";
                            GatewayWorkerService::sendSystemMessage($message, $acceptedService->serve_user_id);

                            $message = "您购买的服务由于服务方超过期限未提交完成，服务已失败,退款正在处理中";
                            GatewayWorkerService::sendSystemMessage($message, $acceptedService->assign_user_id);
                        }
                    }
                });
                return;
            }

            //服务在被确认完成之后，没有后续处理    则完成服务   信用等级增加   打款     双方推送
            if ($acceptedService->status == AcceptedAssignment::STATUS_DEALT) {

                $globalConfigs = app('global_configs');
                $rate = $globalConfigs['service_fee_rate'];

                $service = $acceptedService->service;
                $order = Order::where('type', 'service')->where('primary_key', $acceptedService->id)->where('status', 'succeed')->first();

                $acceptedService = DB::transaction(function () use ($acceptedService, $rate, $order) {
                    $acceptedService->status = AcceptedService::STATUS_FINISHED;
                    $acceptedService->save();

                    //记录日志 -- 采纳接受的委托
                    $this->operationLogService->log(
                        OperationLog::OPERATION_FINISH,
                        OperationLog::TABLE_ACCEPTED_SERVICES,
                        $acceptedService->id,
                        0,
                        AcceptedAssignment::STATUS_DEALT,
                        OperationLog::STATUS_FINISHED
                    );

                    //把报酬打到serve_user 账户          增加服务星级
                    $serveUser = $acceptedService->serveUser;
                    $serveUserInfo = $serveUser->userInfo;

                    //更新余额
                    $serveUserInfo->balance = $serveUserInfo->balance + $acceptedService->reward * (1 - $rate);
                    //更新积分
                    $serveUserInfo->serve_points = $serveUserInfo->serve_points + (int)$acceptedService->reward;
                    $serveUserInfo->save();

                    $this->flowLogService->log(
                        $acceptedService->assign_user_id,
                        'orders',
                        Order::BALANCE,
                        $order->id,
                        -$order->fee
                    );

                    $assignUser = $acceptedService->assignUser;
                    $assignUserInfo = $assignUser->userInfo;


                    //更新委托人积分
                    $assignUserInfo->assign_points = $assignUserInfo->assign_points + (int)$acceptedService->reward;
                    $assignUserInfo->save();

                    return $acceptedService;
                });

                //推送
                $message = "您提交完成的服务 $service->title 已被系统确认完成，服务报酬已经打入您的余额";
                GatewayWorkerService::sendSystemMessage($message, $acceptedService->serve_user_id);

                $message = "您购买的服务之前被提交完成，由于超过时间您未确认，已为您自动完成";
                GatewayWorkerService::sendSystemMessage($message, $acceptedService->serve_user_id);
            }

            //服务已经是完成的状态  do nothing
            if ($acceptedService->status == AcceptedAssignment::STATUS_FINISHED) {
                return;
            }

            return;
        }
    }
}

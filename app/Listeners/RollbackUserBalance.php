<?php

namespace App\Listeners;

use App\Events\TransferFailed;
use App\Models\User;
use App\Models\UserInfo;
use App\Models\Withdrawal;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class RollbackUserBalance
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  TransferFailed  $event
     * @return void
     */
    public function handle(TransferFailed $event)
    {
        //
        $data = $event->data;

        $orderNo = $data->order_no;

        $withdrawal = Withdrawal::where('out_trade_no', $orderNo)->where('status', Withdrawal::STATUS_PROCESSING)->first();

        if (!$withdrawal) {
            \Log::error('单号为'.$orderNo.'的提现 回调时未能找到提现单');
            throw new \Exception();
        }

        $amount = $withdrawal->fee;
        $userInfo = UserInfo::where('user_id', $withdrawal->user_id)->first();
        $balance = UserInfo::where('user_id', $withdrawal->user_id)->pluck('balance');
        try {
            DB::transaction(function () use ($userInfo, $balance, $amount, $withdrawal) {

                $userInfo->balance = $balance + $amount;
                $userInfo->save();

                if (!$withdrawal->status == Withdrawal::STATUS_PROCESSING) {
                    throw new \Exception('提现单已经之前已经处理完成');
                }

                $withdrawal->status = Withdrawal::STATUS_FAILED;
                $withdrawal->save();
            });
        } catch (\Exception $e) {
            throw $e;
        }
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [

        //提现(企业支付) 成功
        'App\Events\TransferSucceed' => [
            //修改提现单状态
            'App\Listeners\UpdateWithdrawalStatus'
        ],
        //提现(企业支付) 失败
        'App\Events\TransferFailed' => [
            //帐户余额回滚 + 修改订单状态
            'App\Listeners\RollbackUserBalance',
        ],
        //退款成功
        'App\Events\RefundSucceed' => [
            //处理退款
            'App\Listeners\DealWithRefund'
        ],

    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}

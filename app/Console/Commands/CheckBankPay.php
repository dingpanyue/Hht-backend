<?php

namespace App\Console\Commands;

use App\Models\Withdrawal;
use EasyWeChat\Factory;
use Illuminate\Console\Command;

class CheckBankPay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check {withdrawal_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
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
        $withdrawalId = $this->argument('withdrawal_id');
        $withdrawal = Withdrawal::find($withdrawalId);

        if (!$withdrawal) {
            \Log::error("查询提现 $withdrawalId 时未能找到该提现");
            exit();
        }

        if (!$withdrawal->status == Withdrawal::STATUS_PROCESSING) {
            \Log::error("查询提现 $withdrawalId 时发现 该提现已经经过处理");
            exit();
        }

        $outTradeNo = $withdrawal->out_trade_no;

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

        $order = $app->transfer->queryBankCardOrder($outTradeNo);


        //提现成功
        if ($order['result_code'] == 'SUCCESS') {
            $withdrawal->status = Withdrawal::STATUS_SUCCESS;
            $withdrawal->save();
        } else {
            
        }



    }
}

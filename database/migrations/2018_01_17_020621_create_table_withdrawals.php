<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableWithdrawals extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('withdrawals', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('method')->comment('支付方式');
            $table->string('account')->comment('转出账户');
            $table->decimal('fee')->comment('支付的费用');
            $table->string('out_trade_no')->index()->comment('外部订单编号');
            $table->integer('user_id')->nullable()->comment('用户id');
            $table->string('result')->comment('结果');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::drop('withdrawals');
    }
}

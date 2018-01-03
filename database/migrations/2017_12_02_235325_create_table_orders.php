<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('orders', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('type')->index()->comment('类型, assignment 和 service 两种');
            $table->integer('primary_key')->index()->comment('assignment 和 service 的主键');
            $table->string('method')->comment('支付方式');
            $table->decimal('fee')->comment('支付的费用');
            $table->string('out_trade_no')->index()->comment('外部订单编号');
            $table->string('status')->comment('状态');
            $table->integer('user_id')->nullable()->comment('用户id');
            $table->string('charge_id')->nullable()->comment('ping++支付id');
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
        Schema::drop('orders');
    }
}

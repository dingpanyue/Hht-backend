<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableUserAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('user_accounts', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id')->comment('用户id');
            $table->string('alipay')->nullable()->comment('支付宝账户');
            $table->string('wechat')->nullable()->comment('微信openid');
            $table->string('password')->nullable()->index()->comment('支付密码');
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
        Schema::drop('user_accounts');
    }
}

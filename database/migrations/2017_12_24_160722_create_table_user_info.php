<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableUserInfo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('user_info', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id')->index()->comment('用户的id');
            $table->string('real_name')->index()->comment('用户真实姓名');
            $table->string('card_no')->index()->comment('用户身份证号码');
            $table->decimal('balance')->comment('余额');
            $table->integer('points')->comment('成功之后所获得的 积分');
            $table->string('status')->comment('状态');
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
        Schema::drop('user_info');
    }
}

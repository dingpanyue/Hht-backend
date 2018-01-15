<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableUserAddresses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('user_addresses', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id')->comment('用户id');
            $table->integer('province_id')->comment('省份id');
            $table->integer('city_id')->comment('城市id');
            $table->integer('area_id')->comment('区域id');
            $table->string('detail_address')->comment('详细地址');
            $table->string('postcode')->nullable()->comment('开始时间');
            $table->string('receiver')->nullable()->comment('结束时间');
            $table->string('mobile')->nullable()->comment('电话号码');
            $table->integer('is_default')->nullable()->comment('是否为默认地址');
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
        Schema::drop('user_addresses');

    }
}

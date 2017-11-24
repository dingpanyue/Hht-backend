<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableServices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('services', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id')->index()->comment('服务者id');
            $table->string('title')->index()->comment('标题/概要');
            $table->integer('classification')->index()->comment('服务分类');
            $table->text('introduction')->nullable()->comment('详细介绍');
            $table->integer('province_id')->index();
            $table->integer('city_id')->index();
            $table->integer('area_id')->index();
            $table->decimal('lng');
            $table->decimal('lat');
            $table->text('detail_address')->comment('详细地址');
            $table->decimal('reward')->index()->nullable()->comment('报酬');
            $table->timestamp('expired_at')->nullable()->comment('提供服务截止时间');
            $table->tinyInteger('status')->comment('发布服务时的备注状态');
            $table->text('comment')->comment('备注');
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
    }
}

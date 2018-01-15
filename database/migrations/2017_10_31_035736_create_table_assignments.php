<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableAssignments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assignments', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id')->index()->comment('发布者id');
            $table->string('title')->index()->comment('标题/概要');
            $table->integer('classification')->index()->comment('委托分类');
            $table->text('introduction')->nullable()->comment('详细介绍');
            $table->text('images')->nullable()->comment('图片');
            $table->integer('province_id')->index();
            $table->integer('city_id')->index();
            $table->integer('area_id')->index();
            $table->decimal('lng');
            $table->decimal('lat');
            $table->text('detail_address')->comment('详细地址');
            $table->decimal('reward')->index()->nullable()->comment('报酬');
            $table->timestamp('expired_at')->nullable()->comment('委托可接受状态截止时间');
            $table->timestamp('deadline')->nullable()->comment('委托结束期限');
            $table->tinyInteger('status')->comment('发布委托时的备注状态');
            $table->integer('adapted_assignment_id')->comment('接受的委托的id');
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
        Schema::drop('assignments');
    }
}

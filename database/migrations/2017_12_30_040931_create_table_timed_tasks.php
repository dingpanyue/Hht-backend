<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableTimedTasks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('timed_tasks', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('name')->comment('名称');
            $table->string('command')->comment('命令');
            $table->timestamp('last_time')->nullable()->comment('上次执行时间');
            $table->string('note')->nullable()->comment('备注');
            $table->string('last_log')->nullable()->comment('执行日志');
            $table->timestamp('start_time')->nullable()->default(null)->comment('开始时间');
            $table->timestamp('end_time')->nullable()->default(null)->comment('结束时间');
            $table->integer('key')->nullable()->comment('');
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
        Schema::drop('timed_tasks');
    }
}

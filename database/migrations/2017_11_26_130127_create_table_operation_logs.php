<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableOperationLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('operation_logs', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('operation')->index()->comment('操作');
            $table->string('table')->index()->comment('表');
            $table->integer('primary_key')->index()->comment('主键');
            $table->integer('user_id')->nullable()->comment('用户id');
            $table->string('origin_status')->comment('初始状态');
            $table->string('final_status')->comment('操作后的状态');
            $table->text('comment')->comment('操作备注');
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
        Schema::drop('operation_logs');
    }
}

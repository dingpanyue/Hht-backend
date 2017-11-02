<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableAssignmentStatusLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('assignment_operation_logs', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('operation')->index()->comment('操作');
            $table->integer('user_id')->index();
            $table->integer('assignment_id')->index();
            $table->text('comment')->comment('备注');
            $table->integer('origin_status')->comment('操作前状态');
            $table->integer('final_status')->comment('操作后状态');
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
        Schema::drop('assignment_operation_logs');
    }
}

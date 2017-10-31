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
            $table->
            $table->decimal('reward')->comment('委托报酬');
            $table->timestamp('deadline')->comment('截止时间');
            $table->integer('status')->index();
            $table->text('comment')->comment('接受委托时的备注');
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

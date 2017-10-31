<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableAcceptedAssignments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('accepted_assignments', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('accept_user_id')->index();
            $table->integer('assignment_id')->index();
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
        Schema::drop('accepted_assignments');
    }
}

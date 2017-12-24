<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableAcceptedServices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('accepted_services', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('assign_user_id')->index()->comment('委托者id');
            $table->integer('serve_user_id')->index()->comment('服务者id');
            $table->integer('parent_id')->index();
            $table->decimal('reward')->comment('服务报酬');
            $table->timestamp('deadline')->comment('截止时间');
            $table->integer('status')->index();
            $table->text('comment')->comment('购买服务时的备注');
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
        Schema::drop('accepted_services');
    }
}

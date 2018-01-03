<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('messages', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('type')->index()->comment('类型');
            $table->text('message')->comment('内容');
            $table->integer('from_user_id')->index()->comment('发出者');
            $table->integer('to_user_id')->index()->comment('发给');
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
        Schema::drop('messages');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableFlowLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('flow_logs', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id')->index()->comment('付钱的人 或者 id');
            $table->string('table')->index();
            $table->string('method')->index()->comment('途径');
            $table->integer('primary_key')->comment('主键');
            $table->decimal('amount')->comment('金额');
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
        Schema::drop('flow_logs');
    }
}

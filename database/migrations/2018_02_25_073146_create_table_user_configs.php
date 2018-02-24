<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableUserConfigs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('user_configs', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id')->comment('用户id');
            $table->integer('show_mobile')->nullable()->comment('委托分类');
            $table->integer('show_region')->nullable();
            $table->integer('show_address')->nullable();
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

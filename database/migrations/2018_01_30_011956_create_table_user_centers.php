<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableUserCenters extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('user_centers', function(Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id')->comment('用户id');
            $table->string('images')->nullable()->comment('展示图片');
            $table->text('description')->nullable()->comment('个人说明');
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
        Schema::drop('user_centers');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateRestLogsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('rest_logs', function(Blueprint $table)
		{
			$table->increments('id');
			$table->text('request', 65535)->nullable();
			$table->string('request_route')->nullable();
			$table->text('client_useragent', 65535)->nullable();
			$table->string('client_ip', 15);
			$table->string('msgcode', 6)->nullable();
			$table->text('message', 65535)->nullable();
			$table->text('response', 65535)->nullable();
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
		Schema::drop('rest_logs');
	}

}

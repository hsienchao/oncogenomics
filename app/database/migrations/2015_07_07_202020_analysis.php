<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Analysis extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
                Schema::create('analysis', function(Blueprint $table) {                    
                    $table->increments('id');
                    $table->integer('study_id');
                    $table->string('analysis_name', 128);
                    $table->text('analysis_desc')->nullable();
                });

	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('analysis', function(Blueprint $table)
		{
			//
		});
	}

}

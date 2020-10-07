<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class StudyTable extends Migration {

	public function up()
	{
		Schema::create('studies', function(Blueprint $table) {                    
                    $table->increments('id');
                    $table->integer('user_id');
                    $table->string('study_type', 128);
                    $table->string('study_name', 128);
                    $table->text('study_desc')->nullable();
                    $table->boolean('is_public');
                    $table->integer('status');
                    $table->timestamps();
                });

                Schema::create('study_groups', function(Blueprint $table) {
                    $table->increments('id');
                    $table->integer('study_id');
                    $table->string('group_type',128);
                    $table->string('group_name',128);
                    $table->timestamps();
                });

                Schema::create('group_samples', function(Blueprint $table) {
                    $table->integer('group_id');
                    $table->string('sample_id',256);
                    $table->timestamps();
                    $table->primary(array('group_id','sample_id'));
                });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('studies');
                Schema::drop('study_groups');
                Schema::drop('group_samples');
	}


}

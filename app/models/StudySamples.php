<?php

class StudySamples extends Eloquent {
	protected $fillable = [];
        protected $table = 'study_samples';
	protected $primaryKey = 'study_id,sample_id';
	
}

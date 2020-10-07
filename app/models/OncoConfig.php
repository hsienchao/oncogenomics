<?php

class OncoConfig extends Eloquent {
	protected $fillable = [];
        protected $table = 'config';
        protected $primaryKey = 'config_key';
	public $timestamps = false;
}

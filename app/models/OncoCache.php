<?php

class OncoCache extends Eloquent {
	public $timestamps = false;
	public $incrementing = false;
	protected $fillable = [];
	protected $table = 'cache';
}
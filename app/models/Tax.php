<?php

class Tax extends Eloquent {

	protected $table = 'tax';
	public $timestamps = false;

	public function user_web()
	{
		return $this->belongsTo('Taxpayer', 'id_taxpayer');
	}

	
}
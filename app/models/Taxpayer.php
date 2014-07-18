<?php

class Taxpayer extends Eloquent {

	protected $table = 'taxpayer';
	public $timestamps = false;

	public function taxes()
	{
		return $this->hasMany('Tax', 'id_taxpayer');
	}

	
}
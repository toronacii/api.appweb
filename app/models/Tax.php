<?php

class Tax extends Eloquent {

	protected $table = 'tax';
	public $timestamps = false;

	public function user_web()
	{
		return $this->belongsTo('Taxpayer', 'id_taxpayer');
	}

/*
	public function toArray()
    {
        $array = parent::toArray();
        foreach ($this->getMutatedAttributes() as $key)
        {
            if ( ! array_key_exists($key, $array)) {
                $array[$key] = $this->{$key};   
            }
        }
        return $array;
    }

    public function getNombreCompletoAttribute()
    {
        return $this->tax_account_number . "hola";    
    }
*/
	
}
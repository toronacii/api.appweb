<?php

class BaseController extends Controller {

	/**
	 * Setup the layout used by the controller.
	 *
	 * @return void
	 */

	/*
	public $queries;
	public $last_query;

	public function __construct()
	{
		$this->queries = DB::getQueryLog();
		$this->last_query = end($this->queries);
		#return parent::__construct();
	}
	*/

	protected function setupLayout()
	{
		if ( ! is_null($this->layout))
		{
			$this->layout = View::make($this->layout);
		}
	}

    public static function getQuery($sql, $params)
    {
        $query = $sql;

        if ($params)
        {
            $search =  array_map(function($param) {
                return ":$param";   
            }, array_keys($params));

            $replace = array_values($params);

            $query = str_replace($search, $replace, $query);
        }

        return $query;
    }

    public function select($sql) {
        
        return new Illuminate\Support\Collection(DB::select($sql));

    }

}
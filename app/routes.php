<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::group(array('prefix' => 'api/v1'), function()
{
    /*Route::get('{controller}/{method?}/{args?}', function($controller, $method = "index", $args = null){

		$nameController = explode('_', $controller);
		$controller = "";

		foreach ($nameController as $partName)
			$controller .= ucfirst(strtolower($partName));

		$controller .= "Controller";

		$args = str_replace('#|=', '/', $args);

		if ($array = @unserialize($args))
			$argsNew[] = $array;
		else
			$argsNew = ($args) ? explode("/", $args) : array();

		#var_dump($args); exit;

		$controllerObject = App::make($controller);

		return call_user_func_array(array($controllerObject, $method), $argsNew);

	})->where('args', '.*');*/

	Route::post('{controller}/{method?}', function($controller, $method = "index"){

		$args = Input::all();

		$nameController = explode('_', $controller);
		$controller = "";

		foreach ($nameController as $partName)
			$controller .= ucfirst(strtolower($partName));

		$controller .= "Controller";

		$controllerObject = App::make($controller);

		return call_user_func_array(array($controllerObject, $method), $args);

	});



});

Route::get('/1/{id_taxpayer}', 

	function ($id_taxpayer)
    {
    	$sql = "SELECT *, created::date FROM (
                    SELECT DISTINCT news.id AS id_news, news.type, news.title, news.message, news.created, news.id_taxpayer, news.id_tax, news.authomatic, news_users_web.read_date, taxpayer.firm_name, tax.tax_account_number
                    FROM appweb.news
                    INNER JOIN taxpayer ON taxpayer.id = news.id_taxpayer
                    LEFT JOIN appweb.tax ON tax.id = news.id_tax
                    LEFT JOIN appweb.news_users_web ON news_users_web.id_news = news.id AND news_users_web.id_taxpayer = taxpayer.id
                    WHERE news.id_taxpayer = $id_taxpayer
                    AND NOW()::date BETWEEN date_from AND CASE WHEN date_to ISNULL THEN '2030-12-31' ELSE date_to END
                    AND news_users_web.deleted_at ISNULL
                    AND news.authomatic

                    UNION 

                    SELECT news.id, news.type, news.title, news.message, news.created, news.id_taxpayer, news.id_tax, news.authomatic, news_users_web.read_date, '', ''
                    FROM appweb.news
                    INNER JOIN appweb.tax ON tax.id_tax_type = ANY(news.id_tax_types)
                    LEFT JOIN appweb.news_users_web ON news_users_web.id_news = news.id AND news_users_web.id_taxpayer = tax.id_taxpayer
                    WHERE tax.id_taxpayer = $id_taxpayer
                    AND NOW()::date BETWEEN date_from AND CASE WHEN date_to ISNULL THEN '2030-12-31' ELSE date_to END
                    AND news_users_web.deleted_at ISNULL
                    AND NOT(news.authomatic)
                ) AS t

                ORDER BY t.created DESC";

		var_dump( DB::select($sql));
    }

);

/*

Route::get('2', function (){
	
	$taxes = Tax::find([155, 156, 157]);

	$myTaxes = $taxes->map(function($tax){
		$tax->nombreCompleto = $tax->created . " " . $tax->valid;
		return $tax;
	});

	var_dump($taxes->toArray());
});

*/

App::error(function(Exception $exception)
{

	$error = array(
		'message' => $exception->getMessage(),
		'file'    => $exception->getFile(),
		'line'    => $exception->getLine(),
		'trace'   => $exception->getTrace()
	);

    return Response::json(array('php_error' => $error));
    
});
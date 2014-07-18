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
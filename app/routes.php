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

		$reflectionMethod =  new \ReflectionMethod($controller, $method);

		Session::flash('paramNames', 
			array_map(
				function($item)
				{
	        		return $item->getName();
	    		},
    			$reflectionMethod->getParameters()
    		)
    	);

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
	$json = <<<EOT
O:8:"stdClass":26:{s:2:"id";i:10479;s:10:"id_invoice";s:6:"519632";s:7:"control";s:11:"13838971358";s:14:"cod_afiliacion";s:8:"27112013";s:7:"factura";s:10:"9100193718";s:5:"monto";s:6:"716.63";s:6:"estado";s:1:"A";s:6:"codigo";s:2:"00";s:11:"descripcion";s:15:"TRANS. APROBADA";s:4:"vtid";s:8:"13050831";s:6:"seqnum";s:2:"97";s:6:"authid";s:4:"2116";s:8:"authname";s:9:"P-Banesco";s:7:"tarjeta";s:16:"541247******6622";s:10:"referencia";s:2:"81";s:8:"terminal";s:8:"13050831";s:4:"lote";s:1:"1";s:8:"rifbanco";s:12:"J-07013380-5";s:10:"afiliacion";s:8:"27112013";s:6:"pagina";s:64:"http://localhost/appweb_clean/index.php/planillas_pago/impuestos";s:6:"correo";N;s:15:"validation_code";s:3:"224";s:7:"created";s:23:"2014-09-30 22:38:56.994";s:8:"modified";s:23:"2014-09-30 22:38:56.994";s:15:"date_compensate";s:19:"2014-10-01 03:10:12";s:6:"status";i:6;}
EOT;
	$verifier = unserialize($json);
	switch ($verifier->estado)
	{
		case 'P' : $verifier->titulo = 'Transacción pendiente'; break;
		case 'A' : $verifier->titulo = 'Transacción aprobada'; break;
		case 'R' : $verifier->titulo = 'Transacción rechazada'; break;
	}
	$verifier = (array)$verifier + array(
        'email' => 'toronacii@gmail.com',
        'view' => 'emails.online_payment'
    );

    $email = 'toronacii@gmail.com';

    Mail::send('emails.online_payment', $verifier, function ($message) use ($email)
	{
		$message->subject("Oficina Virtual - Alcaldía del Municipio Sucre");
		if (isset($subject))
	    	$message->subject($subject);
	    $message->to($email);
	});

    #dd($verifier);

    return View::make($verifier['view'], $verifier);
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

	Log::error($exception);

	$data = [
		'error'  => $exception,
		'params' => (Session::get('paramNames')) ? array_combine(Session::get('paramNames'), Input::all()) : NULL
	];

	#return View::make('emails.errors')->withError($exception);

	Mail::send('emails.errors', $data, function($message) {
		$message->subject("Oficina Virtual - Error - Debug");
	    $message->to('toronacii@gmail.com');
	});

    return Response::json(array('php_error' => $error));
    
});

<?php

date_default_timezone_set('America/Caracas');

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
	Route::post('{controller}/{method?}', function($controller, $method = "index"){

		$args = Input::all();

		$nameController = explode('_', $controller);
		$controller = "";

		foreach ($nameController as $partName)
			$controller .= ucfirst(strtolower($partName));

		$controller .= "Controller";

		#PARA CONOCER LOS VALORES DE LOS PARAMETROS EN EL DEBUG
		Session::flash('reflection', new \ReflectionMethod($controller, $method));

		$controllerObject = App::make($controller);

		return call_user_func_array(array($controllerObject, $method), $args);

	});



});

/*

Route::get('test', function(){

	$args = [unserialize('a:5:{s:8:"function";a:4:{s:6:"id_tax";s:6:"110505";s:4:"type";s:4:"TRUE";s:11:"fiscal_year";s:4:"2014";s:10:"activities";s:17:"{{662,156156.16}}";}s:7:"toolbar";a:5:{s:10:"resp_legal";s:11:"EDGAR MUNOZ";s:13:"ci_resp_legal";s:9:"7.663.350";s:5:"local";s:14:"0212-504-55-62";s:7:"celular";s:14:"0424-197-91-51";s:11:"id_taxpayer";i:13976;}s:4:"maps";a:3:{s:3:"lat";s:18:"10.498445692468321";s:4:"long";s:18:"-66.79623126983642";s:7:"json_gm";s:0:"";}s:20:"activities_specified";a:1:{i:662;s:3:"479";}s:12:"tax_discount";a:1:{i:18;s:9:"126515.61";}}')];
	//dd($args);
	return call_user_func_array(array(App::make('DeclaracionesController'), 'save_statement'), $args);

});

*/
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


/**
* join_params()
* @param $reflection 	-	reflection of method
* @param $values 		-	values of parameters
*/
function join_params($reflection, $values)
{
	$params = [];
	foreach ($reflection->getParameters() AS $index => $paramReflection)
	{
		if (isset($values[$index]))
		{
			$value = $values[$index];
		}
		else
		{
			$value = $paramReflection->getDefaultValue();
		}

		#d($index, $paramReflection, $value);
		$params[$paramReflection->getName()] = $value;
	}
	return $params;
}

/**
* jTraceEx() - provide a Java style exception trace
* @param $exception
* @param $seen      - array passed to recursive calls to accumulate trace lines already seen
*                     leave as NULL when calling this function
* @return array of strings, one entry per trace line
*/
function jTraceEx($e, $seen=null) {
    $starter = $seen ? 'Caused by: ' : '';
    $result = array();
    if (!$seen) $seen = array();
    $trace  = $e->getTrace();
    $prev   = $e->getPrevious();
    $result[] = sprintf('%s%s: %s', $starter, get_class($e), $e->getMessage());
    $file = $e->getFile();
    $line = $e->getLine();
    while (true) {
        $current = "$file:$line";
        if (is_array($seen) && in_array($current, $seen)) {
            $result[] = sprintf(' ... %d more', count($trace)+1);
            break;
        }
        $result[] = sprintf(' at %s%s%s(%s%s%s)',
                                    count($trace) && array_key_exists('class', $trace[0]) ? str_replace('\\', '.', $trace[0]['class']) : '',
                                    count($trace) && array_key_exists('class', $trace[0]) && array_key_exists('function', $trace[0]) ? '.' : '',
                                    count($trace) && array_key_exists('function', $trace[0]) ? str_replace('\\', '.', $trace[0]['function']) : '(main)',
                                    $line === null ? $file : basename($file),
                                    $line === null ? '' : ':',
                                    $line === null ? '' : $line);
        if (is_array($seen))
            $seen[] = "$file:$line";
        if (!count($trace))
            break;
        $file = array_key_exists('file', $trace[0]) ? $trace[0]['file'] : 'Unknown Source';
        $line = array_key_exists('file', $trace[0]) && array_key_exists('line', $trace[0]) && $trace[0]['line'] ? $trace[0]['line'] : null;
        array_shift($trace);
    }
    $result = join("\n", $result);
    if ($prev)
        $result  .= "\n" . jTraceEx($prev, $seen);

    return $result;
}

App::error(function(Exception $exception)
{

	$error = array(
		'message' => $exception->getMessage(),
		'file'    => $exception->getFile(),
		'line'    => $exception->getLine(),
		'trace'   => $exception->getTrace()
	);

	$logFile = 'log.log';
	Log::useDailyFiles(storage_path().'/logs/'.$logFile);
	Log::error($exception);

	$data = [
		'error'  => $exception,
		'params' => (Session::get('reflection')) ? join_params(Session::get('reflection'), Input::all()) : NULL,
		'trace'  => jTraceEx($exception)
	];

	#return View::make('emails.errors', $data);

	Mail::send('emails.errors', $data, function($message) {
		$message->subject("Error Oficina Virtual - [" . date('d/m/Y H:i:s') . "]");
	    $message->to('toronacii@gmail.com');
	});

    return Response::json(array('php_error' => $error));
   
});

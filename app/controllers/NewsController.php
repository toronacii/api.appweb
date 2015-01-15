<?php

class NewsController extends BaseController {

    function get_news($id_taxpayer)
    {
    	$sql = "SELECT * FROM appweb.get_news_taxpayer(?)
                ORDER BY created DESC";

		return DB::select($sql, array($id_taxpayer));
    }

    function mark($news, $id_taxpayer)
    {
    	$table = DB::table('appweb.news_users_web');

    	$tableWhereUpdate = $table->where('id_taxpayer', $id_taxpayer)->whereIn('id_news', $news);

    	if ($tableWhereUpdate->count())
    	{
    		return Response::json(
    			$tableWhereUpdate->update(['read_date' => DB::raw('NOW()')])
    		);	    	
    	}

    	$insert = array();

    	foreach ($news as $id_new)
    	{
    		$insert[] = [
    			'id_taxpayer' => $id_taxpayer,
	    		'id_news' => $id_new,
	    		'read_date' => DB::raw('NOW()')
    		];
    	}

    	return Response::json($table->insert($insert));

    }

    function unmark($news, $id_taxpayer)
    {

    	return Response::json(
    		DB::table('appweb.news_users_web')
    		->where('id_taxpayer', $id_taxpayer)
    		->whereIn('id_news', $news)
    		->update(['read_date' => DB::raw('NULL')])
	    );
    }

    function delete($news, $id_taxpayer)
    {
    	return Response::json(
    		DB::table('appweb.news_users_web')
    		->where('id_taxpayer', $id_taxpayer)
    		->whereIn('id_news', $news)
    		->update(['deleted_at' => DB::raw('NOW()')])
	    );
    }

}
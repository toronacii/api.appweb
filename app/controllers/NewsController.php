<?php

class NewsController extends BaseController {

    function get_news($id_taxpayer)
    {
    	$sql = "SELECT DISTINCT news.*, news.created::date, news_users_web.read_date
				FROM appweb.news
				INNER JOIN appweb.tax ON tax.id_tax_type = ANY(news.id_tax_types)
				LEFT JOIN appweb.news_users_web ON news_users_web.id_news = news.id AND news_users_web.id_taxpayer = tax.id_taxpayer
				WHERE tax.id_taxpayer = ?
				AND NOW()::date BETWEEN date_from AND CASE WHEN date_to ISNULL THEN '2030-12-31' ELSE date_to END
				AND news_users_web.deleted_at ISNULL
				ORDER BY news.created DESC";

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
<?php

class NewsController extends BaseController {

    function get_news($id_taxpayer)
    {
    	$sql = "SELECT DISTINCT news.id::bigint, news.id_taxpayer, news.id_tax, news.type, news.title, news.message, news.created, news.authomatic, news_users_web.read_date, taxpayer.firm_name, tax.tax_account_number
                FROM appweb.news
                INNER JOIN taxpayer ON taxpayer.id = news.id_taxpayer
                INNER JOIN appweb.tax ON tax.id = news.id_tax AND tax.id_taxpayer = ? 
                LEFT JOIN appweb.news_users_web ON news_users_web.id_news = news.id AND news_users_web.id_taxpayer = taxpayer.id
                WHERE CURRENT_DATE BETWEEN date_from AND COALESCE(date_to, '2030-12-31')
                AND news_users_web.deleted_at ISNULL
                AND news.deleted_at ISNULL
                AND news.authomatic

                UNION 

                SELECT DISTINCT news.id::bigint, news.id_taxpayer, news.id_tax, news.type, news.title, news.message, news.created, news.authomatic, news_users_web.read_date, '', ''
                FROM appweb.news
                INNER JOIN appweb.tax ON tax.id_tax_type = ANY(news.id_tax_types) AND tax.id_taxpayer = ?
                LEFT JOIN appweb.news_users_web ON news_users_web.id_news = news.id AND news_users_web.id_taxpayer = tax.id_taxpayer
                WHERE CURRENT_DATE BETWEEN date_from AND COALESCE(date_to, '2030-12-31')
                AND news_users_web.deleted_at ISNULL
                AND news.deleted_at ISNULL
                AND NOT(news.authomatic)";

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
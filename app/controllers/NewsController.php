<?php

class NewsController extends BaseController {

    function get_news($id_taxpayer)
    {
    	$sql = "SELECT * FROM (
                    SELECT DISTINCT news.id, news.type, news.title, news.message, news.created, news.id_taxpayer, news.id_tax, news.authomatic, news_users_web.read_date, taxpayer.firm_name, tax.tax_account_number
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

		return DB::select($sql);
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
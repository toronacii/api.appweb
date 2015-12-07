<?php

class DeclaracionesController extends BaseController {

	public function get_declaraciones($id_taxpayer)
	{
	    $sql = "SELECT tax.id, tax_account_number, firm_name, 
	            CASE WHEN form_number ISNULL THEN 'vacio' ELSE form_number END AS form_number, 
	            fiscal_year, type, statement.id AS id_statement, tax_total,
	            SUM(income) AS total_income,
	            CASE WHEN statement.id_user = 198 AND statement_date >= '2012-10-01'::date THEN true ELSE false END AS reimprimir
	            FROM tax
	            INNER JOIN taxpayer ON id_taxpayer = taxpayer.id AND id_taxpayer = ?
	            INNER JOIN statement ON statement.id_tax = tax.id
	            INNER JOIN statement_detail ON statement_detail.id_statement = statement.id
	            WHERE id_tax_type = 1 
	            AND id_tax_status = 1
	            AND NOT tax.canceled
	            AND NOT tax.removed
	            AND statement.status = 2
	            AND NOT statement.canceled
				AND NOT statement.estimated_sterile
	            AND type IN (0,2,3,5)
	            GROUP BY tax.id, tax_account_number, firm_name, form_number, fiscal_year, type, statement.id, tax_total, statement.id_user, statement_date
	            ORDER BY tax_account_number, fiscal_year DESC, type";
	    $return = array();
	    if ($r = DB::select($sql, array($id_taxpayer)))
	    {
	        foreach ($r AS $row)
	        {
	            switch ($row->type){
	                case 0: $type = "Estimada"; break;
	                case 2: $type = "Definitiva"; break;
	                case 3: $type = "Estimada sustitutiva"; break;
	                case 5: $type = "Definitiva sustitutiva"; break;
	                
	            }
	            $return['accounts'][$row->tax_account_number][] = (object)array(
	                'id_tax' => $row->tax_account_number,
	                'form_number' =>  $row->form_number,
	                'fiscal_year' => $row->fiscal_year,
	                'type' => $type,
	                'id_statement' => $row->id_statement,
	                'tax_total' => number_format(round($row->tax_total,2),2,',','.'),
	                'total_income' => number_format(round($row->total_income,2),2,',','.'),
	                'reimprimir' => ($row->reimprimir == 't') ? true : false
	            );
	        }
	        $return['firm_name'] = $r[0]->firm_name;
	    }

	    return Response::json($return);
	}

	public function get_statement($id_taxpayer, $id_sttm) 
	{
	    $sql = "SELECT
	    		statement.id,
	            statement.id_tax,
	            statement.form_number,
	            CASE WHEN statement.type IN (2,5) THEN 'TRUE' ELSE 'FALSE' END AS type,
	            statement.fiscal_year,
	            statement.month,
	            statement_form_ae.codval,
	            statement.statement_date,
	            statement.extemp,
	            statement.statement_date,
	            statement.change_audit,
	            tax_classifier.code, 
	            SUBSTRING(tax_classifier.name,1,38) AS name, 
	            tax_classifier.aliquot,
	            CASE WHEN COALESCE(statement.closing, false) THEN tax_classifier.minimun_taxable * statement.month ELSE tax_classifier.minimun_taxable END AS minimun_taxable,
	            statement_detail.permised,
	            statement_detail.income,
	            statement_detail.caused_tax,
	            COALESCE(statement_detail.percent_discount, 0) AS percent_discount,
	            COALESCE(statement.closing, false) AS closing
	            FROM statement
	            INNER JOIN statement_form_ae ON statement_form_ae.code = statement.form_number
	            INNER JOIN statement_detail ON statement.id = statement_detail.id_statement
	            INNER JOIN tax_classifier ON statement_detail.id_classifier_tax = tax_classifier.id
	            INNER JOIN tax ON statement.id_tax = tax.id
	            WHERE id_taxpayer = ?
	            AND statement.id = ?
	            AND NOT statement.canceled
	            AND statement.status = 2
				AND NOT statement_form_ae.canceled
	            ORDER BY permised DESC, tax_classifier.code";

	    return DB::select($sql, array($id_taxpayer, $id_sttm));
	}

	public function get_statement_tax_discount($id_sttm)
	{
		$sql = "SELECT tax_discount.amount, discount.type, discount.description
				FROM 
				tax_discount
				INNER JOIN statement ON tax_discount.id_statement_form = statement.id_statement_form
				INNER JOIN discount ON tax_discount.discount_type = discount.id
				WHERE statement.id = ?
				AND tax_discount.applied
				ORDER BY discount.type DESC";

	    return DB::select($sql, array($id_sttm));
	}

	public function datos_taxpayer($id_tax) 
	{
	    $sql = "SELECT
	            taxpayer.id,
	            tax.rent_account AS cuenta_renta,
	            tax.tax_account_number AS numero_cuenta,
	            taxpayer.firm_name AS razon_social,
	            taxpayer.corporate_name AS nombre_comercial,
	            taxpayer.rif,
	            address.address || CASE WHEN address.street IS NOT NULL THEN ', ' || address.street || ', ' || address.name ELSE '' END AS direccion,
	            inf_additional_statement_form_ae.resp_legal,
	            inf_additional_statement_form_ae.ci_resp_legal,
	            inf_additional_statement_form_ae.lat,
	            inf_additional_statement_form_ae.long,
	            users_web.local,
	            users_web.celular
	            FROM tax
	            INNER JOIN taxpayer ON tax.id_taxpayer = taxpayer.id
	            INNER JOIN tax_address ON tax.id = tax_address.id_tax
	            INNER JOIN address ON tax_address.id_address = address.id
	            INNER JOIN appweb.users_web ON users_web.id_taxpayer = taxpayer.id
	            LEFT JOIN tecnologia.inf_additional_statement_form_ae ON tax.id=inf_additional_statement_form_ae.id_tax
	            WHERE tax.id = ? ";

	    $r = DB::select($sql, array($id_tax));
	    
	    return Response::json($r[0]);
	}

	public function get_tax_unit($year) 
	{
	    $sql = "SELECT * FROM appweb.tax_unit(?)";

	    $r = DB::select($sql, array($year));
	    
	    return Response::json($r[0]);
	}

	public function get_total_sttm($id_tax, $type, $fiscal_year, $month = NULL) 
	{
	    if (strtoupper($type) == 'FALSE'){ #ESTIMADA
	        $fiscal_year = $fiscal_year - 2;
	    }
	    $sql = "SELECT tax_total, SUM(income) AS total_income
	            FROM statement
	            LEFT JOIN statement_detail ON id_statement = statement.id
	            WHERE id_tax = ?
	            AND fiscal_year = ?
	            AND CASE WHEN ? THEN type IN (0,3) ELSE type IN (2,5) END
	            AND COALESCE(month, 0) = COALESCE(?, 0)
	            AND NOT canceled
	            AND status = 2
	            AND NOT estimated_sterile
	            GROUP BY tax_total";
	    
	    $r = DB::select($sql, array($id_tax, $fiscal_year, $type, $month));

	    if ($r && isset($r[0])){
	        if (strtoupper($type) == 'TRUE') #DEFINITIVA
	            return Response::json($r[0]->tax_total);
	        
	        return Response::json($r[0]->total_income);
	    }
	    return Response::json(0);
	}

	public function get_rebajas($id_tax) 
	{
	    $sql = "SELECT DISTINCT 
	    		activity_rebates.code_new as code_new
				FROM appweb.tax_classifier(2012)
				INNER JOIN appweb.permissible_activities(?, 2012) ON permissible_activities.id_classifier_tax = tax_classifier.id
				INNER JOIN activity_rebates ON tax_classifier.code = activity_rebates.code_old
				WHERE art = 94";

	    $r = DB::select($sql, array($id_tax));
	    
	    $cod_rebajas = array();
	    for ($i = 0; $i < count($r); $i++) 
	    {
	        $cod_rebajas[$i] = $r[$i]->code_new;
	    }

	    return Response::json($cod_rebajas);
	}

	function get_declaracion($id_statement)
	{
	    $sql = "SELECT code, name, description, aliquot, income, caused_tax, permised, 
	            CASE WHEN s1.type in (0,3) THEN 'e' ELSE 'd' END as type, s1.fiscal_year AS fiscal_year_sttm,
	            s2.fiscal_year, s2.tax_total AS estimada
	            FROM statement AS s1
	            INNER JOIN statement_detail ON statement_detail.id_statement = s1.id AND s1.id = ?
	            INNER JOIN tax_classifier ON id_classifier_tax = tax_classifier.id
	            LEFT JOIN statement AS s2 ON 
	                    s1.type IN (2,5) 
	                    AND s2.type IN (0,3) 
	                    AND s2.id_tax = s1.id_tax 
	                    AND s1.fiscal_year = s2.fiscal_year
	            WHERE s1.status = 2 AND NOT s1.canceled";
	    
	    return DB::select($sql, array($id_statement));
	}

	/*function get_errors_declare($id_taxpayer, $type, $fiscal_year) 
	{
	    $sql = "SELECT tax_account_number, appweb.have_statement(tax.id,:type,:fiscal_year,FALSE) AS id_sttm_form, 
	            tax.id AS id_tax, id_message, message FROM 
	            tax
	            LEFT JOIN appweb.errors_declare_taxpayer(:id_taxpayer,:type,:fiscal_year) AS errors ON tax.id = id_tax
	            WHERE id_taxpayer = :id_taxpayer
	            AND id_tax_type = 1
	            AND id_tax_status = 1
	            AND NOT tax.canceled
	            AND NOT tax.removed
	            ORDER BY tax_account_number, id_message";
	    $r = DB::select($sql, array('id_taxpayer' => $id_taxpayer, 'type' => $type, 'fiscal_year' => $fiscal_year));

		return Response::json($this->orderErrorsDeclareTaxpayer($r));
	}*/

	function get_errors_declare_monthly($id_taxpayer, $fiscal_year, $month, $type) 
	{
	    $sql = "SELECT tax_account_number, appweb.have_statement(tax.id, :fiscal_year, :type, FALSE, :month) AS id_sttm_form, 
	            tax.id AS id_tax, id_message, message FROM 
	            tax
	            LEFT JOIN appweb.errors_declare_taxpayer_monthly(:id_taxpayer, :fiscal_year, :type, :month) AS errors ON tax.id = id_tax
	            WHERE id_taxpayer = :id_taxpayer
	            AND id_tax_type = 1
	            AND id_tax_status = 1
	            AND NOT tax.canceled
	            AND NOT tax.removed
	            ORDER BY tax_account_number, message DESC";

	    $r = DB::select($sql, ['id_taxpayer' => $id_taxpayer, 'fiscal_year' => $fiscal_year, 'month' => $month, 'type' => $type]);
	    
		return Response::json($r);	
	}

	public function get_data_statement($id_sttm_form)
	{
		$sttm_form = DB::select("SELECT id_tax, fiscal_year FROM statement_form_ae WHERE id = ?", [$id_sttm_form])[0];
		
	    $sql = "SELECT 
	            tax_classifier.id,
	            tax_classifier.code,
	            tax_classifier.name,
	            tax_classifier.description,
	            tax_classifier.aliquot,
	            tax_classifier.minimun_taxable,
	            CASE WHEN statement_form_detail.authorized THEN 't' ELSE 'f' END AS authorized,
	            statement_form_detail.monto,
	            statement_form_detail.caused_tax_form,
	            appweb.get_tax_classifier_converter(tax_classifier.id) AS ids_specialized,
	            get_last_statement_specialized.id_tax_classifier_specialized AS last_specialized,
				permissible_activities.percent_discount
	            FROM statement_form_detail
	            INNER JOIN tax_classifier ON tax_classifier.id = id_tax_classifier
				LEFT JOIN appweb.permissible_activities(:id_tax, :fiscal_year) ON permissible_activities.id_classifier_tax = tax_classifier.id
	            LEFT JOIN appweb.get_last_statement_specialized(:id_tax) ON tax_classifier.id = get_last_statement_specialized.id_tax_classifier
	            WHERE statement_form_detail.id_statement_form = :id_sttm_form
	            ORDER BY authorized DESC";
	    
	    return DB::select($sql, array('id_sttm_form' => $id_sttm_form, 'id_tax' => $sttm_form->id_tax, 'fiscal_year' => $sttm_form->fiscal_year));
	}

	public function get_activities($fiscal_year) 
	{
		$sql = "SELECT 
				tax_classifier.id,
				tax_classifier.code,
				tax_classifier.name,
				tax_classifier.description,
				tax_classifier.aliquot,
				tax_classifier.minimun_taxable,
				'f' AS authorized,
				appweb.get_tax_classifier_converter(tax_classifier.id) AS ids_specialized
				FROM appweb.tax_classifier($fiscal_year)
				ORDER BY tax_classifier.code";

	    return DB::select($sql);
	}

	public function tax_activities($id_tax, $fiscal_year = NULL) 
	{
	    $sql = "SELECT 
				tax_classifier.id,
				tax_classifier.code,
				tax_classifier.name,
				tax_classifier.description,
				tax_classifier.aliquot,
				tax_classifier.minimun_taxable,
				't' AS authorized,
				appweb.get_tax_classifier_converter(tax_classifier.id) AS ids_specialized,
				permissible_activities.percent_discount
				FROM appweb.permissible_activities(:id_tax, :fiscal_year)
				INNER JOIN appweb.tax_classifier(:fiscal_year) ON permissible_activities.id_classifier_tax = tax_classifier.id 
				ORDER BY tax_classifier.code";

	    return DB::select($sql, [ 'id_tax' => $id_tax, 'fiscal_year' => $fiscal_year ]);
	}

	public function get_tax_classifier_specialized() 
	{
		return DB::select("SELECT id, code, name, id_parent FROM appweb.tax_classifier_specialized");
	}

	public function get_children_tax_classifier_specialized($ids, $field = 'id') 
	{
	    $sql = "SELECT id, code, name 
	    		FROM appweb.tax_classifier_specialized 
	    		WHERE $field IN ($ids)";	
	    return DB::select($sql);

	    #var_dump(DB::getQueryLog(), $r); exit;
	}

	public function get_tax_discounts($id_tax, $statement_type, $fiscal_year, $month = "NULL")
	{
	    $sql = "SELECT discounts.*, name, description, type
	    		FROM appweb.generate_and_get_tax_discounts(:id_tax, :statement_type, :fiscal_year, $month) AS discounts
	    		INNER JOIN discount ON discount_type = discount.id
	    		ORDER BY type DESC";
	    	    
	   	$r = DB::select($sql, [
	   		'id_tax' => $id_tax,
	   		'statement_type' => $statement_type,
	   		'fiscal_year' => $fiscal_year
	   	]);

	    return Response::json($r);

	}
/*
	public function get_tax_discount($id_tax, $statement_type, $fiscal_year)
	{
	    $sql = "SELECT id, amount FROM tax_discount 
	    WHERE id_tax = ? 
	    AND statement_type = ?
	    AND fiscal_year = ?
	    AND discount_type = 1
	    AND NOT applied";
	    	    
	   	$r = DB::select($sql, array($id_tax, $statement_type, $fiscal_year));

	    if (count($r) > 0)
	        return Response::json($r[0]);
	    
	    return Response::json(false);

	}
*/
	public function save_statement($data){
	    
	    return DB::transaction(function() use ($data)
		{	
			extract($data['toolbar']);
		    extract($data['function']);
		    extract($data['maps']);	

		    $sql = "UPDATE appweb.users_web SET local = ?, celular = ? WHERE id_taxpayer = ?";

		    if (! DB::update($sql, array($local, $celular, $id_taxpayer)))
		    	throw new Exception('Error al actualizar teléfonos');
		    
		    $dataNew = array(
		    	'resp_legal' => $resp_legal, 
                'ci_resp_legal' => $ci_resp_legal
		    );

		    if ($lat) $dataNew['lat'] = $lat;
		    if ($lat) $dataNew['long'] = $long;
		    if ($json_gm) $dataNew['json_gm'] = $json_gm;

		    if (! $result = DB::table('tecnologia.inf_additional_statement_form_ae')->where('id_tax', $id_tax)->update($dataNew))
		    {
		    	$result = DB::table('tecnologia.inf_additional_statement_form_ae')->insert($dataNew + array('id_tax' => $id_tax));
		    }
		    
		    if (! $result)
		    	throw new Exception('Error al insertar información adicional');
		   #dd($id_tax);
		    $sql = "SELECT * FROM appweb.save_statement($id_tax, $fiscal_year, '$type', $month, '$activities'::text[][], $discount::text[][])";
		    
		    $r = DB::select($sql);

		    #GUARDAR ESPECIFICACIÓN DE ACTIVIDADES

		    if (($id_statement_form = $r[0]->save_statement) > 0 && $data['activities_specified']){

		    	$dataNew = array();
		        foreach ($data['activities_specified'] as $id_tax_classifier => $id_tax_classifier_specialized){
		        	$dataNew[] = array(
		        		'id_statement_form' => $id_statement_form,
		        		'id_tax_classifier' => $id_tax_classifier,
		        		'id_tax_classifier_specialized' => $id_tax_classifier_specialized
		        	);
		        }

		        if (! $r = DB::table('appweb.statement_specialized')->insert($dataNew))
		        	throw new Exception('Error al insertar actividades especializadas');

		    }

		    return Response::json($id_statement_form);

		});
		
	}

	public function liquid_statement($id_sttm_form) {

	    $sql = "SELECT * FROM appweb.liquid_statement($id_sttm_form)";
	    	 
	 	if ($r = DB::select($sql))
	 		 return Response::json($r[0]->liquid_statement);
	 	return Response::json(false);

	}

	public function get_sttm_sumary($id_tax, $fiscal_year) 
	{
		$sql = "SELECT SUM(tax_total) AS sttm_sumary
				FROM statement
				WHERE id_tax = :id_tax
				AND NOT canceled
				AND COALESCE(month, 0) > 0
				AND fiscal_year = :fiscal_year
				AND type IN (2, 5)
				AND status = 2
				AND NOT COALESCE(closing, false)";

		if ($r = DB::select($sql, ['id_tax' => $id_tax, 'fiscal_year' => $fiscal_year])) {
			return Response::json($r[0]->sttm_sumary);
		}
		return Response::json(0);
	}

}
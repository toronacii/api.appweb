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
	            statement.id_tax,
	            statement.form_number,
	            CASE WHEN statement.type IN (2,5) THEN 'TRUE' ELSE 'FALSE' END AS type,
	            statement.fiscal_year,
	            statement_form_ae.codval,
	            statement.statement_date,
	            statement.extemp,
	            statement.statement_date,
	            statement.change_audit,
	            statement.id_tax_discount,
	            tax_discount.amount AS amount_discount,
	            tax_classifier.code, 
	            SUBSTRING(tax_classifier.name,1,38) AS name, 
	            tax_classifier.aliquot,
	            tax_classifier.minimun_taxable,
	            statement_detail.permised,
	            statement_detail.income,
	            statement_detail.caused_tax
	            FROM statement
	            INNER JOIN statement_form_ae ON statement_form_ae.code = statement.form_number
	            INNER JOIN statement_detail ON statement.id = statement_detail.id_statement
	            INNER JOIN tax_classifier ON statement_detail.id_classifier_tax = tax_classifier.id
	            INNER JOIN tax ON statement.id_tax = tax.id
	            LEFT JOIN tax_discount ON statement.id_tax_discount = tax_discount.id
	            WHERE id_taxpayer = ?
	            AND statement.id = ?
	            AND NOT statement.canceled
	            AND statement.status = 2
				AND NOT statement_form_ae.canceled
	            ORDER BY permised DESC, tax_classifier.code";

	    return DB::select($sql, array($id_taxpayer, $id_sttm));
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

	public function get_total_sttm($id_tax, $type, $fiscal_year) 
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
	            AND NOT canceled
	            AND status = 2
	            AND NOT estimated_sterile
	            GROUP BY tax_total";
	    
	    $r = DB::select($sql, array($id_tax, $fiscal_year, $type));

	    if ($r && isset($r[0])){
	        if (strtoupper($type) == 'TRUE') #DEFINITIVA
	            return Response::json($r[0]->tax_total);
	        
	        return Response::json($r[0]->total_income);
	    }
	    return Response::json(0);
	}

	public function get_rebajas($id_tax) 
	{
	    $sql = "SELECT
	            distinct(activity_rebates.code_new) as code_new
	            FROM
	            public.tax_classifier
	            INNER JOIN public.permissible_activities ON public.permissible_activities.id_classifier_tax = public.tax_classifier.id
	            INNER JOIN activity_rebates ON public.tax_classifier.code=activity_rebates.code_old
	            WHERE
	            public.tax_classifier.code NOT LIKE '%.%' AND
	            art = 94 AND
	            public.permissible_activities.id_tax = ?";
	    $r = DB::select($sql, array($id_tax));
	    $cod_rebajas = array();
	    for ($i = 0; $i < count($r); $i++) {
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

	function get_errors_declare($id_taxpayer, $type, $fiscal_year) 
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
	}

	function get_errors_declare_monthly($id_taxpayer, $type, $fiscal_year, $month = NULL) 
	{
	    $sql = "SELECT tax_account_number, appweb.have_statement(tax.id,:type,:fiscal_year,FALSE) AS id_sttm_form, 
	            tax.id AS id_tax, id_message, message FROM 
	            tax
	            LEFT JOIN appweb.errors_declare_taxpayer_monthly(:id_taxpayer,:type,:fiscal_year,:month) AS errors ON tax.id = id_tax
	            WHERE id_taxpayer = :id_taxpayer
	            AND id_tax_type = 1
	            AND id_tax_status = 1
	            AND NOT tax.canceled
	            AND NOT tax.removed
	            ORDER BY tax_account_number, message DESC";
	    $r = DB::select($sql, array('id_taxpayer' => $id_taxpayer, 'type' => $type, 'fiscal_year' => $fiscal_year, 'month' => $month));
	    
		return Response::json($r);	
	}

	public function get_data_statement($id_sttm_form) 
	{
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
	            appweb.get_tax_classifier_converter(tax_classifier.id) AS ids_specialized
	            FROM statement_form_detail
	            INNER JOIN tax_classifier ON tax_classifier.id = id_tax_classifier
	            WHERE id_statement_form = ?
	            ORDER BY authorized DESC";
	    
	    return DB::select($sql, array($id_sttm_form));
	}

	public function get_activities($fiscal_year = NULL) 
	{
		$operator = "!=";
		if ($fiscal_year <= 2010)
		{
			$operator = "=";
		}
	    $sql = "SELECT 
				tax_classifier.id,
				tax_classifier.code,
				tax_classifier.name,
				tax_classifier.description,
				tax_classifier.aliquot,
				tax_classifier.minimun_taxable,
				appweb.get_tax_classifier_converter(tax_classifier.id) AS ids_specialized
				FROM tax_classifier 
				WHERE id_tax_type = 1
				AND attribute_classifier = 4
				AND parent_level $operator 689
				ORDER BY tax_classifier.code";

	    return DB::select($sql);
	}

	public function tax_activities($id_tax, $fiscal_year = NULL) 
	{
		$operator = "!=";
		if ($fiscal_year <= 2010)
		{
			$operator = "=";
		}
	    $sql = "SELECT 
				tax_classifier.id,
				tax_classifier.code,
				tax_classifier.name,
				tax_classifier.description,
				tax_classifier.aliquot,
				tax_classifier.minimun_taxable,
				appweb.get_tax_classifier_converter(tax_classifier.id) AS ids_specialized
				FROM permissible_activities
	            INNER JOIN tax_classifier ON permissible_activities.id_classifier_tax = tax_classifier.id 
				WHERE id_tax_type = 1
				AND attribute_classifier = 4
				AND parent_level $operator 689
				AND permissible_activities.id_tax = ? 
				ORDER BY tax_classifier.code";

	    return DB::select($sql, array($id_tax));
	}

	public function get_children_tax_classifier_specialized($ids, $field = 'id') 
	{
	    $sql = "SELECT id, code, name 
	    		FROM appweb.tax_classifier_specialized 
	    		WHERE $field IN ($ids)";	
	    return DB::select($sql);

	    #var_dump(DB::getQueryLog(), $r); exit;
	}

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

		    $sql = "SELECT * FROM appweb.save_statement($id_tax, $type, $fiscal_year, '$activities'::text[][], $month)";
		    
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

		    #GUARDAR DESCUENTO
		    if (isset($data['tax_discount']))
		    {
		        $keys = array_keys($data['tax_discount']);
		        $id_tax_discount = $keys[0];
		        $amount = $data['tax_discount'][$id_tax_discount];

		        if (! $r = DB::table('tax_discount')->where('id', $id_tax_discount)->update(array('amount' => $amount)))
		        	throw new Exception('Error al actualizar descuentos');
		        if (! $r = DB::update("UPDATE statement_form_ae SET tax_total_form = tax_total_form - $amount WHERE id = $id_statement_form"))
		        	throw new Exception('Error al actualizar statement_form_ae');
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

}
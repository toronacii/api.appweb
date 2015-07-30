<?php

class TramitesController extends BaseController {

	public function have_tasa_paid($id_tax)
	{
		$sql = "SELECT tasas_tramites.id
				FROM appweb.tasas_tramites
				INNER JOIN invoice ON invoice.id = tasas_tramites.id_invoice
				INNER JOIN invoice_fee ON invoice_fee.id_invoice = invoice.id
				INNER JOIN appweb.tax ON tax.id = tasas_tramites.id_tax
				WHERE NOT tasas_tramites.used 
				AND invoice.status in (6,4)
				AND id_fee_type = CASE WHEN tax.id_tax_type = 1 THEN 6 ELSE 5 END
				AND tasas_tramites.id_tax = ?
				ORDER BY tasas_tramites.created
				LIMIT 1";

		if ($r = DB::select($sql, array($id_tax)))
		{
			return Response::json($r[0]->id);
		}

		return Response::json(false);

	}

	public function declaraciones_anteriores($id_tax)
	{
		$sql = "SELECT * FROM appweb.statement_missing($id_tax)";

		$r = DB::select($sql);
		$resp = array();
		foreach ($r as $i => $v)
			$resp[] = $i + 1 . ". $v->statement_missing";

		return Response::json($resp);
	}

	public function esta_solvente($id_tax)
	{
		$sql = "SELECT appweb.total_debito($id_tax)";

		$r = DB::select($sql);

		return Response::json($r[0]->total_debito <= 0);
	}

	public function cadastral_number_actualized($id_tax)
	{
		$sql = "SELECT COUNT(id) AS total
	            FROM cadastre.cadastral_document
	            WHERE id_tax = ?
	            AND status_rent = 3
	            AND NOT canceled
	            AND EXTRACT('YEAR' FROM date) >= 2013";

        $r = DB::select($sql, array($id_tax));

        return Response::json($r[0]->total > 0);
	}

	public function insert_request_solvencia($id_tax)
	{
		$sql = "SELECT appweb.insert_request($id_tax) AS id_request";

		$r = DB::select($sql);

		return Response::json($r[0]->id_request);
	}

	public function get_data_tramite($id_request)
	{
		$sql = "SELECT request_date,
				request_code,
				tax_account_number,
				request_type.name AS request_type,
				tax_type.name AS tax_type
				FROM request 
				INNER JOIN request_type ON id_request_type = request_type.id
				INNER JOIN appweb.tax ON id_tax = tax.id
				INNER JOIN tax_type ON tax.id_tax_type = tax_type.id
				WHERE request.id = ?";

		if ($r = DB::select($sql, array($id_request)))
		{
			return Response::json($r[0]);
		}
		
		return Response::json(false);
	}

	public function get_fiscal($cedula) {
    
		$sql = "SELECT system_user.first_name  || ' ' || system_user.last_name AS nombre,
	            CASE WHEN prosecutor.tipo = 2 THEN 'Fiscal' WHEN  prosecutor.tipo = 1 THEN 'Jefe Division' END AS tipo,
	            prosecutor.grupo
	            FROM tecnologia.prosecutor
	            INNER JOIN system_user on system_user.id = id_user
	            WHERE status = 1
	            AND prosecutor.status = 1
	            AND system_user.identity_card = ? ";
	    
	    $r = DB::select($sql, array($cedula));

	    if (isset($r[0]))
	    	return Response::json($r[0]);

	    return Response::json(false);
	    
	}

	public function get_solvencias_taxpayer($id_taxpayer)
	 {
			  $sql = "SELECT request.id,
			    tax.id AS id_tax,
			    tax.tax_account_number, 
			    CASE WHEN request.status = 'Impreso' THEN 'Listo para retirar' ELSE request.status END AS status,
			    request.id_request_type,
			    request.request_code, 
			    request.request_date, 
			    tax.id_tax_type
			    FROM request
			    INNER JOIN appweb.tax ON id_tax = tax.id
			    WHERE tax.id_taxpayer = ?
			    AND request.is_web
			    AND NOT request.deleted
			    ORDER BY id_tax_type, request_date DESC";
			  
			  /*$return = array();

			  if ($r = DB::select($sql, array($id_taxpayer)))
			  {
			   foreach ($r as $obj)
			   {
			    $return[$obj->id_tax_type][] = $obj;
			   }
			  }

	     return Response::json($return);*/

	     return DB::select($sql, array($id_taxpayer));
	 }


	// Historicos de Retiros
	public function get_retiro_taxpayer($id_taxpayer)
	{
		$sql = "SELECT request.id,
					tax.id AS id_tax,
					tax.tax_account_number, 
					request.id_request_type,
					request.request_code, 
					request.request_date, 
					tax.id_tax_type,
					status_request.name AS status
					FROM appweb.request  
					INNER JOIN appweb.tax ON id_tax = tax.id
					INNER JOIN appweb.status_request ON status_request.id = request.id_status_request
					WHERE tax.id_taxpayer = ?
					AND NOT request.deleted
					ORDER BY id_tax_type, request_date DESC";
		
	    return DB::select($sql, array($id_taxpayer));
	}


	#PROCEDIMIENTOS ADMINISTRATIVOS

	public function get_procedimiento_auditoria($id_taxpayer)
	{
		$sql = "SELECT auditoria.id, tax.id AS id_tax, tax.tax_account_number, n_orden, hist_status_auditoria.status as status_auditoria, id_tax_type
				FROM tecnologia.auditoria
				LEFT JOIN tecnologia.hist_status_auditoria ON id_hist_status_auditoria = hist_status_auditoria.id
				LEFT JOIN appweb.tax ON tax.id = auditoria.id_tax
				WHERE tax.id_taxpayer = ?
				AND hist_status_auditoria.status IS NOT NULL
				AND auditoria.active = '1'
				AND auditoria.status_caso != 0";

		return DB::select($sql, array($id_taxpayer));
	}

	public function get_procedimiento_fiscalizacion($id_taxpayer)
	{
		$sql = "SELECT matriz.id,
				tax.id AS id_tax,
				matriz.procedimiento AS tipo,
				matriz.nro_procedimiento AS n_procedimiento,
				matriz.fecha_elaboracion AS fecha,
				matriz.resultado AS status,
				prosecutor.first_name||' '||prosecutor.last_name as fiscal_asignado,
				tax.tax_account_number,
				tax.id_tax_type
				FROM tecnologia.matriz
				LEFT JOIN tecnologia.case as c ON c.document_number = matriz.nro_procedimiento
				LEFT JOIN tecnologia.prosecutor ON prosecutor.id_user = matriz.fiscal_actuante
				LEFT JOIN appweb.tax ON matriz.id_tax = tax.id
				LEFT JOIN taxpayer ON tax.id_taxpayer = taxpayer.id
				LEFT JOIN tecnologia.process ON c.id = process.id_case
				LEFT JOIN tecnologia.result ON c.id = result.id_case
				WHERE tax.id_taxpayer = ? 
				AND matriz.resultado IS NOT NULL
				AND (process.notification='s' OR result.notified ='s')
				AND tecnologia.matriz.resultado  != 'Caso cerrado'
				ORDER BY matriz.fecha_elaboracion";

		return DB::select($sql, array($id_taxpayer));

	}

	public function get_procedimiento_catastro($id_taxpayer)
	{
		$sql = "SELECT id, id_tax, tax_account_number, cadastral_number, type, status, id_tax_type
				FROM appweb.get_cadastral_document 
				WHERE id_taxpayer = ?
				AND status IS NOT NULL";
		return DB::select($sql, array($id_taxpayer));
	}

	public function post_retiro($id_tax, $id_taxpayer)
	{
			$sql = "SELECT appweb.insert_request_retiro($id_tax) AS id_request";

			$r = DB::select($sql);

			return Response::json($r[0]->id_request);

	}

	public function get_data_request_retiro($id_request)
	{
			#dd($id_request);
		$sql = "SELECT request_date,
				request_code,
				tax_account_number,
				appweb.request_type.name AS request_name,
				tax_type.name AS tax_type
				FROM appweb.request 
				INNER JOIN appweb.request_type ON  request_type.code = id_request_type
				INNER JOIN appweb.tax ON id_tax = tax.id
				INNER JOIN tax_type ON tax.id_tax_type = tax_type.id
				WHERE request.id = ?";

		if ($r = DB::select($sql, array($id_request)))
		{
			return Response::json($r[0]);
		}
		
		return Response::json(false);
	}


	public function have_statement($id_tax, $closing = false)
	{

		$sql = "SELECT * FROM appweb.have_statement(:id_tax, TRUE, :year, TRUE, :month, :closing)";

		$r = DB::select($sql, [
			'id_tax' => $id_tax,
			'year' => (int)date('Y'),
			'month' => (int)date('m'),
			'closing' => $closing
		]);

		return Response::json($r[0]->have_statement > 0);
	}

	#PROCEDIMIENTOS ADMINISTRATIVOS PARA VALIDAR RETIRO

	/*public function get_procedimiento_auditoria_retiro($id_tax)
	{
		$sql = "SELECT auditoria.id, tax.id AS id_tax, tax.tax_account_number, n_orden, hist_status_auditoria.status as status_auditoria, id_tax_type
				FROM tecnologia.auditoria
				LEFT JOIN tecnologia.hist_status_auditoria ON id_hist_status_auditoria = hist_status_auditoria.id
				LEFT JOIN appweb.tax ON tax.id = auditoria.id_tax
				WHERE tax.id = ?
				AND hist_status_auditoria.status IS NOT NULL
				AND auditoria.active = '1'
				AND auditoria.status_caso != 0";

		return DB::select($sql, array($id_taxpayer));
	}
*/
	public function get_procedimiento_auditoria_retiro($id_tax)
	{
		$sql = "SELECT * 
				id,
				order_number,
				fiscal_act_number
				FROM audit.audits 
				WHERE deleted_at IS NULL 
				AND id_tax= ?";

		return DB::select($sql, array($id_taxpayer));
	}

	public function get_procedimiento_fiscalizacion_retiro($id_tax)
	{
		$sql = "SELECT matriz.id,
				tax.id AS id_tax,
				matriz.procedimiento AS tipo,
				matriz.nro_procedimiento AS n_procedimiento,
				matriz.fecha_elaboracion AS fecha,
				matriz.resultado AS status,
				prosecutor.first_name||' '||prosecutor.last_name as fiscal_asignado,
				tax.tax_account_number,
				tax.id_tax_type
				FROM tecnologia.matriz
				LEFT JOIN tecnologia.case as c ON c.document_number = matriz.nro_procedimiento
				LEFT JOIN tecnologia.prosecutor ON prosecutor.id_user = matriz.fiscal_actuante
				LEFT JOIN appweb.tax ON matriz.id_tax = tax.id
				LEFT JOIN taxpayer ON tax.id_taxpayer = taxpayer.id
				LEFT JOIN tecnologia.process ON c.id = process.id_case
				LEFT JOIN tecnologia.result ON c.id = result.id_case
				WHERE tax.id = ? 
				AND matriz.resultado IS NOT NULL
				AND (process.notification='s' OR result.notified ='s')
				AND tecnologia.matriz.resultado  != 'Caso cerrado'
				ORDER BY matriz.fecha_elaboracion";

		return DB::select($sql, array($id_taxpayer));

	}

}
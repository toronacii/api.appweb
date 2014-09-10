<?php

class PlanillasController extends BaseController {

	public function infocontribuyente($cuentarenta) 
	{
		if (strlen($cuentarenta) == 9) {
			$tipo = "tax_account_number";
	    } else if (strlen($cuentarenta) == 16) {
	        $tipo = "rent_account";
	    } else{
	    	return Response::json();
	    }

	    $sql = "SELECT taxpayer.id AS sujeto,
			    tax.id AS tributo,
			    rent_account,
			    tax_account_number,
		        address,
		        rif,
		        firm_name,
		        corporate_name,
		        real_initial_date AS initial_date,
		        registration_date,
		        id_tax_type AS hechoimponible 
		        FROM  appweb.tax 
		        INNER JOIN taxpayer ON taxpayer.id = tax.id_taxpayer
		        WHERE $tipo = ?";

	    return DB::select($sql, array($cuentarenta));
	}

	public function buscar_clasificadores($id_tax)
	{
		if (date('Y') < 2011){
			$year = 2010;
		}else{
			$year = 2011;
		}

		$sql = "SELECT tax_classifier.code, 
				tax_classifier.name AS nombre, 
				tax_classifier.aliquot, 
				tax_classifier.minimun_taxable AS minimo 
				FROM tax_classifier 
				INNER JOIN permissible_activities ON permissible_activities.id_classifier_tax = tax_classifier.id
				WHERE permissible_activities.id_tax = ? 
				AND permissible_activities.fiscal_year = ?";

		return DB::select($sql, array($id_tax, $year));
	}

	public function buscar_clasificadores2($id_tax)
	{
		$sql = "SELECT tax_classifier.code, 
				tax_classifier.name AS nombre, 
				tax_classifier.aliquot, 
				tax_classifier.minimun_taxable AS minimo 
				FROM tax_classifier 
				INNER JOIN appweb.tax ON tax.id_tax_classifier = tax_classifier.id
				WHERE tax.id = ?";

		return DB::select($sql, array($id_tax));
	}

	public function buscar_campos($id_tax)
	{
		$sql = "SELECT additional_field_tax_type.name as nombre, 
				additional_value_tax.integer_value as valor1, 
				additional_value_tax.string_value as valor2, 
				additional_value_tax.float_value as valor3, 
				additional_value_tax.boolean_value as valor4, 
				additional_value_tax.date_value as valor5, 
				additional_field_tax_type.data_type as tipo
				FROM additional_value_tax  
				INNER JOIN additional_field_tax_type ON additional_value_tax.id_additional_field_tax_type = additional_field_tax_type.id
				WHERE additional_value_tax.id_tax = ? ";

		return DB::select($sql, array($id_tax));
	}

	public function estado_cuenta($id_tax, $fecha = NULL)
	{	
		$sql = "SELECT application_date, 
			  	reference_code, 
			  	concept, 
			  	expiry_date, 
			  	debito, 
			  	credito, 
			  	canceled
				FROM appweb.estado_cuenta(?, ?)";

		return DB::select($sql, array($id_tax, $fecha));
	}

	public function tipos_tasas($id_tax_type, $associate = NULL) 
	{
	    $id_tax_type = implode(',',$id_tax_type);
	    $where = ($associate) ? "AND associate = 1 " : "";

	    $sql = "SELECT fee_type.id,
                fee_type.name,
                fee_type.tax_unit,
                fee_tax.id_tax_type,
                tax_unit.id AS id_tax_unit,
                tax_unit.value
	            FROM fee_type
	            INNER JOIN tecnologia.fee_tax ON fee_tax.id_fee_type = fee_type.id
	            INNER JOIN appweb.tax_unit(" . date('Y') . ") AS tax_unit ON 1 = 1
	            WHERE NOT fee_type.deleted
	            AND id_tax_type IN ($id_tax_type)
	            $where
	            ORDER BY id_tax_type, fee_type.name";

	    $r = DB::select($sql);

	    foreach ($r as $v){
	        $n[$v->id] = $v;
	    }

	    return Response::json($n);
	}

	public function generar_planilla_tasa($id_tax, $id_fee_type) {

	    $sql = "SELECT appweb.generar_planilla_tasa($id_tax, ?) AS id_invoice";

	    $r = DB::select($sql, array($id_fee_type));

	    return Response::json($r[0]->id_invoice);
	}

	public function data_pdf_invoice($id_invoice) 
	{
	    $sql = "SELECT 
	            total_amount, 
	            invoice.expiry_date, 
	            validation_code, 
	            invoice_number,
	            emision_date, 
	            invoice_type,
	            tax.id_taxpayer,
	            firm_name,
	            rif,
	            address,
	            rent_account,
	            tax_account_number,
	            ppd.discount_amount,
	            discount_percent,
	            tax_type.name AS tax_type
	            FROM invoice
	            LEFT JOIN appweb.tax ON invoice.id_tax = tax.id
	            LEFT JOIN taxpayer ON tax.id_taxpayer = taxpayer.id
	            LEFT JOIN tax_type ON id_tax_type = tax_type.id
	            LEFT JOIN prompt_payment_discount ppd ON invoice.id = ppd.id_invoice
	            WHERE invoice.id = ?";

	    $r = DB::select($sql, array($id_invoice));

	    if ($r[0]->invoice_type == 1){ # PLANILLA DE TASA

	        $sql1 = "SELECT invoice.emision_date AS application_date,
	                expiry_date,
	                name AS concept,
	                total_amount AS amount
	                FROM invoice
	                INNER JOIN invoice_fee ON id_invoice = invoice.id
	                INNER JOIN fee_type ON id_fee_type = fee_type.id
	                WHERE invoice.id = ?
	                ORDER BY invoice.emision_date";

	    }else{ #PLANILLA DE IMPUESTOS

	        $sql1 = "SELECT application_date, 
	                expiry_date, 
	                concept, 
	                invoice_transaction.amount 
	                FROM invoice_transaction
	                INNER JOIN transaction ON id_transaction = transaction.id
	                WHERE id_invoice = ?
	                ORDER BY application_date";
	    }

	    $r1 = DB::select($sql1, array($id_invoice));

	    $resp = array(
	        'metadata' => $r[0],
	        'cargos' => $r1
	    );

	    return Response::json($resp);

	}

	public function get_cargos_taxpayer($id_taxpayer, $id_tax = NULL)
	{
		$sql = "SELECT * FROM appweb.cargos_taxpayer(?, NULL)";

		#CÓDIGO PARA CONTRIBUYENTE EVENTUAL
		if ($id_tax)
		{
			$sql .= " WHERE id_tax = $id_tax";
		}

		$n = array();

	    $r = DB::select($sql, array($id_taxpayer));

        foreach ($r as $v)
        {	            
            $n[$v->id_tax][] = $v;
        }
	        
        return Response::json($n);
	    
	}

	public function generar_planilla_impuesto($id_tax, $ids_transaction) 
	{
	    $sql = "SELECT appweb.generar_planilla_impuesto(?, '$ids_transaction'::bigint[]) AS id_invoice";

	    $r = DB::select($sql, array($id_tax));

	    return Response::json($r[0]->id_invoice);
	}

	public function cuentas_usuario_unificada($id_taxpayer) 
	{
		$sql = "SELECT * FROM (
	                SELECT id_tax,
	                SUM(CASE WHEN application_date <= now()::date THEN amount ELSE 0 END) AS fecha_actual,
	                SUM(amount) AS fecha_completa
	                FROM appweb.cargos_taxpayer(?, NULL)
	                GROUP BY id_tax
	            ) as t
	            WHERE fecha_actual > 0 OR fecha_completa > 0
	            ORDER BY fecha_actual DESC";

	    $r = DB::select($sql, array($id_taxpayer));

	    $n = array();

        foreach ($r as $v)
        {
	        $n[$v->id_tax] = $v;
	    }

	    return Response::json($n);
	}

	public function generar_planilla_unificada($id_taxpayer, $id_taxes) 
	{
	    $sql = "SELECT appweb.generar_planilla_unificada(?, '$id_taxes') AS id_invoice";

	    $r = DB::select($sql, array($id_taxpayer));

	    return Response::json($r[0]->id_invoice);
	}

	public function get_detail_planilla_unificada($id_invoice) 
	{
	    $sql = "SELECT tax_account_number, name, invoice_tax.amount, discount_porcent, discount 
	            FROM invoice_tax 
	            INNER JOIN appweb.tax ON tax.id = invoice_tax.id_tax
	            INNER JOIN tax_type ON tax.id_tax_type = tax_type.id
	            WHERE id_invoice = ?";

	    return DB::select($sql, array($id_invoice));
	}

	public function get_header_planilla_unificada($id_invoice) 
	{
	    $sql = "SELECT total_amount, expiry_date, validation_code, invoice_number, firm_name, corporate_name, rif, id_taxpayer, emision_date,
	            regexp_replace(address.address || CASE WHEN address.street IS NOT NULL THEN ', ' || address.street || ', ' || address.name ELSE '' END, E'[\\n\\r\\t]+', ' ', 'g') AS address
	            FROM invoice
	            INNER JOIN taxpayer ON id_taxpayer = taxpayer.id
	            LEFT  JOIN address  ON taxpayer.id_address = address.id
	            WHERE invoice.id = ?";
	    $r = DB::select($sql, array($id_invoice));

	    return Response::json(@$r[0]);
	}

	public function get_planillas_pago($id_taxpayer, $id_tax = NULL)
	{
		$where = ($id_tax) ? "AND tax.id = $id_tax " : "";
	    $sql = "SELECT 
	            invoice.id,
	            CASE WHEN invoice_type = 5 THEN '-' ELSE tax_account_number END tax_account_number,
	            invoice.invoice_number, 
	            invoice.emision_date,
	            CASE WHEN payment.id ISNULL THEN invoice.expiry_date ELSE payment.date END AS date,
	            invoice.total_amount,
	            invoice_type,
	            CASE WHEN invoice_type = 1 THEN 'tasa'
	            	 WHEN invoice_type = 2 THEN 'impuesto'
	            	 WHEN invoice_type = 5 THEN 'unificada'
	           	END AS tipo,
	            CASE WHEN payment.id ISNULL THEN 'no pagada' ELSE 'pagada' END AS status,
	            EXTRACT (EPOCH FROM age(invoice.expiry_date, CURRENT_DATE)) < 0 AS vencida
	            FROM invoice
	            LEFT JOIN payment on payment.id_invoice=invoice.id AND payment.status = 2
	            LEFT JOIN tax on tax.id=invoice.id_tax 
	            WHERE invoice.id_taxpayer = ? 
	            AND invoice.status IN (6,4,1)
	            AND CASE WHEN invoice_type != 5 THEN invoice.id_tax IS NOT NULL ELSE true END 
	            $where
	            ORDER BY invoice.emision_date DESC, tax_account_number";

	    return DB::select($sql, array($id_taxpayer));
	}

	public function delete_invoice($id_invoice)
	{
		DB::transaction(function() use ($id_invoice)
		{			
			$sql = "UPDATE invoice SET status = 3 WHERE id = ? /*AND status = 1*/";

			if (! DB::update($sql, array($id_invoice)))
				throw new Exception('No se ha cambiado el status');

			$sql = "UPDATE prompt_payment_discount SET canceled = true WHERE id = ?";
 			
 			DB::update($sql, array($id_invoice));

		});

		return Response::json(true);

	}

}
<?php

class GestionUsuarioController extends BaseController {

	public function existe_email_usuario($email)
	{
		return UserWeb::where('email', $email)->count();
	}

	public function send_email_WS($data) 
	{
		extract($data);
		$emailSends = true;

		if (isset($email))
			$emails[] = $email;

		foreach ($emails as $email) 
		{
			$emailSends &= Mail::send($view, $data, function ($message) use ($email) 
			{   
				$message->subject("Oficina Virtual - Alcaldía del Municipio Sucre");
				if (isset($subject))
			    	$message->subject($subject);
			    $message->to($email);
			});
		}

		return Response::json($emailSends);

	}

	public function ultima_planilla_pagada($id_taxpayer) 
	{
	    $sql = "SELECT tax_account_number, invoice_number, payment.created as emision_date
	            FROM invoice
	            INNER JOIN appweb.tax ON tax.id=invoice.id_tax
	            inner join payment on payment.id_invoice=invoice.id
	            WHERE tax.id_taxpayer= ?
	            AND tax.id_tax_type = 1
	            AND invoice.status = 6
	            AND invoice.invoice_type in(2,3)
	            ORDER BY tax_account_number, payment.created DESC";

	    $r = DB::select($sql, array($id_taxpayer));
	    
	    $final = new StdClass();

	    $final->posee_planillas = FALSE;
	    if (count($r) > 0) {
	        $tax_account_number_random = $r[rand(0, count($r) - 1)]->tax_account_number; //CUENTA AL AZAR
	        $esta_listo = FALSE;
	        foreach ($r as $objR) {
	            if ($objR->tax_account_number == $tax_account_number_random) {
	                if (!$esta_listo)
	                    $emision_date_mayor = $objR->emision_date; //FECHA MAYOR
	                if ($objR->emision_date == $emision_date_mayor)
	                    $invoices[] = $objR->invoice_number;
	                $esta_listo = TRUE;
	            }else if ($esta_listo)
	                break;
	        }
	        $final->posee_planillas = TRUE;
	        $final->tax_account_number = $tax_account_number_random;
	        $final->invoice = $invoices;
	    }
	    return Response::json($final);
	}


	function ultimo_numero_declaracion($id_taxpayer) 
	{
	    $sql = "SELECT tax_account_number, form_number, statement_date AS emision_date
	            FROM statement
	            INNER JOIN appweb.tax ON tax.id = id_tax
	            WHERE id_taxpayer = ?
	            AND statement.status = 2 
	            AND NOT statement.canceled
	            ORDER BY tax_account_number, emision_date DESC";

	    $r = DB::select($sql, array($id_taxpayer));
	    $final = new StdClass();

	    $final->posee_planillas = FALSE;
	    if (count($r) > 0) {
	        $tax_account_number_random = $r[rand(0, count($r) - 1)]->tax_account_number; //CUENTA AL AZAR
	        $esta_listo = FALSE;
	        foreach ($r as $objR) {
	            if ($objR->tax_account_number == $tax_account_number_random) {
	                if (!$esta_listo)
	                    $emision_date_mayor = $objR->emision_date; //FECHA MAYOR
	                if ($objR->emision_date == $emision_date_mayor)
	                    $statements[] = $objR->form_number;
	                $esta_listo = TRUE;
	            }else if ($esta_listo)
	                break;
	        }
	        $final->posee_planillas = TRUE;
	        $final->tax_account_number = $tax_account_number_random;
	        $final->statement = $statements;
	    }
	    return Response::json($final);
	}

	public function registrar_contribuyente($datos_insertar) {

	    #var_dump($datos_insertar); exit;
	    extract($datos_insertar['datos_basicos']);
	    $id_taxpayer = $datos_insertar['id_taxpayer'];

	    $campos = 'cedula';
	    $valores = "'$cedula'";    

	    $insert = array(
	    	'id_taxpayer'   => $id_taxpayer,
	    	'nombres' 	    => $nombres,
	    	'apellidos'     => $apellidos,
	    	'local' 	    => $tlf_local,
	    	'celular' 	    => $tlf_celular,
	    	'email' 	    => $email,
	    	'password'	    => sha1($pass),
	    	'register_from' => 0
	    );

	    if ($tipo_persona == 'juridica') 
	    {
	    	$insert = $insert + array(
	    		'tipo_persona' => 'f',
	    		'razon_social' => $razon_social,
	    		'rif' => $rif
	    	);
	    }
	    else
	    {
	    	$insert = $insert + array(
	    		'cedula' => $cedula
	    	);
	    }

	    $id = DB::table('appweb.users_web')->insertGetId($insert);

	    if (is_array($datos_insertar['cuentas_reportadas'])) 
	    {
	        foreach ($datos_insertar['cuentas_reportadas'] as $iObj => $objCuenta) 
	        {
	            foreach ($objCuenta as $cuenta) 
	            {
	                $insert2[] = array(
	                	'id_taxpayer'  => $id_taxpayer,
	                	'type_account' => ($iObj == 'cuentarenta') ? 0 : 1,
	                	'account' => $cuenta
	                );
	                
	            }
	        }
	        DB::table('appweb.tax_reported')->insert($insert2);
	    }

	    return Response::json($id);
	
	}

	public function validar_usuario_email($id_user, $hash) 
	{
	    $sql = "SELECT appweb.validar_usuario_email($id_user,'$hash') AS resp";
	    return DB::select(DB::raw($sql));
	}

	public function existe_contribuyente($tipoCuenta, $numero) 
	{
	    $campoCuenta = ($tipoCuenta == "cuentanueva") ? "tax_account_number" : "rent_account";
	    $id_taxpayer = "(SELECT id_taxpayer FROM tax WHERE UPPER(public.tax.$campoCuenta)='$numero')";

	    $sql = "SELECT tax.id_taxpayer, tax.rent_account,tax.tax_account_number, users_web.id AS id_users_web, tax.id_tax_type
	            FROM appweb.tax
	            INNER JOIN taxpayer ON tax.id_taxpayer = taxpayer.id
	            LEFT JOIN appweb.users_web ON users_web.id_taxpayer = taxpayer.id 
	            WHERE public.taxpayer.id = $id_taxpayer
	            ORDER BY tax_account_number";

	    $r = DB::select(DB::raw($sql));

	    $return['usado'] = false;
	    $return['cuentas'] = array();
	    if (count($r) > 0) {
	        if ($r[0]->id_users_web) {
	            $return['usado'] = true;
	        } else {
	            $return['tiene_AE'] = 0;
	            $return['id_taxpayer'] = $r[0]->id_taxpayer;
	            #var_dump($r);
	            foreach ($r as $iObj => $obj) {
	            	$return['cuentas'][$iObj] = new stdClass();
	                $return['cuentas'][$iObj]->rent_account = $obj->rent_account;
	                $return['cuentas'][$iObj]->tax_account_number = $obj->tax_account_number;
	                if ($obj->id_tax_type == 1) { //ACTIVIDADES ECONOMICAS
	                    $return['tiene_AE'] = 1;
	                }
	            }
	        }
	    }

	    return Response::json($return);
	}

	public function tax($tax_account_number)
	{
		$sql = "SELECT 
		id_taxpayer,
		tax.id AS id_tax,
		tax_account_number,
		rent_account,
		real_initial_date AS initial_date,
		address,
		id_tax_type,
		tax_type.name
		FROM appweb.tax
		INNER JOIN tax_type ON tax_type.id = id_tax_type
		WHERE tax_account_number = ?";

		$tax = DB::select($sql, array($tax_account_number));

		if (isset($tax[0]))
			return Response::json($tax[0]);

		return Response::json(false);	
	}

	public function update_user($id, $update)
	{
		return UserWeb::where('id', $id)->update($update);
	}


}
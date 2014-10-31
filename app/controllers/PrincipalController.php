<?php

class PrincipalController extends BaseController {

	public function valid_user($user)
	{
		$sql = "SELECT id, 
		id_taxpayer, 
		password, 
		confirmed_email, 
		nombres,
		apellidos,
		email,
		tipo_persona,
		CASE WHEN tipo_persona THEN cedula ELSE rif END AS ced_rif,
		local,
		celular
		FROM appweb.users_web 
		WHERE UPPER(email) = ?";

		if ($results = DB::select($sql, array(strtoupper($user))))
			return Response::json($results[0]);

		return Response::json();
	}

	public function taxpayer($id_taxpayer)
	{
		$sql = "SELECT 
		taxpayer.id AS id_taxpayer,
		firm_name,
		rif,
		regexp_replace(address.address || CASE WHEN address.street IS NOT NULL THEN ', ' || address.street || ', ' || address.name ELSE '' END, E'[\\n\\r\\t]+', ' ', 'g') AS address
		FROM taxpayer
		LEFT JOIN address ON taxpayer.id_address = address.id
		WHERE taxpayer.id = ?";

		return Response::json(DB::select($sql, array($id_taxpayer))[0]);

	}

	public function taxes($id_taxpayer)
	{
		$sql = "SELECT 
		tax.id AS id_tax,
		tax_account_number,
		rent_account,
		real_initial_date AS initial_date,
		address,
		id_tax_type,
		tax_type.name--,
		-- appweb.total_debito(tax.id) AS total_edocuenta,
		-- appweb.total_debito_completo(tax.id) AS total_edocuenta2
		FROM appweb.tax
		INNER JOIN tax_type ON id_tax_type = tax_type.id
		WHERE id_taxpayer = ?";

		$taxes = DB::select($sql, array($id_taxpayer));

		$return = array();

		foreach ($taxes as $tax){
			$return[$tax->id_tax] = $tax;
		}

		return Response::json($return);	
	}

	public function edo_cuenta($id_taxpayer)
	{
		$sql = "SELECT 
		tax.id AS id_tax,
		tax_account_number,
		rent_account,
		real_initial_date AS initial_date,
		address,
		id_tax_type,
		tax_type.name,
		appweb.total_debito(tax.id) AS total_edocuenta,
		appweb.total_debito_completo(tax.id) AS total_edocuenta2
		FROM appweb.tax
		INNER JOIN tax_type ON id_tax_type = tax_type.id
		WHERE id_taxpayer = ?";

		$taxes = DB::select($sql, array($id_taxpayer));

		$return = array();

		foreach ($taxes as $tax){
			$return[$tax->id_tax] = $tax;
		}

		return Response::json($return);	
	}

	public function cambiar_password($email, $pass) 
	{
        return UserWeb::where(DB::raw('UPPER(email)'), '=', strtoupper($email))->update(array('password' => sha1($pass)));
    }

    public function save_user_login($id_users_web, $ip)
    {
    	return Response::json(
    		DB::table('appweb.users_web_login')->insert(['id_users_web' => $id_users_web, 'ip_address' => $ip])
    	);
    }

}
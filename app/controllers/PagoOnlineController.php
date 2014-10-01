<?php 

class PagoOnlineController extends BaseController {

	public function get_data_invoice($id_invoice)
	{
		$sql = "SELECT invoice_number, total_amount AS amount, CURRENT_DATE > expiry_date AS expired, estado, invoice.validation_code, users_web.email
				FROM invoice
				INNER JOIN appweb.users_web ON invoice.id_taxpayer = users_web.id_taxpayer
				LEFT JOIN appweb.online_payment ON id_invoice = invoice.id AND estado = 'A'
				WHERE invoice.id = ?";

		$r = DB::select($sql, array($id_invoice));

		return Response::json($r[0]);
	}	

	public function set_online_payment($data)
	{
		$op = new OnlinePayment();
		foreach ($data as $index => $value)
		{
			$op->$index = $value;
		}
		return Response::json($op->save());
	}

	public function update_online_payment($data)
	{
		$op = OnlinePayment::whereControl($data['control'])->first();
		foreach ($data as $index => $value)
		{
			$op->$index = $value;
		}
		if ($op->save())
			return Response::json($op);

		return Response::json(false);
	}

	public function get_payment_by_control($control)
	{
		$sql = "SELECT online_payment.*, invoice.status
				FROM appweb.online_payment
				INNER JOIN invoice ON id_invoice = invoice.id
				WHERE control = ?";

		$r = DB::select($sql, array($control));

		return Response::json(@$r[0]);
	}

}
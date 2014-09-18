<?php 

class PagoOnlineController extends BaseController {

	private function get_data_megasoft($url, $options)
	{
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/xml', "Authorization: Basic {$options->credentials}"));
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$resp = curl_exec($ch);
		curl_close($ch);

		return $resp;
	}

	private function get_url_pre_register($invoice_number, $amount, $options)
	{	
		
		$params =  array('@cod_afiliacion', '@factura', '@monto');
		$replace = array($options->cod_affiliate, $invoice_number, number_format($amount, 2, '.', ''));

		return str_replace($params, $replace, $options->pre_register);
	}

	private function xml_to_object($xml)
	{
		$object = simplexml_load_string($xml);
		$object->descripcion = (string)$object->descripcion;
		$object->voucher = (string)$object->voucher;

		return $object;
	}

	private function private_verifier($control, $options)
	{
		$url = str_replace('@control', $control, $options->verifier);
		$xml = $this->get_data_megasoft($url, $options);
		return $this->xml_to_object($xml);
	}

	public function verifier($control, $options)
	{
		return Response::json($this->private_verifier($control, $options));
	}

	public function pre_register($id_invoice, $pagina, $test = TRUE)
	{
		$online_payment = OnlinePayment::whereIdInvoice($id_invoice)->orderBy('created', 'DESC')->get();

		$options = (object)(($test) ? Config::get('megasoft-development') : Config::get('megasoft-production'));

		if ($online_payment)
		{
			$lists = $online_payment->lists('estado', 'control');
			#APROBADO
			if (in_array('A', $lists))
			{
				return Response::json("Pago Aprobado");
			}

			#PENDIENTE
			$control = array_search('P', $lists);

			if ($control && $this->private_verifier($control, $options)->estado == 'P')
			{
				return Response::json($control);
			}
		}

		$invoice = Invoice::find($id_invoice);
		$email = UserWeb::whereIdTaxpayer($invoice->id_taxpayer)->pluck('email');

		# CREAR VARIABLES DE PAGO EN LÍNEA (cod_affiliate, credentials, pre_register, verifier)
		
		$pre_register = $this->get_url_pre_register($invoice->invoice_number, $invoice->total_amount, $options);
		$control = $this->get_data_megasoft($pre_register, $options);

		#CREAMOS OBJETO PARA GUARDAR DATOS DEL PAGO
		$online_payment = New OnlinePayment();
		$online_payment->id_invoice = $id_invoice;
		$online_payment->control = ($control) ? $control : NULL;
		$online_payment->factura = $invoice->invoice_number;
		$online_payment->monto = $invoice->total_amount;
		$online_payment->estado = 'P';
		$online_payment->pagina = $pagina;
		$online_payment->correo = $email;
		$online_payment->validation_code = $invoice->validation_code;

		$online_payment->save();

		return Response::json($control);
	}

}
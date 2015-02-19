<?php

return array(
	'cod_affiliate' => '1214449',
	'credentials'   => base64_encode('alcaldiasucre01:Analisis2015-+'),
	'pre_register'  => 'https://payment.megasoft.com.ve/payment/action/paymentgatewayuniversal-prereg?cod_afiliacion=@cod_afiliacion&factura=@factura&monto=@monto',
	'verifier'      => 'https://payment.megasoft.com.ve/payment/action/paymentgatewayuniversal-querystatus?control=@control'
);
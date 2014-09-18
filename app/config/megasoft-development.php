<?php

return array(
	'cod_affiliate' => '27112013',
	'credentials'   => base64_encode("alcaldiasucre01:Alcaldia2013-+"),
	'pre_register'  => 'https://200.71.151.226:8443/payment/action/paymentgatewayuniversal-prereg?cod_afiliacion=@cod_afiliacion&factura=@factura&monto=@monto',
	'verifier'      => 'https://200.71.151.226:8443/payment/action/paymentgatewayuniversal-querystatus?control=@control'
);
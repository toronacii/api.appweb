<?php
	switch ($estado)
	{
		case 'P' : $titulo = 'Transacción pendiente'; break;
		case 'A' : $titulo = 'Transacción aprobada'; break;
		case 'R' : $titulo = 'Transacción rechazada'; break;
	}
?>

<div style="width:100%" align="center">
	<p align="left">
		<strong>Estimado Contribuyente:</strong>
		@if ($estado == 'A')
		Gracias por utilizar nuestra Oficina Virtual. Su transacción ha sido completada y su pago fue compensado exitosamente
		@elseif ($estado == 'R')
		Su transacción no ha sido aprobada, verifique que los datos colocados son los correctos o comuníquese con el banco emisor de su tarjeta.
		@endif
	</p>
	<div style="background-color:#f7b82b">
		<p><font size="4;" color="#CC3300">{{ $titulo }}</font></p>
	</div>
	<p><font size="3" color="#000000">Referencia: {{ $control }}</font></p>
	<table width="100%" border="2">
		<tbody>
			<tr>
				<td width="218" align="center"><font color="#F47B20; size=2"><strong>Nº Planilla</strong></font></td>
				<td width="222" align="center"><font color="#000000; size=2"><strong>{{ $factura }}</strong></font></td>
			</tr>
			<tr>
				<td align="center"><font color="#F47B20; size=2"><strong>Monto</strong></font></td>
				<td align="center"><font color="#000000; size=2"><strong>{{ number_format($monto, 2, ',', '.') }}</strong></font></td>
			</tr>
			@if ($estado == 'A')
			<tr>
				<td align="center"><font color="#F47B20; size=2"><strong>Fecha</strong></font></td>
				<td align="center"><font color="#000000; size=2"><strong>{{ date('d/m/Y', strtotime(($date_compensate) ? $date_compensate : $created)) }}</strong></font></td>
			</tr>
			@endif
			@if ($estado == 'R')
			<tr>
				<td align="center"><font color="#F47B20; size=2"><strong>Descripción</strong></font></td>
				<td align="center"><font color="#000000; size=2"><strong>{{ $descripcion or $titulo }}</strong></font></td>
			</tr>
			@endif
		</tbody>
	</table>
	<p><img src="http://www.alcaldiamunicipiosucre.gov.ve/contenido/wp-content/uploads/2010/10/as_rentas-sucre-300x300.jpg" width="150" height="150" align="center"></p>
</div>
<div align="left" style="width:600px">
	<font color="#009900"><strong>Imprima solo si es necesario, cuidemos nuestro planeta</strong></font>
</div>

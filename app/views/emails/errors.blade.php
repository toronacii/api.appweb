<h3>[{{ date('d/m/Y H:i:s') }}]</h3>
<table style="border-collapse:collapse;" border="1">
	<tr>
		<td>Error</td>
		<td>{{ $error->getMessage() }}</td>
	</tr>
	<tr>
		<td>Archivo</td>
		<td>{{ $error->getFile() }}</td>
	</tr>
	<tr>
		<td>Linea</td>
		<td>{{ $error->getLine() }}</td>
	</tr>
	@if (isset($params) && $params)
	<tr>
		<td>Data</td>
		<td><?php s($params) ?></td>
	</tr>
	@endif
	<tr>
		<td>Server</td>
		<td><?php s(Request::server()) ?></td>
	</tr>
	<tr>
		<td>Trace</td>
		<td><pre>{{ $trace }}</pre></td>
	</tr>
</table>
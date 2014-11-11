<h3>{{ $server . " : " . date('d/m/Y H:i:s') }}</h3>

<table style="border-collapse:collapse;" border="1">
	<tr>
		<td>Tipo</td>
		<td>{{ $severity }}</td>
	</tr>
	<tr>
		<td>Error</td>
		<td>{{ $error }}</td>
	</tr>
	<tr>
		<td>Archivo</td>
		<td>{{ $filepath }}</td>
	</tr>
	<tr>
		<td>Linea</td>
		<td>{{ $line }}</td>
	</tr>
	@if (isset($session))
	<tr>
		<td>Session</td>
		<td><pre>{{ s($session) }}</pre></td>
	</tr>
	@endif
</table>


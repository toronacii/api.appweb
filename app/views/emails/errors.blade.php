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
	@if ($params)
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
		<td colspan="2">
			<table>
				<tr>
					<tr>{{ $error->xdebug_message }}</tr>
				</tr>
			</table>
		</td>
	</tr>
</table>
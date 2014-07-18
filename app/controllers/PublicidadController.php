<?php

class PublicidadController extends BaseController {

	public function get_publicidad()
	{
		$sql = "SELECT id_tax_type, id_tax_classifier,name, description, code, formula, cant_unit, min_taxable 
	            FROM tax_classifier 
	            INNER JOIN appweb.calc_publicidad ON id_tax_classifier = tax_classifier.id
	            WHERE active AND id_tax_type IN (4,5)
	            ORDER BY id_tax_classifier";

	    $r = DB::select($sql);

	    $return = array();
	    foreach ($r as $v){
	    	$return[($v->id_tax_type == 4) ? "fija" : "eventual"][] = $v;
	    }

	    return Response::json($return);
	}

}
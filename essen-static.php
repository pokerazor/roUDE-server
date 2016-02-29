<?php
error_reporting (E_ALL);

$LOGADATEI_NAME="log-static.htm";
$LOG_PREFIX = strftime ("%A, %e. %B %Y %H:%M:%S (%Z)", time()).": ".$_SERVER['QUERY_STRING']. "(".$_SERVER['HTTP_USER_AGENT'].")";

$logdatei = fopen($LOGADATEI_NAME,"a+");
fputs($logdatei, $LOG_PREFIX);
fputs($logdatei, "<br /> \r\n");

$GEOJSON_FILE_NAME="export-essen-static.geojson.js";


$overpassQuery='[out:json][bbox:51.403061,6.726379,51.489507,7.076569];(way[amenity=university]);(node(w)[entrance]);out;';
$overpassUrl='http://overpass-api.de/api/interpreter';
$querystr='?data='.urlencode($overpassQuery);

//$GEOJSON_FILE_NAME=$overpassUrl.$querystr;

//echo '<a href="'.$GEOJSON_FILE_NAME.'">'.$GEOJSON_FILE_NAME.'</a><br>';

$geojsonContent= file_get_contents($GEOJSON_FILE_NAME);
//echo "<pre>".htmlspecialchars($geojsonContent)."</pre><br>";

$phpObj = json_decode ( $geojsonContent, false, 512 );
$jsonexp = json_encode ( $phpObj, 0 );
$allResults=$phpObj->features;

$transferArray = Array ();

if (json_last_error ()) {	
	fputs ( json_last_error_msg () );
	fputs ( $logdatei, "<br /> \r\n" );
} else {
	foreach ( $allResults as $curMarker ) {
		if(!isset($curMarker->properties->name)){
			$curMarker->properties->name=".";
		}
		
		$newElement = array (
				'id' => $curMarker ->id.'',
				'lat' => $curMarker->geometry->coordinates[1],
				'lng' => $curMarker->geometry->coordinates[0],
				'elevation' => 0,
 									
				'title' => $curMarker->properties->name,
				'area' => 0,
				'details' => '',
		);
		
		$transferArray [] = $newElement;
	}
	
	$output= array(
	'status'=> 'OK',
	'num_results'=> count($transferArray),
	'results'=> $transferArray);
	header("Content-Type: application/json");
 	echo json_encode ( $output, 0 );
}
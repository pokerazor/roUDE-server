<?php
header("Content-Type: application/json");

include("phpfastcache/phpfastcache.php");
$cache = phpFastCache("files");
error_reporting (E_ALL);

$CACHE_KEY ="overpassResult";

$logMessage="";

if($_SERVER["HTTP_CACHE_CONTROL"]=="no-cache" || $_SERVER["HTTP_PRAGMA"]=="no-cache"){
// 	$cache->delete($CACHE_KEY);
// 	$cache->delete($CACHE_KEY.$queryParams['cacheKey']);
	$cache->clean(); //clear complete cache, necessary if using argument-dependant cache key
	$logMessage.='cache cleaned'.', ';
}

$queryParams=getQueryParams();

$logMessage.= stripLineBreaks(var_export($queryParams,true)).', ';

$cacheResult = $cache->get($CACHE_KEY.$queryParams['cacheKey']);

if($cacheResult == null) {	
	$logMessage.= 'not in cache'.', ';
	//$overpassQuery='[out:json][bbox:51.403061,6.726379,51.489507,7.076569];(way[building=university]);(node(w)[entrance]);out;';
	$overpassQuery='
[out:json];
(way(around:'.$queryParams['range'].','.$queryParams['lat'].','.$queryParams['lng'].')[building=university]);
(node(w)[entrance]);
out;
';
	$overpassUrl='http://overpass-api.de/api/interpreter';
	$querystr='?data='.urlencode($overpassQuery);
 	$logMessage.= stripLineBreaks($overpassQuery). ', ';
	$GEOJSON_FILE_NAME=$overpassUrl.$querystr;
	$logMessage.= $GEOJSON_FILE_NAME. ', ';
	
// 	echo '<a href="'.$GEOJSON_FILE_NAME.'">'.$GEOJSON_FILE_NAME.'</a><br>';
	
	$geojsonContent= file_get_contents($GEOJSON_FILE_NAME);
	//echo "<pre>".htmlspecialchars($geojsonContent)."</pre><br>";
	
	$phpObj = json_decode ( $geojsonContent, false, 512 );
	$jsonexp = json_encode ( $phpObj, 0 );
	$allResults=$phpObj->elements;
	
	$transferArray = Array ();
	
	if (json_last_error ()) {
 		$logMessage.= 'json_last_error_msg='.json_last_error_msg ().',';
	} else {
		foreach ( $allResults as $curMarker ) {
			if(!isset($curMarker->tags->name)){
				$curMarker->tags->name=".";
			}
	
			$newElement = array (
					'id' => $curMarker ->id.'',
					'lat' => $curMarker->lat,
					'lng' => $curMarker->lon,
					'elevation' => 0,
	
					'title' => $curMarker->tags->name,
					'area' => 0,
					'details' => '',
			);
	
			$transferArray [] = $newElement;
		}
	
		$output= array(
				'status'=> 'OK',
				'num_results'=> count($transferArray),
				'results'=> $transferArray);
		
		$output = json_encode ( $output, 0 );
		
 		$logMessage.= 'num_results='.count($transferArray).', ';
	
		// Write to cache to save API calls next time
		$cache->set($CACHE_KEY.$queryParams['cacheKey'], $output, 600);// set to cache for 600 seconds = 10 minutes and 0 = never expired
	}
} else {
 	$logMessage.= 'was cached'.', ';
	
	$output=$cacheResult;
}
writeLogMessage($logMessage);
echo  $output;

function getQueryParams(){
	$queryParams=array('lat'=>51.46184,'lng'=>7.01655,'range'=>5000.0); //set defaults (Campus Schützenbahn, 5km)
	
	if(isset($_GET['latitude'])){
		$queryParams['lat']=min(90.0,max(-90.0,floatval($_GET['latitude']))); // clean request latitude to be float between -90 and 90 (degrees)
	}
	if(isset($_GET['longitude'])){
		$queryParams['lng']=min(180.0,max(-180.0,floatval($_GET['longitude']))); // clean request longitude to be float between -180 and 180 (degrees)
	}
	if(isset($_GET['radius'])){
		$queryParams['range']=min(20.0,max(0.0,floatval($_GET['radius']))); // clean request range to be float between 0 and 20 (km)
		$queryParams['range']=$queryParams['range']*1000; // HTTP URL query parameter (&radius=) given as km, but Overpass needs m
	}
	
	//round for better cacheablility, range to 100m and lat and lon to 3 decimal places (accuracy around 100m), see https://wiki.openstreetmap.org/wiki/DE:Genauigkeit_von_Koordinaten#Genauigkeit_der_Breite 
	
	$queryParams['range']=round($queryParams['range'], -2);
	$queryParams['lat']=round($queryParams['lat'], 3);
	$queryParams['lng']=round($queryParams['lng'], 3);
	
	$queryParams['cacheKey']=$queryParams['lat'].','.$queryParams['lng'].','.$queryParams['range'];
	
	return $queryParams;
}


function writeLogMessage($logMessage=""){
	$LOGADATEI_NAME="log-dynamic.htm";
	$LOG_PREFIX = strftime ("%A, %e. %B %Y %H:%M:%S (%Z)", time()).": ".$_SERVER['QUERY_STRING']. "(".$_SERVER['HTTP_USER_AGENT'].");";
	$logdatei = fopen($LOGADATEI_NAME,"a+");
	fputs($logdatei, $LOG_PREFIX);
	fputs($logdatei, $logMessage);
	fputs($logdatei, "<br /> \r\n");
}

function stripLineBreaks($inputStr){
	$outputStr=str_replace(array("\r", "\n"), '', $inputStr);
	return $outputStr;
}

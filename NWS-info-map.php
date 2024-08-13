<?php
#################################################################################
#
#  NWS Zone coverage displays with  Leaflet Map
#
#  This program is based on WxForecastMap.php by Curly of ricksturf.com, and has
#  been completely rewritten to use Leaflet/OpenStreetMaps and api.weather.gov
#  for the data (instead of Google map/forecast.weather.gov/MapClick.php XML)
#  by Ken True - webmaster@saratoga-weather.org
#
#  NWS-info-map.php - 13-Aug-2024 - initial release
# 
#  Version 1.00 - 13-Aug-2024 - initial release
#
$Version = "nws-info-map.php - V1.00 - 13-Aug-2024";
#################################################################################
#  error_reporting(E_ALL);        // uncomment to turn on full error reporting
#  ini_set('display_errors','1'); // uncomment to turn on full error reporting 
#
# script available at http://github.com/ktrue/NWS-info-map
#  
# you may copy/modify/use this script as you see fit,
# no warranty is expressed or implied.
# Usage:
#  you can use this webpage standalone (customize the HTML portion below)
#  or you can include it in an existing page:
/*
<?php
  $doInclude = true;
  include("nws-info-map.php");
?> 
*/
#
# settings: --------------------------------------------------------------------
# if you are using www.mapbox.com for map tiles, you
# need to acquire an API ke from that service
#
#  put this in the CALLING page for nws-info-map.php script:
/*
  $setMapboxAPIkey = '-replace-this-with-your-API-key-here-'; 
*/
# Note: if using the Saratoga template set, put a new entry in Settings.php
/*

$SITE['mapboxAPIkey'] = '-replace-this-with-your-API-key-here-';

*/
# and you won't need to change the $mapAPI value above (nor any of the other
# settings in the script below.
# 
#  change myLat, myLong to your station latitude/longitude, 
#  set $ourTZ to your time zone
#    other settings are optional
#
#  set to initial location latitude/longitude (decimal degrees)
  $myLat = 37.2747;    //North=positive, South=negative decimal degrees
  $myLong = -122.0229;   //East=positive, West=negative decimal degrees
# The above settings are for saratoga-weather.org location
  $ourTZ = "America/Los_Angeles";  //NOTE: this *MUST* be set correctly to
# translate UTC times to your LOCAL time for the displays.
# Use https://www.php.net/manual/en/timezones.php to find the timezone suitable for
#  your location.
#
#  pick a format for the time to display ..uncomment one (or make your own)
# $timeFormat = 'D, Y-m-d H:i:s T';  // Fri, 2006-03-31 14:03:22 TZone
#  $timeFormat = 'D, d-M-Y g:i:s a T';  // Fri, 31-Mar-2006 4:03:22 am TZone
  $timeFormat = 'g:i a T M d, Y';  // 10:30 am CDT March 31, 2018
 
	# see: http://leaflet-extras.github.io/leaflet-providers/preview/ for additional maps
	# select ONE map tile provider by uncommenting the values below.
	
	$mapProvider = 'Esri_WorldTopoMap'; // ESRI topo map - no key needed
	#$mapProvider = 'OSM';     // OpenStreetMap - no key needed
	#$mapProvider = 'Terrain'; // Terrain map by stamen.com - no key needed
	#$mapProvider = 'OpenTopo'; // OpenTopoMap.com - no key needed
	#$mapProvider = 'Wikimedia'; // Wikimedia map - no key needed
# 
	#$mapProvider = 'MapboxSat';  // Maps by Mapbox.com - API KEY needed in $mapboxAPIkey 
	#$mapProvider = 'MapboxTer';  // Maps by Mapbox.com - API KEY needed in $mapboxAPIkey 
	$mapboxAPIkey = '--mapbox-API-key--';  
	# use this for the API key to MapBox
  $mapZoomDefault = 9;  // =11; default Leaflet Map zoom entry for display (1=world, 14=street)

  $cacheFileDir = './cache/';  // to store JSON files
  $refreshTime  = 24*60*60;         // cache file life expectancy in seconds (one day default)
	
# end of settings -------------------------------------------------------------

if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
   //--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain;charset=ISO-8859-1");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
}
// overrides from Settings.php if available
 if(file_exists("Settings.php")) {include_once("Settings.php");}
//if(file_exists("common.php"))   {include_once("common.php");}
global $SITE;
if (isset($SITE['latitude'])) 	     {$myLat = $SITE['latitude'];}
if (isset($SITE['longitude'])) 	     {$myLong = $SITE['longitude'];}
if (isset($SITE['cityname'])) 	     {$ourLocationName = $SITE['cityname'];}
if (isset($SITE['tz']))              {$ourTZ = $SITE['tz']; }
if (isset($SITE['timeFormat']))      {$timeFormat = $SITE['timeFormat'];}
if (isset($SITE['mapboxAPIkey']))    {$mapboxAPIkey = $SITE['mapboxAPIkey']; }
if (isset($SITE['cacheFileDir']))    {$cacheFileDir = $SITE['cacheFileDir']; }
// end of overrides from Settings.php

// overrides from including page if any
if (isset($setMapZoomDefault))  { $mapZoomDefault = $setMapZoomDefault; }
if (isset($setDoLinkTarget))    { $doLinkTarget = $setDoLinkTarget; }
if (isset($setLatitude))        { $myLat = $setLatitude; }
if (isset($setLongitude))       { $myLong = $setLongitude; }
if (isset($setLocationName))    { $ourLocationName = $setLocationName; }
if (isset($setTimeZone))        { $ourTZ = $setTimeZone; }
if (isset($setTimeFormat))      { $timeFormat = $setTimeFormat; }
if (isset($setMapProvider))     { $mapProvider = $setMapProvider; }
if (isset($setMapboxAPIkey))    { $mapboxAPIkey = $setMapboxAPIkey; }
// ------ start of code -------

if(!isset($mapboxAPIkey)) {
	$mapboxAPIkey = '--mapbox-API-key--';
}
$includeMode = (isset($doInclude) and $doInclude)?true:false;

# table of available map tile providers
$mapTileProviders = array(
  'OSM' => array( 
	   'name' => 'Street',
	   'URL' =>'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
		 'attrib' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Points &copy 2012 LINZ',
		 'maxzoom' => 18
		  ),
  'Wikimedia' => array(
	  'name' => 'Street2',
    'URL' =>'https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png',
	  'attrib' =>  '<a href="https://wikimediafoundation.org/wiki/Maps_Terms_of_Use">Wikimedia</a>',
	  'maxzoom' =>  18
    ),		
  'Esri_WorldTopoMap' =>  array(
	  'name' => 'Terrain',
    'URL' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
	  'attrib' =>  'Tiles &copy; <a href="https://www.esri.com/en-us/home" title="Sources: Esri, DeLorme, NAVTEQ, TomTom, Intermap, iPC, USGS, FAO, NPS, NRCAN, GeoBase, Kadaster NL, Ordnance Survey, Esri Japan, METI, Esri China (Hong Kong), and the GIS User Community">Esri</a>',
	  'maxzoom' =>  18
    ),
	'OpenTopo' => array(
	   'name' => 'Topo',
		 'URL' =>'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
		 'attrib' => ' &copy; <a href="https://opentopomap.org/">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>) | Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors.',
		 'maxzoom' => 15
		  ),
	'MapboxTer' => array(
	   'name' => 'Terrain3',
		 'URL' =>'https://api.mapbox.com/styles/v1/mapbox/outdoors-v10/tiles/256/{z}/{x}/{y}?access_token='.
		 $mapboxAPIkey,
		 'attrib' => '&copy; <a href="https://mapbox.com">MapBox.com</a> | Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors.',
		 'maxzoom' => 18
		  ),
	'MapboxSat' => array(
	   'name' => 'Satellite',
		 'URL' =>'https://api.mapbox.com/styles/v1/mapbox/satellite-streets-v10/tiles/256/{z}/{x}/{y}?access_token='.
		 $mapboxAPIkey,
		 'attrib' => '&copy; <a href="https://mapbox.com">MapBox.com</a> | Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors.',
		 'maxzoom' => 18
		  ),
			
	);


if(!$includeMode) { // emit only if full page is generated
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="viewport" content="initial-scale=1.0" />
<link rel="stylesheet" href="nws-info-map.css" />
<title>NWS Information Map</title>
<style type="text/css">
<!--
body,td,th {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 12px;
}
.graytitles {
  font-size:large;
  text-align:center;
  margin-top: 5px;
}
-->
</style>
</head>
<body>
<?php } // end !$includeMode ?>
<div>
<p> </p>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.5.1/leaflet.js" type="text/javascript"></script>
<script src="https://unpkg.com/leaflet-geosearch@3.6.0/dist/geosearch.umd.js" type="text/javascript"></script>
<?php

$centerlat = $myLat;
$centerlong=  $myLong;
$zoom = $mapZoomDefault;

global $Status;
$errorMessage = '';
 
$Status = "<!-- $Version -->\n";

if(isset($_REQUEST['zoom']) and is_numeric($_REQUEST['zoom'])) {
	$zoom = $_REQUEST['zoom'];
}
if(isset($_REQUEST['llc'])) {
	list($centerlat,$centerlong) = explode(',',$_REQUEST['llc']);
}

if(isset($_REQUEST['map'])) {
	$reqMap = $_REQUEST['map'];
}
$doDebug = isset($_REQUEST['debug'])?true:false;

# for easy testing (part 1)
if(isset($_REQUEST['latlong'])) {
	$t = $_REQUEST['latlong'];
	if(preg_match('!^([\d\.]+),-([\d\.]+)$!',$t,$m)) {
		$centerlat = $m[1];
		$centerlong= $m[2];
		$centerlong= 0.0-$centerlong;
		$Status .= "<!-- using latlong=$centerlat,$centerlong -->\n";
	}
}
# for easy testing (part 2)
if(isset($_REQUEST['lat']) and is_numeric($_REQUEST['lat']) and
   isset($_REQUEST['lon']) and is_numeric($_REQUEST['lon']) ) {
		 $centerlat = $_REQUEST['lat'];
		 $centerlong= $_REQUEST['lon'];
		 $Status .= "<!-- using lat=$centerlat and long=$centerlong -->\n";
}

# for easy testing (part 3) - fudge the gridpoint number
if(isset($_REQUEST['adj'])) {
	$t = $_REQUEST['adj'];
	if(preg_match('!^([\-\d\.]+),([\-\d\.]+)$!',$t,$m)) {
		$adj1 = $m[1];
		$adj2 = $m[2];
		$Status .= "<!-- using adj=$adj1,$adj2 -->\n";
	}
} else {
	$adj1 = 0;
	$adj2 = 0;
}

# listing of current WFO gridpoint forecasts with +1,+1 offsets to correct  20-Oct-2022
#$offByOne = array('AFC','AER','ALU','AFG','AJK','BOX','CAE','DLH','FSD','HGX','HNX','LIX','LWX','MAF','MFR','MLB','MRX','MTR');
  
$offByOne = array('NIL'); 
$fcstZoneURL   = 'http://nosite/none';
$fcstZone      = 'none';
$countyZoneURL = 'http://nosite/none';
$countyZone    = 'none';
$fireZoneURL   = 'http://nosite/none';
$fireZone      = 'none';
  
$pointHTML = WXmap_fetchUrlWithoutHanging('https://api.weather.gov/points/'.$centerlat.','.$centerlong);
	$long = round($centerlong,4);
	$lat  = round($centerlat,4);
  $stuff = explode("\r\n\r\n",$pointHTML); // maybe we have more than one header due to redirects.
  $content = (string)array_pop($stuff); // last one is the content
  $headers = (string)array_pop($stuff); // next-to-last-one is the headers
  preg_match('/HTTP\/\S+ (\d+)/', $headers, $m);
	//$Status .= "<!-- m=".print_r($m,true)." -->\n";
	//$Status .= "<!-- html=".print_r($html,true)." -->\n";
	if(isset($m[1])) {$lastRC = (string)$m[1];} else {$lastRC = '0';}

if($lastRC == '200') { // got a good return .. process
	$rawpointJSON = json_decode($content,true);
	$pointJSON = $rawpointJSON['properties'];
	$gridPointMeta = $pointJSON['gridId'].'/'.$pointJSON['gridX'].','.$pointJSON['gridY'];
	
	if($doDebug) {$Status .= "<!-- Point content\n".var_export($pointJSON,true)." -->\n";}
	
	$fcstURL = $pointJSON['forecast'];
	$ngpID = $pointJSON['gridId'];
	if(in_array($ngpID,$offByOne)) {
		print "<!-- WFO=$ngpID is +1,+1 offset, adjusted down by -1,-1 -->\n";
		$adj1 = -1;
		$adj2 = -1;
	}
	$ngpX  = $pointJSON['gridX']+$adj1;
	$ngpY  = $pointJSON['gridY']+$adj2;
	
	$fcstURL = "https://api.weather.gov/gridpoints/$ngpID/$ngpX,$ngpY";
	$gridPointMeta = $ngpID.'/'.$ngpX.','.$ngpY;
	           
	$fcstZoneURL = $pointJSON['forecastZone'];
	$fcstZone = get_zone($fcstZoneURL);
	$countyZoneURL = isset($pointJSON['county'])?$pointJSON['county']:'http://nosite/none';
	$countyZone = get_zone($countyZoneURL);
	$fireZoneURL = isset($pointJSON['fireWeatherZone'])?$pointJSON['fireWeatherZone']:'http://nosite/none';
	$fireZone = get_zone($fireZoneURL);
	$cityname = $pointJSON['relativeLocation']['properties']['city'];
	$statename= $pointJSON['relativeLocation']['properties']['state'];
	$TZ       = $pointJSON['timeZone'];
	date_default_timezone_set($TZ);
	$distanceFrom = '';
	if (isset($pointJSON['relativeLocation']['properties']['distance']['value'])) {
		$distance = $pointJSON['relativeLocation']['properties']['distance']['value'];
		$distance = floor(0.000621371 * $distance); // truncate to nearest whole mile
		$Status.= "<!-- distance=$distance from " . $cityname . " -->\n";
		if ($distance >= 2) {
			$angle = $pointJSON['relativeLocation']['properties']['bearing']['value'];
			$compass = array('N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW');
			$direction = $compass[round($angle / 22.5) % 16];
			$t = $distance . ' ';
			$t.= ($distance > 1) ? "Miles" : "Mile";
			$t.= " $direction ";
			$distanceFrom = $t. ' from ';
		}
	}
	
	$Status .=  "<!-- fcstURL='$fcstURL' -->\n";
} else { //not a good return
  $errorMessage .= "<h2>Oops... unable to fetch the point location data RC=$lastRC.<br/>";
  if($lastRC == '404') {
    $errorMessage .= "You may have selected a point outside the USA NWS Forecast area.  <a href=\"\" onclick=\"window.local.reload();\">Try again</a></h2>";
  } else {
    $errorMessage .= "Please <a href=\"\" onclick=\"window.local.reload();\">try again</a></h2>";
  }
	$distanceFrom = '';
	$cityname = 'n/a';
	$statename = 'n/a';
	$updateTime= 'n/a';
	$lat=$centerlat;
	$long=$centerlong;
}
// now get the actual forecast from the gridpoint URL

if(isset($fcstURL)) { // try only if the gridpoint URL was found

  $gridpointHTML = WXmap_fetchUrlWithoutHanging($fcstURL);
  $stuff = explode("\r\n\r\n",$gridpointHTML); // maybe we have more than one header due to redirects.
  $content = (string)array_pop($stuff); // last one is the content
  $headers = (string)array_pop($stuff); // next-to-last-one is the headers
  preg_match('/HTTP\/\S+ (\d+)/', $headers, $m);
	//$Status .= "<!-- m=".print_r($m,true)." -->\n";
	//$Status .= "<!-- html=".print_r($html,true)." -->\n";
	if(isset($m[1])) {$lastRC = (string)$m[1]; } else { $lastRC = '0'; }
  if($lastRC == '200') {
	
		$rawgridpointJSON = json_decode($content,true);
		$gridpointJSON = $rawgridpointJSON['properties'];
	  if($doDebug) {$Status .= "<!-- gridpoint content\n".var_export($gridpointJSON,true)." -->\n";}
		$updateTime = date($timeFormat,strtotime($gridpointJSON["updateTime"]));
/* ld+json extract	
		if(isset($gridpointJSON['geometry'])) {
			$g = $gridpointJSON['geometry'];
		# "geometry": "GEOMETRYCOLLECTION(POINT(-122.0220167 37.2668315),POLYGON((-122.0385572 37.275548,-122.0330058 37.2536879,-122.005479 37.2581132,-122.011025 37.2799738,-122.0385572 37.275548)))",
			if(preg_match('|POINT\(([^\)]+)\)|i',$g,$m)) {
				list($long,$lat) = explode(' ',$m[1]);
				$long = round($long,4);
				$lat  = round($lat,4);
				$centerlat = $lat;
				$centerlong = $long;
				
			}
			if(preg_match('|POLYGON\(\(([^\)]+)\)\)|i',$g,$m)) {
				$poly = explode(',',$m[1]);
			}
*/			
		}

	$poly = array();
  if(isset($rawgridpointJSON['geometry']['coordinates'][0])) {
	  if($doDebug) {$Status .= "<!-- gridpoint geometry\n".var_export($rawgridpointJSON['geometry']['coordinates'][0],true)." -->\n";}
		foreach ($rawgridpointJSON['geometry']['coordinates'][0] as $i => $coords) {
			$poly[] = $coords[0].' '.$coords[1];
		}
		
		if($doDebug) {$Status .= "<!-- lat=$lat long=$long poly=".var_export($poly,true)." -->\n"; }
	} else {

		$errorMessage .= "<h2>Oops... unable to fetch the gridpoint forecast data RC=$lastRC.<br/>" .
										"Please try again later</h2>";
		$updateTime= 'n/a';
		$lat=$centerlat;
		$long=$centerlong;
	}
}
/*
list($alertsList,$alertsJS) = WXmap_get_alerts($countyZoneURL,$fcstZone,$countyZone,$fireZone,$centerlat,$centerlong,
      "$cityname $statename");
*/
$alertsList = '';
$alertsJS = '';
  list($fcstZoneName,$fcstZoneJS) = WXmap_get_zone_info($fcstZoneURL,1);
  list($countyZoneName,$countyZoneJS) = WXmap_get_zone_info($countyZoneURL,3);
  list($fireZoneName,$fireZoneJS) = WXmap_get_zone_info($fireZoneURL,4);
  
if(!isset($zoom)) {$zoom = 11;}
print $Status;

?>
<div style="width:621px; margin:0px auto 0px auto;">
 <table cellspacing="0" cellpadding="0" 
   style="width:100%; margin:0px auto 0px auto; border:1px solid black; background-color:#F5F8FE">
  <tr>
   <td style="text-align:center; padding:5px 0px 5px 0px">National Weather Service<br/>
    Coverage maps for Forecast, County and Fire Zones</td>
  </tr>
 </table>
 <p> </p>
<table cellspacing="3" cellpadding="3" style="width: 611px;color:black; line-height:1.2em; margin: 0px auto 0px auto; border:solid 4px #006699; background-color:#FFF">
 <tr>
  <td colspan="3">
   <div style="margin: 6px auto 0px auto; text-align:center; width:570px; height:360px; border: outset #777 3px;" id="map_canvas">
    <noscript>
     <p><span style="font-size:14px;">Your JavaScript is disabled and preventing the map to load for you.</span></p>
     <p>Enable your JavaScript in your browser to view the map and select a forecast for any US location.</p>
    </noscript>
    <?php echo $errorMessage; ?>
   </div>
   <div style="text-align:center; font-size:12px;margin-top: 5px;">
     <span style="color:#009900;font-size:large;font-weight:bolder;">&#9744; </span> Green box outlines the forecast area</div>
  </td>
 </tr>
 <tr>
  <td style="width: 60%;font-size:12px; padding-left:16px; color:#030">Zoom or drag the map for a precise location<br />Double-click the location for the latest forecast.</td>
  <td style="width: 5%;float:right">
   <form action="" method="get">
   <div>
    <input type="hidden" id="latlongclicked" name="llc" />
    <input type="hidden" id="currentzoom" name="zoom" />
    <input type="hidden" id="currentmap"  name="map" />
    <input style="visibility:hidden;" id="theSubmitButton" type="submit" value=""/>
   </div>
   </form>
  </td>
  <td style="width:32%; text-align:left; font-size:10px">LAT: &nbsp;<span id="latspan"><?php echo $lat; ?></span><br />LON: &nbsp;<span id="lngspan"><?php echo $long;?></span></td>
 </tr>
 <tr>
  <td colspan="3" style="padding: 2px 10px 2px 10px"><hr /></td>
 </tr>
 <tr>
  <td colspan="3" style="text-align:center; font-size: 1.5em;"><b><?php echo "$distanceFrom$cityname, $statename"; ?></b></td>
 </tr>
 <!-- tr>
  <td colspan="3" style="padding-bottom: 6px; text-align:center; font-size: 0.8em">Updated <?php echo $updateTime;?></td>
 </tr -->
 <tr>
   <td style="width:20%;font-size-adjust: 1;padding-top: 10px;"><a href="https://www.weather.gov/pimar/PubZone">Forecast Zone</a>:</td>
   <td style="width:10%"><span class="zone" style="color: black;background-color:#F79F81;">
     <b><?php echo $fcstZone; ?></b></span></td>
   <td style="width:70%;text-align:left;"><?php echo $fcstZoneName; ?></td>
 </tr>
 <tr>
   <td style="width:20%;font-size-adjust: 1; padding-top: 10px;"><a href="https://www.weather.gov/pimar/FIPSCodes">County Zone</a>:</td>
   <td style="width:10%;"><span class="zone" style="color: white;background-color:#0174DF;">
      <b><?php echo $countyZone; ?></b></span>
   </td>
   <td style="width:70%;text-align:left;"><?php echo $countyZoneName; ?></td>
 </tr>
  <tr>
   <td style="width:20%;font-size-adjust: 1;padding-top: 10px;"><a href="https://www.weather.gov/pimar/FireZones">Fire Zone</a>:</td>
   <td style="width:10%;"><span class="zone" style="color: black;background-color:#58FAD0;">
      <b><?php echo $fireZone; ?></b></span>
   </td>
   <td style="width:70%;text-align:left;"><?php echo $fireZoneName; ?></td>
 </tr>
<?php if($fcstZone !== 'none') { // do copy only if valid forecast zone ?>
 <tr>
  <td colspan="3" style="padding: 2px 10px 2px 10px"><hr /></td>
 </tr>
 <tr>
  <td colspan="3" style="text-align: center;padding: 2px 10px 2px 10px">
    Configuration for <a href="https://github.com/ktrue/NWS-forecast" title="advforecast2.php script"><strong>NWS forecast script</strong></a>:<br/>Put in <strong>$SITE['NWSforecasts']</strong> array in <em>Settings.php</em> for Saratoga Base-USA template<br/>
   Put in <strong>$NWSforecasts</strong> array in <em>advforecast2.php</em> for standalone use.</td>
 </tr>
 <tr>
  <td colspan="3" style="padding: 2px 10px 2px 10px"><button class="control-copytextarea" 
onclick="return fieldtoclipboard.copyfield(event, 'configtext')">
Copy to Clipboard</button><br/><textarea id="configtext" cols="80" rows="2">
<?php echo "\"$fcstZone|$distanceFrom$cityname, $statename|https://forecast.weather.gov/MapClick.php?lat=$centerlat&amp;lon=$centerlong&amp;unit=0&amp;lg=english&amp;FcstType=text&amp;TextType=2\",\n";?>
</textarea></td>
 </tr>
 <tr>
  <td colspan="3" style="padding: 2px 10px 2px 10px"><hr /></td>
 </tr>
 <tr>
  <td colspan="3" style="text-align: center;padding: 2px 10px 2px 10px">
    Configuration for <a href="https://github.com/ktrue/NWS-alerts" title="nws-alerts.php script"><strong>NWS alerts script</strong></a>:<br/>Put in <strong>$SITE['NWSalertsCodes']</strong> array in <em>Settings.php</em> for Saratoga Base-USA template<br/>
   Put in <strong>$myZC</strong> array in <em>nws-alerts-config.php</em> for standalone use.</td>
 </tr>
 <tr>
  <td colspan="3" style="padding: 2px 10px 2px 10px"><button class="control-copytextarea" 
onclick="return fieldtoclipboard.copyfield(event, 'configtext2')">
Copy to Clipboard</button><br/><textarea id="configtext2" cols="80" rows="2">
<?php echo "\"$cityname, $statename|$fcstZone|$countyZone\",\n";?>
</textarea></td>
 </tr>
<?php } // end of valid forecast zone ?>
</table>

<script type="text/javascript">
// <![CDATA[
<?php
	print '// Leaflet/OpenStreetMap+other tile providers MAP production code
';
	// Generate map options
	$mOpts = array();
	$mList = '';  
	$mFirstMap = '';
	$mFirstMapName = '';
	$mSelMap = '';
	$mSelMapName = '';
	$swxAttrib = ' | Script by <a href="https://saratoga-weather.org/">Saratoga-weather.org</a>';
	$mScheme = $_SERVER['SERVER_PORT']==443?'https':'http';
	foreach ($mapTileProviders as $n => $M ) {
		$name = $M['name'];
		$vname = 'M'.strtolower($name);
		if(empty($mFirstMap)) {$mFirstMap = $vname; $mFirstMapName = $name;}  // default map is first in list
		if(strpos($n,'Mapbox') !== false and 
		   strpos($mapboxAPIkey,'-API-key-') !== false) { 
			 $mList .= "\n".'// skipping Mapbox - '.$name.' since $mapboxAPIkey is not set'."\n\n"; 
			 continue;
		}
		if($mScheme == 'https' and parse_url($M['URL'],PHP_URL_SCHEME) == 'http') {
			$mList .= "\n".'// skipping '.$name.' due to http only map tile link while our page is https'."\n\n";
			continue;
		}
		if($mapProvider == $n) {$mSelMap = $vname; $mSelMapName = $name;}
		$mList .= 'var '.$vname.' = L.tileLayer(\''.$M['URL'].'\', {
			maxZoom: '.$M['maxzoom'].',
			attribution: \''.$M['attrib'].$swxAttrib.'\',
			mapname: "'.$name.'"
			});
';
		$mOpts[$name] = $vname;
		
	}
	print "// Map tile providers:\n";
  print $mList;
	print "// end of map tile providers\n\n";
	print "var baseLayers = {\n";
  $mtemp = '';
	foreach ($mOpts as $n => $v) {
		$mtemp .= '  "'.$n.'": '.$v.",\n";
	}
	$mtemp = substr($mtemp,0,strlen($mtemp)-2)."\n";
	print $mtemp;
	print "};	\n";
	if(empty($mSelMap)) {$mSelMap = $mFirstMap; $mSelMapName = $mFirstMapName;}
	if(isset($reqMap) and isset($mOpts[$reqMap])) {
		$mSelMap = $mOpts[$reqMap];
		$mSelMapName = $reqMap;
	}
	// end Generate map tile options
?>
var map = L.map('map_canvas', {
		center: new L.latLng([<?php echo $centerlat;?>,<?php echo $centerlong;?>]), 
		zoom: <?php echo $zoom; ?>,
		layers: [<?php echo $mSelMap; ?>],
		doubleClickZoom: false,
		scrollWheelZoom: false
		});

var selMap = '<?php echo $mSelMapName; ?>';
	 // console.log('initial selMap='+selMap);	

var layerControl = L.control.layers(baseLayers, {}, {collapsed: false}).addTo(map);

// draw the gridpoint forecast area as a polygon
  var polyfa = [
<?php
  foreach ($poly as $i => $coords) {
		list($longP,$latP) = explode(' ',$coords);
		print "  [$latP,$longP],\n";
	}
?>
  ];
	
 var mapolyfa = new L.polygon(polyfa,{
  opacity: 1.0,
  color: "#009900",
  strokeOpacity: 0.9,
  weight: 2.5,
  fillColor: "#7FF378",
  fillOpacity: 0.20,
	title: "Forecast Area"
 }).addTo(map);

  mapolyfa.bindTooltip("Forecast Area for <?php echo "$distanceFrom$cityname"; ?>", 
   { sticky: true,
     direction: "auto"
   });
	 
	var markerImageGreen  = new L.icon({ 
		iconUrl: "./ajax-images/mma_20_green.png",
		iconSize: [12, 20],
		iconAnchor: [6, 20]
    });
	var marker = new L.marker(new L.LatLng(<?php echo $centerlat;?>,<?php echo $centerlong;?>),{
		clickable: true,
		draggable: false,
		icon: markerImageGreen,
		title: "Selected location at <?php echo $centerlat;?>,<?php echo $centerlong;?>"+
		       "\n(/gridpoints/<?php echo $gridPointMeta; ?>/forecast bounding box shown)"+
					 "\nadjustment of <?php echo $adj1; ?>,<?php echo $adj2; ?> gridpoint used"
	});
  map.addLayer(marker);
  
  const searchControl = new GeoSearch.GeoSearchControl({
    style: 'bar',
    showMarker: false, // optional: true|false  - default true
		marker: {
    // optional: L.Marker    - default L.Icon.Default
      icon: markerImageGreen,
      draggable: false,
    },
    maxMarkers: 1, // optional: number      - default 1
    showPopup: false, // optional: true|false  - default false
    retainZoomLevel: true, // optional: true|false  - default false
    animateZoom: true, // optional: true|false  - default true
    autoClose: false, // optional: true|false  - default false
    searchLabel: 'Search city,state, zip code or address', // optional: string      - default 'Enter address'
    keepResult: false, // optional: true|false  - default false
    updateMap: false, // optional: true|false  - default true		
		notFoundMessage: 'Sorry, that address could not be found.',
    provider: new GeoSearch.OpenStreetMapProvider(),
  });
  map.addControl(searchControl);
 function searchEventHandler(result) {
  console.log(result.location);
  document.getElementById('latlongclicked').value = result.location.y.toFixed(4) + ',' + result.location.x.toFixed(4);
  document.getElementById('currentzoom').value = map.getZoom();
  document.getElementById('currentmap').value = selMap;
  document.getElementById('theSubmitButton').click();

 }

 map.on('geosearch/showlocation', searchEventHandler);

  // -- Forecast Zone --
<?php if(!empty($fcstZoneJS)) { print $fcstZoneJS; } ?>
// -- County Zone --
<?php if(!empty($countyZoneJS)) { print $countyZoneJS; } ?>
// -- Fire Zone --
<?php if(!empty($fireZoneJS)) { print $fireZoneJS; } ?>
// -- boilerplate --
 
var click_elements = document.getElementsByClassName("leaflet-control-layers-selector");

  // Find all the layers control elements
for (let i = 0;i < click_elements.length;i++) {
     click = click_elements[i];
}
// and finally, make a click event
 click_elements[click_elements.length -1].click(); // toggle Fire off (it was set by default as checked/on
 click_elements[click_elements.length -2].click(); // toggle County off (it was set by default as checked/on

  L.control.scale().addTo(map);

// display mouse lat/long in page as mouse moves	 
 function mouseMove (e) {
   document.getElementById('latspan').innerHTML = e.latlng.lat.toFixed(4)
   document.getElementById('lngspan').innerHTML = e.latlng.lng.toFixed(4)
 }
 map.on('mousemove',mouseMove);

// do 'submit' with mouse lat/long+current zoom as args  
 function mouseDoubleClicked (e) {
   document.getElementById('latlongclicked').value = e.latlng.lat.toFixed(4) + ',' + e.latlng.lng.toFixed(4);
   document.getElementById('currentzoom').value = map.getZoom();
   document.getElementById('currentmap').value = selMap;
   document.getElementById('theSubmitButton').click();
	 // console.log('submit selMap='+selMap)	
 }
 map.on('dblclick',mouseDoubleClicked);
 
 function mapbasechg (e) {
   document.getElementById('currentmap').value = e.name;
	 selMap = e.name;
	 //console.log('mapchange selMap='+e.name)	
 }
 
 map.on('baselayerchange',mapbasechg);

/* Select (and copy) Form Element Script v1.1
* Author: Dynamic Drive at http://www.dynamicdrive.com/
* Visit http://www.dynamicdrive.com/dynamicindex11/selectform.htm for full source code
*/

var fieldtoclipboard = {
	tooltipobj: null,
	hidetooltiptimer: null,

	createtooltip:function(){
		var tooltip = document.createElement('div')
		tooltip.style.cssText = 
			'position:absolute; background:black; color:white; padding:4px;z-index:10000;'
			+ 'border-radius:3px; font-size:12px;box-shadow:3px 3px 3px rgba(0,0,0,.4);'
			+ 'opacity:0;transition:opacity 0.3s'
		tooltip.innerHTML = 'Copied!'
		this.tooltipobj = tooltip
		document.body.appendChild(tooltip)
	},

	showtooltip:function(e){
		var evt = e || event
		clearTimeout(this.hidetooltiptimer)
		this.tooltipobj.style.left = evt.pageX - 10 + 'px'
		this.tooltipobj.style.top = evt.pageY + 15 + 'px'
		this.tooltipobj.style.opacity = 1
		this.hidetooltiptimer = setTimeout(function(){
			fieldtoclipboard.tooltipobj.style.opacity = 0
		}, 700) // time in milliseconds before tooltip disappears
	},

	selectelement:function(el){
    var range = document.createRange() // create new range object
    range.selectNodeContents(el)
    var selection = window.getSelection() // get Selection object from currently user selected text
    selection.removeAllRanges() // unselect any user selected text (if any)
    selection.addRange(range) // add range to Selection object to select it
	},
	
	copyfield:function(e, fieldref, callback){
		var field = (typeof fieldref == 'string')? document.getElementById(fieldref) : fieldref
		callbackref = callback || function(){}
		if (/(textarea)|(input)/i.test(field) && field.setSelectionRange){
			field.focus()
			field.setSelectionRange(0, field.value.length) // for iOS sake
		}
		else if (field && document.createRange){
			this.selectelement(field)
		}
		else if (field == null){ // copy currently selected text on document
			field = {value:null}
		}
		var copysuccess // var to check whether execCommand successfully executed
		try{
			copysuccess = document.execCommand("copy")
		}catch(e){
			copysuccess = false
		}
		if (copysuccess){ // execute desired code whenever text has been successfully copied
			if (e){
				this.showtooltip(e)
			}
			callbackref(field.value || window.getSelection().toString())
		}
		return false
	},


	init:function(){
		this.createtooltip()
	}
}
fieldtoclipboard.init();
// ]]>
</script>

<p style="text-align:center;"><small><?php echo $Version; ?> <a href="https://github.com/ktrue/NWS-info-map" title="Click for source">Script</a> by <a href="https://saratoga-weather.org/">Saratoga-weather.org</a>. 
Data provided by <a href="https://www.weather.gov/">NOAA/NWS</a> API.</small> </p>
</div>
</div>
<?php if(!$includeMode) { ?>
</body>
</html>
<?php } // end !$includeMode ?>
<?php
// ------------------------------------------------------------------------------------------
// FUNCTIONS

function WXmap_fetchUrlWithoutHanging($inurl)
{

  // get contents from one URL and return as string

  global $Status, $needCookie /*, $URLcache */;
  $useFopen = false;
  $overall_start = time();
  if (!$useFopen) {

    // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed

    $numberOfSeconds = 6;
		$url = $inurl;
    // Thanks to Curly from ricksturf.com for the cURL fetch functions

    $data = '';
    $domain = parse_url($url, PHP_URL_HOST);
    $theURL = str_replace('nocache', '?' . $overall_start, $url); // add cache-buster to URL if needed
    $Status.= "<!-- curl fetching '$theURL' -->\n";
    $ch = curl_init(); // initialize a cURL session
    curl_setopt($ch, CURLOPT_URL, $theURL); // connect to provided URL
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // don't verify peer certificate
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (NWS-info-map.php - saratoga-weather.org)');
//    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:58.0) Gecko/20100101 Firefox/58.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, // request LD-JSON format
    array(
      "Accept: application/geo+json",
			"Cache-control: no-cache",
			"Pragma: akamai-x-cache-on, akamai-x-get-request-id"
    ));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds); //  connection timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds); //  data timeout
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return the data transfer
    curl_setopt($ch, CURLOPT_NOBODY, false); // set nobody
    curl_setopt($ch, CURLOPT_HEADER, true); // include header information

      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);              // follow Location: redirect
      curl_setopt($ch, CURLOPT_MAXREDIRS, 1);                      //   but only one time

    if (isset($needCookie[$domain])) {
      curl_setopt($ch, $needCookie[$domain]); // set the cookie for this request
      curl_setopt($ch, CURLOPT_COOKIESESSION, true); // and ignore prior cookies
      $Status.= "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
    }

    $data = curl_exec($ch); // execute session
    if (curl_error($ch) <> '') { // IF there is an error
      $Status.= "<!-- curl Error: " . curl_error($ch) . " -->\n"; //  display error notice
    }

    $cinfo = curl_getinfo($ch); // get info on curl exec.
    /*
    curl info sample
    Array
    (
    [url] => http://saratoga-weather.net/clientraw.txt
    [content_type] => text/plain
    [http_code] => 200
    [header_size] => 266
    [request_size] => 141
    [filetime] => -1
    [ssl_verify_result] => 0
    [redirect_count] => 0
    [total_time] => 0.125
    [namelookup_time] => 0.016
    [connect_time] => 0.063
    [pretransfer_time] => 0.063
    [size_upload] => 0
    [size_download] => 758
    [speed_download] => 6064
    [speed_upload] => 0
    [download_content_length] => 758
    [upload_content_length] => -1
    [starttransfer_time] => 0.125
    [redirect_time] => 0
    [redirect_url] =>
    [primary_ip] => 74.208.149.102
    [certinfo] => Array
    (
    )
    [primary_port] => 80
    [local_ip] => 192.168.1.104
    [local_port] => 54156
    )
    */
		if($url !== $cinfo['url'] and $cinfo['http_code'] == 200 and
		   strpos($url,'/points/') > 0 and strpos($cinfo['url'],'/gridpoints/') > 0) {
			# only cache point forecast->gridpoint forecast successful redirects
			$Status .= "<!-- note: fetched '".$cinfo['url']."' after redirect was followed. -->\n";
			//$URLcache[$inurl] = $cinfo['url'];
			//$Status .= "<!-- $inurl added to URLcache -->\n";
		}

    $Status.= "<!-- HTTP stats: " . " RC=" . $cinfo['http_code'];
		if (isset($cinfo['primary_ip'])) {
			$Status .= " dest=" . $cinfo['primary_ip'];
		}
    if (isset($cinfo['primary_port'])) {
      $Status .= " port=" . $cinfo['primary_port'];
    }

    if (isset($cinfo['local_ip'])) {
      $Status.= " (from sce=" . $cinfo['local_ip'] . ")";
    }

    $Status.= "\n      Times:" . 
		" dns=" . sprintf("%01.3f", round($cinfo['namelookup_time'], 3)) . 
		" conn=" . sprintf("%01.3f", round($cinfo['connect_time'], 3)) . 
		" pxfer=" . sprintf("%01.3f", round($cinfo['pretransfer_time'], 3));
    if ($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
      $Status.= " get=" . sprintf("%01.3f", round($cinfo['total_time'] - $cinfo['pretransfer_time'], 3));
    }

    $Status.= " total=" . sprintf("%01.3f", round($cinfo['total_time'], 3)) . " secs -->\n";

    // $Status .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";

    curl_close($ch); // close the cURL session

    // $Status .= "<!-- raw data\n".$data."\n -->\n";
    $stuff = explode("\r\n\r\n",$data); // maybe we have more than one header due to redirects.
    $content = (string)array_pop($stuff); // last one is the content
    $headers = (string)array_pop($stuff); // next-to-last-one is the headers

    if ($cinfo['http_code'] <> '200') {
      $Status.= "<!-- headers returned:\n" . $headers . "\n -->\n";
    }

    return $data; // return headers+contents
  }
  else {

    //   print "<!-- using file_get_contents function -->\n";

    $STRopts = array(
      'http' => array(
        'method' => "GET",
        'protocol_version' => 1.1,
        'header' => "Cache-Control: no-cache, must-revalidate\r\n" . 
					"Cache-control: max-age=0\r\n" . 
					"Connection: close\r\n" . 
					"User-agent: Mozilla/5.0 (nws-info-map.php - saratoga-weather.org)\r\n" . 
					"Accept: application/geo+json\r\n",
					"Cache-control: no-cache\r\n",
					"Pragma: akamai-x-cache-on, akamai-x-get-request-id\r\n"
      ) ,
      'ssl' => array(
        'method' => "GET",
        'protocol_version' => 1.1,
				'verify_peer' => false,
        'header' => "Cache-Control: no-cache, must-revalidate\r\n" . 
					"Cache-control: max-age=0\r\n" . 
					"Connection: close\r\n" . 
					"User-agent: Mozilla/5.0 (nws-info-map.php - saratoga-weather.org)\r\n" . 
					"Accept: application/geo+json\r\n",
					"Cache-control: no-cache\r\n",
					"Pragma: akamai-x-cache-on, akamai-x-get-request-id\r\n"
      )
    );
    $STRcontext = stream_context_create($STRopts);
    $T_start = WXmap_fetch_microtime();
    $xml = file_get_contents($inurl, false, $STRcontext);
    $T_close = WXmap_fetch_microtime();
    $headerarray = get_headers($url, 0);
    $theaders = join("\r\n", $headerarray);
    $xml = $theaders . "\r\n\r\n" . $xml;
    $ms_total = sprintf("%01.3f", round($T_close - $T_start, 3));
    $Status.= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
    $Status.= "<-- get_headers returns\n" . $theaders . "\n -->\n";

    //   print " file() stats: total=$ms_total secs.\n";

    $overall_end = time();
    $overall_elapsed = $overall_end - $overall_start;
    $Status.= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n";

    //   print "fetch function elapsed= $overall_elapsed secs.\n";

    return ($xml);
  }
} // end WXmap_fetchUrlWithoutHanging

// ------------------------------------------------------------------

function WXmap_fetch_microtime()
{
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
}

// ------------------------------------------------------------------------------------------

function WXmap_get_zone_info($URL,$idx) {
		global $Status,$doDebug,$cacheFileDir,$refreshTime;
	if(preg_match('!none!',$URL)) {
    return(array('Not Defined',''));
  }
  $cacheName = $cacheFileDir . get_cache_name($URL);
  
  if(!file_exists($cacheName) or 
    (file_exists($cacheName) and filemtime($cacheName) + $refreshTime < time())) {
	  $Status .= "<!-- fetching $URL -->\n";
    $jsonHTML = WXmap_fetchUrlWithoutHanging($URL);
    $stuff = explode("\r\n\r\n",$jsonHTML); // maybe we have more than one header due to redirects.
    $content = (string)array_pop($stuff); // last one is the content
    $headers = (string)array_pop($stuff); // next-to-last-one is the headers
    preg_match('/HTTP\/\S+ (\d+)/', $headers, $m);
	  //$Status .= "<!-- m=".print_r($m,true)." -->\n";
	  //$Status .= "<!-- html=".print_r($html,true)." -->\n";
	  if(!isset($m[1])) {
		  $Status .= "<!-- failed to fetch $URL to process -->\n";
	    return(array('Error: data not available',''));
	  }
		
	  $lastRC = (string)$m[1];

    if($lastRC !== '200') { // no data to process
		  $Status .= "<!-- no data to process -->\n";
	    return(array('',''));
    }
      
    if(file_put_contents($cacheName,$content)) {
      $Status .= "<!-- saved ".strlen($content)." bytes to cache $cacheName -->\n";
      } else {
       $Status .= "<!-- unable to save ".strlen($content)." bytes to cache $cacheName -->\n";
      }
	  } else { # cache not stale.. reload from cache
      $Status .= "<!-- reload from cache $cacheName -->\n";
      $content = file_get_contents($cacheName);
    }

	$JSON = json_decode($content,true);
	if($doDebug) {$Status .= "<!--  JSON\n".var_export($JSON,true)." -->\n"; }
	
	$A = "";
	$JS = '';
  $colors = array('#DF0101','#F79F81','#F2F5A9','#0174DF','#58FAD0',
                  '#FACC2E','#01DF01','#F7BE81','#FE2E64','#E0F8EC',
                  '#DF0101','#F79F81','#F2F5A9','#0174DF','#58FAD0',
                  '#FACC2E','#01DF01','#F7BE81','#FE2E64','#E0F8EC',
                  '#DF0101','#F79F81','#F2F5A9','#0174DF','#58FAD0',
                  '#FACC2E','#01DF01','#F7BE81','#FE2E64','#E0F8EC'
									);

    $J = $JSON['properties'];
		// generate the text
    $name = isset($J['name'])?$J['name']:'n/a';
    $type = isset($J['type'])?$J['type']:'n/a';
    if($type == 'public') {
      $type='Forecast Zone';
    } else {
      $type = ucfirst($type).' Zone';
    }
    $state= isset($J['state'])?$J['state']:'n/a';
    $zone = isset($J['id'])?$J['id']:'n/a';
    $A = "$name, $state, $type";
    #$Status .= "<!-- poly $idx \n".var_export($poly,true)." -->\n";
    $cs = $idx;
    $cc = count($colors);
    if(!isset($colors[$cs])) {$tr = range(0,$cs); shuffle($tr); $cs = $tr[0];}
    $rc = isset($colors[$cs])?$colors[$cs]:$colors[1];
    $id = $idx+1;
		// process map defs
		if(isset($JSON['geometry']['coordinates'][0]) or 
       isset($JSON['geometry']['geometries'][0])) { 
      
			if($JSON['geometry']['type'] == 'Polygon') { # simple polygon
 
      $JS .= '					
  var poly'.$id.' = [
';
      $JS .= extract_Polygon($JSON);
  		$tooltip = "$name, $state, $type ($zone)";
      $JS .= '
  ];
	
 var mapoly'.$id.' = new L.polygon(poly'.$id.',{
  opacity: 1.0,
  color: "'.$rc.'",
  strokeOpacity: 0.9,
  weight: 2.5,
  fillColor: "'.$rc.'",
  fillOpacity: 0.20,
	title: "'.$A.'"
 }).addTo(map);

  mapoly'.$id.'.bindTooltip("'.$tooltip.'", 
   { sticky: true,
     direction: "auto"
   });
   
   layerControl.addOverlay(mapoly'.$id.',"'.$type.'");
';				
									  
} elseif ($JSON['geometry']['type'] == 'MultiPolygon') {
    $Status .= "<!-- multipolygon found -->\n";
    $JS .= '	// multipolygon format				
  var poly'.$id.' = [
';
    $JS .= extract_Multipolygon($JSON);
        
    $tooltip = "$name, $state, $type ($zone)";

$JS .= '
  ];
	
 var mapoly'.$id.' = new L.polygon(poly'.$id.',{
  opacity: 1.0,
  color: "'.$rc.'",
  strokeOpacity: 0.9,
  weight: 2.5,
  fillColor: "'.$rc.'",
  fillOpacity: 0.20,
	title: "'.$A.'"
 }).addTo(map);

  mapoly'.$id.'.bindTooltip("'.$tooltip.'", 
   { sticky: true,
     direction: "auto"
   });
   
  layerControl.addOverlay(mapoly'.$id.',"'.$type.'");
';				

        
} elseif ($JSON['geometry']['type'] == 'GeometryCollection') {

    $Status .= "<!-- GeometryCollection found -->\n";
    $JS .= '	// GeometryCollection format				
  var poly'.$id.' = [
';
    $JS .= extract_GeometryCollection($JSON);
        
    $tooltip = "$name, $state, $type ($zone)";

$JS .= '
  ];
	
 var mapoly'.$id.' = new L.polygon(poly'.$id.',{
  opacity: 1.0,
  color: "'.$rc.'",
  strokeOpacity: 0.9,
  weight: 2.5,
  fillColor: "'.$rc.'",
  fillOpacity: 0.20,
	title: "'.$A.'"
 }).addTo(map);

  mapoly'.$id.'.bindTooltip("'.$tooltip.'", 
   { sticky: true,
     direction: "auto"
   });
   
  layerControl.addOverlay(mapoly'.$id.',"'.$type.'");
';				
        
    } else { # None found...
				$Status .= "<!-- no coordinates found -->\n";
        $JS .= " // no coordinates found\n";
	  }// end polygon generation	
	} // end JS generation
		
		
//	} // end process alerts
	
	return(array($A,$JS));
}


// ------------------------------------------------------------------------------------------
/* Leaflet latlng notes:

var latlngs = [ // multipolygon format in leaflet
  [ // first polygon
    [[37, -109.05],[41, -109.03],[41, -102.05],[37, -102.04]], // outer ring
    [[37.29, -108.58],[40.71, -108.58],[40.71, -102.50],[37.29, -102.50]] // hole
  ],
  [ // second polygon
    [[41, -111.03],[45, -111.04],[45, -104.05],[41, -104.05]]
  ]
];
Note that points you pass when creating a polygon shouldn't have an additional last point 
equal to the first one — it's better to filter out such points.
*/

function extract_Polygon ($JSON,$doEndComma=false) {
/*
    "geometry": {
        "type": "Polygon",
        "coordinates": [
            [
                [
                    -90.200599600000004,
                    38.823612199999999
                ],
                [
                    -90.184196400000005,
                    38.817912999999997
                ],

*/
  $JS = '';
  $nPoly = count($JSON['geometry']['coordinates']);
  foreach ($JSON['geometry']['coordinates'] as $K => $poly) { #loop over multipolygon
    $JS .= '[ /*polygon '.$K. " */\n";

    $pTemp = array();
    foreach ($poly as $ii => $coords) {
      $pTemp[] = array($coords[1],$coords[0]); # reverse long,lat to lat,long for leaflet
    }
    $pLast = array_pop($pTemp); # remove last entry
    $JS .= '    '. json_encode($pTemp);
    $JS .= ($K < $nPoly-1)?"\n],\n":"\n]\n";
  }
  $JS .= $doEndComma?",\n":'';
  return($JS);
}

// ------------------------------------------------------------------------------------------

function extract_Multipolygon ($JSON,$doEndComma=false) {
/*

    "geometry": {
        "type": "MultiPolygon",
        "coordinates": [
            [
                [
                    [
                        -122.3954925,
                        37.708312900000003
                    ],
                    [
                        -122.39285480000001,
                        37.708175100000005
                    ],


*/

  $JS = "// extract_Multipolygon entered\n";

  $nPoly = count($JSON['geometry']['coordinates']);
  foreach ($JSON['geometry']['coordinates'] as $K => $poly) { #loop over multipolygon
    $JS .= '[ /*polygon '.$K. " */\n";

    $pTemp = array();
    foreach ($poly[0] as $ii => $coords) {
      $pTemp[] = array($coords[1],$coords[0]); # reverse long,lat to lat,long for leaflet
    }
    $pLast = array_pop($pTemp); # remove last entry
    $JS .= '    '. json_encode($pTemp);
    $JS .= ($K < $nPoly-1)?"\n],\n":"\n]\n";
  }
  $JS .= $doEndComma?",\n":'';
  $JS .= "\n// extract_Multipolygon exit\n";
  return($JS);
}

// ------------------------------------------------------------------------------------------

function extract_GeometryCollection ($JSON) {
/*  

    "geometry": {
        "type": "GeometryCollection",
        "geometries": [
            {
                "type": "MultiPolygon",
                "coordinates": [
                    [
                        [
                            [
                                -81.986709500000003,
                                27.034841499999999
                            ],
                            [
                                -81.986549300000007,
                                27.033466300000001
                            ],
                            [
                                -81.986701900000014,
                                27.032960800000001
                            ],


*/
  $JS = "// extract_GeometryCollection entered\n";
  $JTemp = array();
  $nGeometries = count($JSON['geometry']['geometries']) - 1;
  
  foreach ($JSON['geometry']['geometries'] as $i => $J) {
    $addComma = ($i == $nGeometries)?false:true; # add comma on middle ones, not end one
    if($J['type'] == 'Polygon') {
      $JTemp['geometry'] = $J;
      $JS .= extract_Polygon($JTemp,$addComma);
    }
    if($J['type'] == 'MultiPolygon') {
      $JTemp['geometry'] = $J;
      $JS .= extract_Multipolygon($JTemp,$addComma);
    }
  }
  $JS .= "\n// extract_GeometryCollection exit\n";
  
  return($JS);
}

// ------------------------------------------------------------------------------------------

function get_zone($zoneURL) {
	# extract Zone from end of URL
	$p = explode('/',$zoneURL);
	return(array_pop($p));
}

// ------------------------------------------------------------------------------------------

function get_cache_name($zoneURL) {
	# extract Zone from end of URL
	$p = explode('/',$zoneURL);
  $zone = array_pop($p);
  $type = array_pop($p);
	return($type.'-'.$zone.'.json');
}

// ------------------------------------------------------------------------------------------


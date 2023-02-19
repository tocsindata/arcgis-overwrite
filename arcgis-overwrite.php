<?php


date_default_timezone_set('UTC');
set_time_limit(1200);

$config = array();
$config['esri']['referer'] = 'https://__YOURCOMPANYSUBDOMAIN__.maps.arcgis.com/' ;
$config['esri']['username'] = '__YOURUSERNAME__' ;
$config['esri']['password'] = '__YOURPASSWORD__' ;
$config['path']['home'] = "/home/__USERHOME__/";
$config['geojson'] = '__URLTOGEOJSON__' ; // for using file get contents curl
$config['ServiceURL'] = "https://services3.arcgis.com/__YOURLAYERID__/arcgis/rest/services/__YOURLAYERNAME__/FeatureServer";
$config['Ref'] = "https://_COMPANYURL_.maps.arcgis.com/";

/*
##########################################################################
##########################################################################
#########                   FUNCTIONS STARTS                      ########
##########################################################################
##########################################################################
*/
			
		

function SendAPICommand($url, $post = array()) {
global $map_name ;
// curl -X POST -d username=me -d password=XX -d referer=www.company.com f=json https://www.arcgis.com/sharing/rest/generateToken
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL,$url);
curl_setopt($ch, CURLOPT_POST, 1);

curl_setopt($ch, CURLOPT_POSTFIELDS, 
http_build_query($post));

// Receive server response ...
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$server_output = curl_exec($ch);
curl_close ($ch);

$log = "ESRI REST API  (responce): ".print_r($server_output, true) ;
LogToSlack($log, $map_name, "earthquakes");
return $server_output ;
}

function SendGuzzle($url, $data = array()) {
global $config ;
require_once($config['path']['home'].'/vendor/autoload.php'); // Guzzle

$post = array('form_params' => $data) ;

$client = new GuzzleHttp\Client();
$response = $client->request('POST', $url, $post);
$result = json_decode($response->getBody(), true);

return $result ;
}

function file_get_contents_curl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);       
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function ArcGisGetAccessToken($token) {
global $config ;
$now = time();

if(isset($token['expires'])) {
	if($token['expires'] < $now) {
	$token['expires'] = 0 ;
	}
} else {
$token['expires'] = 0 ;
}
	if($token['expires'] == 0) {
			$url = $config['esri']['referer']."/sharing/rest/generateToken"; 
			$post = array();
				$post['username'] = $config['esri']['username'] ;
				$post['password'] = $config['esri']['password'] ;
				$post['expiration'] = 320 ;
				$post['referer'] = $config['Ref'] ;
			$post['f'] = 'json' ;
			
			$results = SendAPICommand($url, $post);
			$token = json_decode($results, true);

	}
return $token ;
}
				
   
function EmptyFeatureLayer() {
global $config ;
$deleteServiceUrl = $config['ServiceURL']."/0/deleteFeatures?token=".$token['token'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $deleteServiceUrl);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
  "f" => "json",
  "where" => "1=1"
)));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
// Check the response for errors
$responseObj = json_decode($response);
if ($responseObj->error) {
  echo "Error delete feature layer: " . $responseObj->error->message;
} else {
  echo "Feature layer delete successfully. " . print_r($responseObj, true) ;
}
return ;
}
 
function replace_array_key($array, $old_key, $new_key) {
    $new_array = array();
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $value = replace_array_key($value, $old_key, $new_key);
        }
        if ($key === $old_key) {
            $key = $new_key;
        }
        $new_array[$key] = $value;
    }
    return $new_array;
}


function arrayCastRecursive($array) {
    if (is_array($array)) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = arrayCastRecursive($value);
            }
            if ($value instanceof stdClass) {
                $array[$key] = arrayCastRecursive((array)$value);
            }
        }
    }
    if ($array instanceof stdClass) {
        return arrayCastRecursive((array)$array);
    }
    return $array;
}
	
function UploadEntireLayer() {
global $config ;
require_once($config['path']['home'].'/vendor/autoload.php'); // Guzzle
$post = array();

$token = ArcGisGetAccessToken($token);$serviceurl = $config['ServiceURL'];
$featureServiceUrl = $serviceurl."/0/addFeatures?token=".$token['token']."&f=json"; ; // ?f=json&token=".$token['token'];
// Define the GeoJSON you want to use to overwrite the feature layer
$geojson = json_decode(file_get_contents_curl($config['geojson']), true);
$features = $geojson['features'];
$features = replace_array_key($features, "properties", "attributes") ;
$formParams = [
						    'token'             => $token['token'],
						    'features'              => json_encode($features), 
						    'f'                 => 'json',
						];

					$result = SendGuzzle($featureServiceUrl, $formParams);
					$result = arrayCastRecursive($result);
return $result ;
}

function OverWrite() {
EmptyFeatureLayer() ;
sleep(5) ;
UploadEntireLayer();

return ;
}


/*
##########################################################################
##########################################################################
#########                   FUNCTIONS ENDS                        ########
##########################################################################
##########################################################################
*/

OverWrite() ;

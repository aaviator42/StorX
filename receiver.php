<?php
/*
StorX API receiver
by @aaviator42

Receiver version: 3.5
StorX.php version: 3.5
StorX DB file format version: 3.0

2021-10-11

*/

require('StorX.php');

const DATA_DIR = "./"; //include trailing slash!
const USE_AUTH = TRUE;
const PASSWORD_HASH = '$2y$10$ZuHv1ksKmna0ch1rChhnnu3TP.2WobqHsvwcyWDWzlr0Z7hjclECa';

//Store the endpoint in $endpoint, segmented endpoint in $endpointArray
$endpoint = rtrim(substr(@$_SERVER['PATH_INFO'], 1), '/\\');
$endpointArray = explode("/", $endpoint);

$method = $_SERVER['REQUEST_METHOD'];

if (!empty(file_get_contents('php://input'))){
	$input = json_decode(file_get_contents('php://input'), true);
} else {
	$input = array();
}

$output = array(
	"error" => 0,			//bool: 0 = all ok, 1 = error occured
	"errorMessage" => NULL,	//if error occurs, store message here
	"returnCode" => NULL	//return codes through this
	);


if(!(($method === 'GET') && ($endpointArray[0] === 'ping'))){
	if(USE_AUTH){
		if(!password_verify($input["password"], PASSWORD_HASH)){
			errorAuthFailed();
		}
	}
}


switch($method){
	case 'PUT':
		switch($endpointArray[0]){
			case 'createFile':
				createFile();
			break;
			case 'writeKey':
				writeKey();
			break;
			case 'modifyKey':
				modifyKey();
			break;
			default:
				errorInvalidRequest();
			break;			
		}
	break;
	
	case 'GET':
		switch($endpointArray[0]){
			case 'ping':
				pong();
			break;
			case 'checkFile':
				checkFile();
			break;
			case 'checkKey':
				checkKey();
			break;
			case 'readKey':
				readKey();
			break;
			default:
				errorInvalidRequest();
			break;
		}
		break;
	
	case 'DELETE':
		switch($endpointArray[0]){
			case 'deleteFile':
				deleteFile();
			break;
			case 'deleteKey':
				deleteKey();
			break;
			default:
				errorInvalidRequest();
			break;
		}
		break;
	default:
		errorInvalidRequest();
	break;
}


function errorAuthFailed(){
	global $output;
	
	$output["error"] = 1;
	$output["errorMessage"] = "StorX API: Authentication failed.";
	$output["returnCode"] = -777;
	
	printOutput(401);
	exit(0);
}

function errorInvalidRequest(){
	global $output;
	
	$output["error"] = 1;
	$output["errorMessage"] = "StorX API: Invalid request.";
	$output["returnCode"] = -666;
	
	printOutput(400);
	exit(0);
}

function printOutput($code = 200){
	global $output;
	header('Content-Type: application/json');
	http_response_code($code);
	echo json_encode($output);
}


//-------------

//ping function

function pong(){
	global $input, $output;
	
	$output["version"] = "3.5";
	
	if($input["version"] === "3.5"){
		$output["pong"] = "OK";
	} else {
		$output["pong"] = "ERR";
	}
	printOutput(200);
	exit(0);
	
}


//basic functions --> create, check and delete files

function createFile(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$output["returnCode"] = \StorX\createFile($filename);
	
	if($output["returnCode"] === 1){
		printOutput(201);
	} else {
		printOutput(409);
	}
	exit(0);
}

function checkFile(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$output["returnCode"] = \StorX\checkFile($filename);
	printOutput(200);
	exit(0);
	
}

function deleteFile(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$output["returnCode"] = \StorX\deleteFile($filename);
	if($output["returnCode"] === 1){
		printOutput(200);
	} else {
		printOutput(409);
	}
	exit(0);
}



//Sx object functions

function readKey(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyName = $input["keyName"];
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename, 0) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
	} else {
		$keyValue;
		$output["returnCode"] = $sx->readKey($keyName, $keyValue);
		$output["keyValue"] = base64_encode(serialize($keyValue));
		$sx->closeFile();
	}	
	
	printOutput(200);
	exit(0);
}

function writeKey(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyName = $input["keyName"];
	$keyValue = $input["keyValue"];
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename, 1) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
	} else {
		$keyValue;
		$output["returnCode"] = $sx->writeKey($keyName, $keyValue);
		
		if($sx->closeFile() !== 1){
			$output["returnCode"] = -3;
			$output["error"] = 1;
		}
	}
	
	if($output["returnCode"] === 1){
		printOutput(200);
	} else {
		printOutput(409);
	}
	exit(0);
}

function modifyKey(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyName = $input["keyName"];
	$keyValue = $input["keyValue"];
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename, 1) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
	} else {
		$keyValue;
		$output["returnCode"] = $sx->modifyKey($keyName, $keyValue);
		
		if($sx->closeFile() !== 1){
			$output["returnCode"] = -3;
			$output["error"] = 1;
		}
	}
	
	if($output["returnCode"] === 1){
		printOutput(200);
	} else {
		printOutput(409);
	}
	exit(0);
}

function deleteKey(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyName = $input["keyName"];
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename, 1) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
	} else {
		$keyValue;
		$output["returnCode"] = $sx->deleteKey($keyName);
		
		if($sx->closeFile() !== 1){
			$output["returnCode"] = -3;
			$output["error"] = 1;
		}
	}
	
	if($output["returnCode"] === 1){
		printOutput(200);
	} else {
		printOutput(409);
	}
	exit(0);
}

function checkKey(){
	global $input, $output;
	
	$filename = DATA_DIR . $input["filename"];
	$keyName = $input["keyName"];
	
	$sx = new \StorX\Sx;
	
	if($sx->openFile($filename) !== 1){
		//error opening file 
		$output["returnCode"] = -2;
		$output["error"] = 1;
	} else {
		$keyValue;
		$output["returnCode"] = $sx->checkKey($keyName);
		$sx->closeFile();
	}
	
	printOutput(200);
	exit(0);
}


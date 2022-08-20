<?php


/*
This script accepts user information and creates a new TeamTalk 5 account from it.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "configurator.php";
require_once "error.php";
require_once "json.php";
require_once "server.php";
require_once "validator.php";
require_once "ui.php";


// Extracts a server name from the query string. If there seems to be no server name within URL, returns "default".
function serverNameFromUrl(): string {
	return isset($_GET["server"]) ? $_GET["server"] : "default";
}

/*
Tries to construct an instance of UserInfo class from the parameters passed via the URL query string;
The given Validator object is used to ensure correctness of those parameters.

In addition to the validator, this function also accepts an array of ServerInfo objects
to search the server name extracted from URL in it.

Throws BadQueryStringException if the actual set of required fields within the URL is incomplete;
Throws RuntimeException if the user information is invalid.
*/
function userInfoFromUrl(Validator $validator, array $serverList): UserInfo {
	$server = null;
	$serverName = serverNameFromUrl();
	foreach($serverList as $i) {
		if($i->name === $serverName) {
			$server = $i;
			break;
		}
	}
	if($server === null) {
		throw new RuntimeException("No managed server named \"$serverName\" is configured");
	}
	$error = false;
	$errorMessage = "The following URL parameters are not provided:\n";
	if(!isset($_GET["name"])) {
		$error = true;
		$errorMessage .= "\tname\n";
	}
	if(!isset($_GET["password"])) {
		$error = true;
		$errorMessage .= "\tpassword\n";
	}
	if($error) {
		throw new BadQueryStringException($errorMessage);
	}
	return new UserInfo($validator, $server, $_GET["name"], $_GET["password"]);
}


// Set up GUI.
set_exception_handler("printErrorMessage");
beginRegistrationPage();
register_shutdown_function("endRegistrationPage");

// Configure the essential options.
$validator = new Validator;
$config = new Configurator($validator, new Json("config.json"));
$newAccount = null;
try {
	$newAccount = userInfoFromUrl($validator, $config->getAllServersInfo());
}
catch(Exception $e) {
	if(!isset($_GET["form"])) {
		showRegistrationForm($config->getAllServersInfo());
		exit();
	}
	throw $e;
}
$systemAccount = $config->getSystemAccountInfo($newAccount->server->name);

// Create a new account.
$registrator = new AccountManager($validator, new Tt5Session($systemAccount));
$registrator->createAccount($newAccount);
echo("Successfully created a new account named $newAccount->username on {$newAccount->server->title}!");

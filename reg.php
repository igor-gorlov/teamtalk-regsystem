<?php


/*
This script accepts user information and creates a new TeamTalk 5 account from it.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "init.php";

require_once "conf.php";
require_once "server.php";


// Is thrown when one or more URL parameters needed for some task are missing.
class BadQueryStringException extends RuntimeException {
	public function __construct(string $message) {
		parent::__construct($message);
	}
}

/*
Tries to construct an instance of UserInfo class using the given configuration manager
and the parameters passed via the URL query string.

Throws BadQueryStringException if the actual set of required fields within the URL is incomplete;
Throws RuntimeException if the user information is invalid.
*/
function userInfoFromUrl(ConfigManager $config): UserInfo {
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
	return new UserInfo($config, $_GET["name"], $_GET["password"]);
}


// Configure the essential options.
$config = new ConfigManager("config.json");
$serverName = "";
if(isset($_GET["server"])) {
	$serverName = $_GET["server"];
}
else {
	$serverName = "default";
}

// Establish connection.
$connection = new Tt5Session($serverName, $config);

// Authorize under the system account.
$connection->login();

// Create a new account.
$newUsername = $connection->createAccount(userInfoFromUrl($config));
echo("Successfully created a new account named $newUsername!");


?>

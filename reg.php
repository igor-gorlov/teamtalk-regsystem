<?php


/*
This script accepts user information and creates a new TeamTalk 5 account from it.

© Igor Gorlov, 2022.
*/


declare(strict_types = 1);


require_once "init.php";

require_once "configurator.php";
require_once "server.php";
require_once "validator.php";


// Is thrown when one or more URL parameters needed for some task are missing.
class BadQueryStringException extends RuntimeException {
	public function __construct(string $message) {
		parent::__construct($message);
	}
}

/*
Tries to construct an instance of UserInfo class from the parameters passed via the URL query string;
The given Validator object is used to ensure correctness of those parameters.

Throws BadQueryStringException if the actual set of required fields within the URL is incomplete;
Throws RuntimeException if the user information is invalid.
*/
function userInfoFromUrl(Validator $validator): UserInfo {
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
	return new UserInfo($validator, $_GET["name"], $_GET["password"]);
}


/*
Turns on output buffering and prints the header of the registration page.

From Regsystem's perspective, a "header of a page" is the part of HTML code that precedes main contents of this page;
not to be confused with <header>, <h1>, or other HTML elements.
*/
function beginRegistrationPage(): void {
	ob_start();
	echo("<!DOCTYPE html><html lang=\"en\">");
	echo("<head><meta charset=\"UTF-8\" />");
	echo("<title>TeamTalk Registration System by Igor Gorlov</title></head>");
	echo("<body><h1 style=\"text-align: center; text-decoration: underline; font-weight: bold\">");
	echo("Register a New TeamTalk Account</h1>");
}

/*
Prints the footer of the registration page, turns off output buffering, and flushes the buffer.

From Regsystem's perspective, a "footer of a page" is the part of HTML code that succeeds main contents of this page;
not to be confused with <footer> or other HTML elements.
*/
function endRegistrationPage(): void {
	echo("</body></html>");
	ob_end_flush();
}

// Prints the account creation form. Requires an array of ServerInfo objects representing the managed servers.
function showRegistrationForm(array $servers): void {
	ob_start();
	echo("<form method=\"GET\" action=\"reg.php\">");
	echo("<div><label for=\"server\">Select a server you would like to register on:</label><br>");
	echo("<select id=\"server\" name=\"server\">");
	foreach($servers as $server) {
		echo("<option value=\"$server->name\">$server->title</option>");
	}
	echo("</select></div>");
	echo("<div><label for=\"name\">Enter your username:</label><br>");
	echo("<input id=\"name\" type=\"text\" name=\"name\"></div>");
	echo("<div><label for=\"password\">Enter your password:</label><br>");
	echo("<input id=\"password\" type=\"password\" name=\"password\"></div>");
	echo("<div><button type=\"submit\" name=\"form\" value=\"1\">Register now!</button></div>");
	ob_end_flush();
}


// Set up GUI.
beginRegistrationPage();
register_shutdown_function("endRegistrationPage");

// Configure the essential options.
$config = new Configurator("config.json");
$validator = new Validator;
if($config->exists("validation")) {
	$validator->setRules($config->get("validation"));
}
$serverName = "";
if(isset($_GET["server"])) {
	$serverName = $_GET["server"];
}
else {
	$serverName = "default";
}
$server = $config->getServerInfo($serverName);
$systemAccount = $config->getSystemAccountInfo($serverName);
$newAccount = null;
try {
	$newAccount = userInfoFromUrl($validator);
}
catch(Exception $e) {
	if(!isset($_GET["form"])) {
		showRegistrationForm($config->getAllServersInfo());
		exit();
	}
	throw $e;
}

// Establish connection.
$connection = new Tt5Session($server, $systemAccount);

// Create a new account.
$newUsername = $connection->createAccount($newAccount);
echo("Successfully created a new account named $newUsername on $server->title!");

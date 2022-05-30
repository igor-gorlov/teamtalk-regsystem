<?php


/*
This script accepts user information and creates a new TeamTalk 5 account from it.

© Igor Gorlov, 2022.
*/


declare(strict_types=1);


require_once "init.php";

require_once "server.php";


// Is thrown when one or more URL parameters needed for some task are missing.
class BadQueryStringException extends RuntimeException
{
	public function __construct(string $message)
	{
		parent::__construct($message);
	}
}

/*
Tries to construct an instance of UserInfo class using parameters passed via the URL query string.
Throws BadQueryStringException if the actual set of required fields within the URL is incomplete.
*/
function userInfoFromUrl(): UserInfo
{
	$error = false;
	$errorMessage = "The following URL parameters are not provided:\n";
	if(!isset($_GET["name"]))
	{
		$error = true;
		$errorMessage .= "\tname\n";
	}
	if(!isset($_GET["password"]))
	{
		$error = true;
		$errorMessage .= "\tpassword\n";
	}
	if($error)
	{
		throw new BadQueryStringException($errorMessage);
	}
	return new UserInfo($_GET["name"], $_GET["password"]);
}


try
{

	// Configure the essential options.
	$serverName = "";
	if(isset($_GET["server"]))
	{
		$serverName = $_GET["server"];
	}
	else
	{
		$serverName = "default";
	}
	$serverInfo = Config::get("servers.$serverName");
	if($serverInfo === null)
	{
		throw new BadQueryStringException("Unknown server requested");
	}

	// Establish connection.
	$connection = new Tt5Session($serverInfo["host"], $serverInfo["port"]);

	// Authorize under the system account.
	$connection->login
	(
		new UserInfo
		(
			$serverInfo["systemAccount"]["username"],
			$serverInfo["systemAccount"]["password"],
			$serverInfo["systemAccount"]["nickname"]
		)
	);

	// Create a new account.
	$newUsername = $connection->createAccount(userInfoFromUrl());
	echo("Successfully created a new account named $newUsername!");

}
catch(Exception $e)
{
	echo("<table><tr><td style=\"color: red\"><strong>Error!</strong></td><td><pre><code>");
	echo($e->getMessage());
	echo("</code></pre></td></tr></table>");
}


?>

<?php


/*
This script accepts user information and creates a new TeamTalk 5 account from it.
© Igor Gorlov, 2022.
*/


require_once "server.php";


try
{

	// Configure the essential options.
	$host = "localhost"; // TeamTalk 5 server address.
	$port = "10333"; // TeamTalk 5 TCP port.
	$systemUsername = "regsystem"; /* The name of a registered admin account which will be used for all operations
	                                   involving the server (so-called "the system account"). */
	$systemPassword = "qwerty123456"; // The password of the system account.
	$systemNickname = "Registration System"; // The system account's nickname.

	// Establish connection.
	$connection = new Tt5Session($host, $port);

	// Authorize under the system account.
	$connection->login(new UserInfo($systemUsername, $systemPassword, $systemNickname));

	// Create a new account.
	$newUsername = $connection->createAccount(userInfoFromUrl());
	echo("Successfully created a new account named $newUsername!");

}
catch(Exception $e)
{
	echo("<pre>Error: ".$e->getMessage()."</pre>");
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


?>
